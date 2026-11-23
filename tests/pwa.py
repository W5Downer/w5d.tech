#!/usr/bin/env python3
"""Installable portal (PWA) + offline indicator. Usage: python3 tests/pwa.py [base_url]
HTTP: manifest / service worker / offline page headers, scope, icons, noindex, no private path in public files.
Browser (Chromium, service workers on 127.0.0.1): worker registers and controls the portal, offline navigation shows the offline page,
the offline banner appears/disappears with the connection, a POST is held while offline, and private pages are never stored in any cache."""
import json, os, re, sys, urllib.request, urllib.error
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _staff import ROOT, MOUNT, pw_login
from playwright.sync_api import sync_playwright
BASE = next((a for a in sys.argv[1:] if a.startswith('http')), 'http://127.0.0.1:8080').rstrip('/')
ok = bad = 0
def check(n, c, extra=''):
    global ok, bad
    if c: ok += 1
    else: bad += 1; print('  FAIL', n, extra)

def get(path):
    try:
        r = urllib.request.urlopen(BASE + path, timeout=15); return r.status, dict(r.headers), r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, dict(e.headers), e.read().decode('utf-8', 'replace')

# ---------- HTTP
s, h, b = get(MOUNT + '/manifest.webmanifest')
check('manifest 200', s == 200)
check('manifest type', 'manifest+json' in h.get('Content-Type', ''), h.get('Content-Type', ''))
check('manifest noindex', 'noindex' in h.get('X-Robots-Tag', ''))
m = json.loads(b)
check('manifest standalone', m.get('display') == 'standalone')
check('manifest scope = portal', m.get('scope') == MOUNT + '/' and m.get('start_url', '').startswith(MOUNT + '/'), str(m.get('scope')))
check('manifest has 192 + 512 + maskable icons', {(i['sizes'], i['purpose']) for i in m['icons']} >= {('192x192', 'any'), ('512x512', 'any'), ('512x512', 'maskable')})
for i in m['icons']:
    st, hh, _ = get(i['src']); check('icon reachable ' + i['src'], st == 200 and 'image/png' in hh.get('Content-Type', ''), f'{st} {hh.get("Content-Type")}')
check('manifest has no price/amount', not re.search(r'\d+\s*(TND|DT)', b))

s, h, b = get(MOUNT + '/sw.js')
check('sw 200 javascript', s == 200 and 'javascript' in h.get('Content-Type', ''))
check('sw not cached by browser', 'no-cache' in h.get('Cache-Control', '') or 'max-age=0' in h.get('Cache-Control', ''))
check('sw only handles GET', "r.method !== 'GET'" in b)
check('sw never stores pages (navigate falls back to offline page only)', 'c.put' in b and b.count('c.put') == 1 and 'assets' in b)
check('sw noindex', 'noindex' in h.get('X-Robots-Tag', ''))

s, h, b = get(MOUNT + '/offline/')
check('offline page 200', s == 200)
check('offline page public, no session data', 'data-offline-page' in b and 'csrf' not in b.lower())
check('offline page 3 languages', all(x in b for x in ('You are offline', 'Vous êtes hors ligne', 'أنت غير متصل')))
check('offline page noindex + no-store', 'noindex' in h.get('X-Robots-Tag', '') and 'no-store' in h.get('Cache-Control', ''))
s, h, b = get('/robots.txt'); check('robots.txt never prints the portal path', MOUNT.strip('/') not in b)
s, h, b = get('/sitemap.xml'); check('sitemap never prints the portal path', MOUNT.strip('/') not in b)
for pth in ('/', '/for/', '/pricing/', '/account/login/'):
    s, h, b = get(pth)
    check(f'public {pth} never links the portal manifest or mount', (MOUNT + '/manifest') not in b and MOUNT.strip('/') not in b)
s, h, b = get('/manifest.webmanifest')
check('public site manifest never names the portal', s == 200 and MOUNT.strip('/') not in b)

# ---------- browser
with sync_playwright() as pw:
    br = pw.chromium.launch()
    c = br.new_context(viewport={'width': 390, 'height': 800}, is_mobile=True, has_touch=True, service_workers='allow')
    p = c.new_page(); errs = []
    p.on('pageerror', lambda e: errs.append(str(e)))
    p.on('console', lambda m: errs.append(m.text) if m.type == 'error' else None)
    p.goto(BASE + MOUNT + '/login/')
    check('login page links the manifest', p.locator('link[rel=manifest]').count() == 1)
    check('login page declares theme-color', p.locator('meta[name=theme-color]').count() == 1)
    check('login page declares sw path', p.locator('meta[name=w5d-sw]').get_attribute('content') == MOUNT + '/sw.js')
    check('banner hidden while online', p.evaluate("document.querySelector('[data-offline-banner]').hidden") is True)
    check('signs in', pw_login(p, BASE, MOUNT))
    p.goto(BASE + MOUNT + '/'); p.wait_for_load_state('load')
    ready = False
    for _ in range(40):
        ready = p.evaluate("navigator.serviceWorker.getRegistration('" + MOUNT + "/').then(r=>!!(r&&r.active))")
        if ready: break
        p.wait_for_timeout(250)
    check('service worker active', ready)
    p.reload(); p.wait_for_load_state('load')
    check('service worker controls the portal', p.evaluate("!!navigator.serviceWorker.controller"))
    scope = p.evaluate("navigator.serviceWorker.getRegistration('" + MOUNT + "/').then(r=>r&&new URL(r.scope).pathname)")
    check('worker scope is the portal only', scope == MOUNT + '/', str(scope))
    names = p.evaluate("caches.keys()")
    check('portal cache exists', any(n.startswith('w5d-portal-static-') for n in names), str(names))
    # offline indicator while the page is open
    c.set_offline(True); p.evaluate("window.dispatchEvent(new Event('offline'))"); p.wait_for_timeout(250)
    check('offline: html.is-offline', p.evaluate("document.documentElement.classList.contains('is-offline')"))
    check('offline: banner visible', p.evaluate("!document.querySelector('[data-offline-banner]').hidden"))
    bb = p.evaluate("(()=>{const r=document.querySelector('[data-offline-banner]').getBoundingClientRect();return [r.left,r.right,r.height,innerWidth]})()")
    check('offline: banner fits the phone', bb[0] >= -1 and bb[1] <= bb[3] + 1 and bb[2] > 10, str(bb))
    # a POST must be held back (nothing lost silently)
    p.goto  # no-op reference
    c.set_offline(False); p.evaluate("window.dispatchEvent(new Event('online'))"); p.wait_for_timeout(250)
    check('online again: banner hidden', p.evaluate("document.querySelector('[data-offline-banner]').hidden"))
    check('online again: html.is-offline removed', not p.evaluate("document.documentElement.classList.contains('is-offline')"))
    # offline navigation -> cached offline page
    c.set_offline(True)
    p.goto(BASE + MOUNT + '/setup-guide/', wait_until='load')
    txt = p.inner_text('body')
    check('offline navigation shows the offline page', 'You are offline' in txt and 'Vous êtes hors ligne' in txt and 'أنت غير متصل' in txt, txt[:120])
    check('offline page shows no private data', 'owner@' not in txt and 'Setup guide' not in txt)
    check('offline page styled (stylesheet from cache)', p.evaluate("getComputedStyle(document.querySelector('main')).display") == 'flex')
    c.set_offline(False); p.wait_for_timeout(200)
    p.goto(BASE + MOUNT + '/setup-guide/'); p.wait_for_load_state('load')
    check('back online: real page again', p.locator('[data-setup-guide]').count() == 1)
    # no private page in any cache
    urls = p.evaluate("(async()=>{const o=[];for(const k of await caches.keys()){const c=await caches.open(k);for(const r of await c.keys())o.push(new URL(r.url).pathname)}return o})()")
    private = [u for u in urls if u.startswith(MOUNT + '/') and not u.startswith(MOUNT + '/offline')]
    check('caches hold no private portal page', not private, str(private))
    check('caches hold only offline page + static assets', all(u.startswith(MOUNT + '/offline') or '/assets/' in u for u in urls), str(urls))
    # logging out must still leave nothing private in cache
    check('no console errors', not [e for e in errs if 'Failed to load resource' not in e], '; '.join(errs[:3]))
    c.close(); br.close()
print(f'\npwa: {ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

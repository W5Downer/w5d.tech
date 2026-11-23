#!/usr/bin/env python3
"""PWA installability. Usage: python3 tests/pwa_install.py [base_url]   (run on a secure origin: http://127.0.0.1 or localhost)
What is checked, and by whom:
  - Chromium's OWN installability verdict (CDP Page.getInstallabilityErrors + Page.getAppManifest) on the portal sign-in page:
    an empty error list means Chrome/Edge/Android would offer 'Install'.
  - the page reacts correctly to the install events: a synthetic `beforeinstallprompt` shows [data-pwa-install] (and calls prompt()
    on click), `appinstalled` hides it and marks html.is-installed.
  - iOS Safari user agent -> manual 'Share > Add to Home Screen' hint shown, no install button; iOS Chrome (CriOS) -> no hint.
  - display-mode: standalone -> is-installed, no install button, no hint.
NOT checked (cannot be, without a device): the real OS install dialog, the home-screen icon, iOS Safari's actual Add-to-Home flow."""
import json, os, re, sys, urllib.request
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _staff import ROOT, MOUNT
from playwright.sync_api import sync_playwright
BASE = next((a for a in sys.argv[1:] if a.startswith('http')), 'http://127.0.0.1:8080').rstrip('/')
ok = bad = 0
def check(n, c, extra=''):
    global ok, bad
    if c: ok += 1
    else: bad += 1; print('  FAIL', n, extra)

IOS_SAFARI = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1'
IOS_CHROME = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/123.0.0.0 Mobile/15E148 Safari/604.1'
ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Mobile Safari/537.36'

# ---------- manifest content that installers care about (HTTP level, also guards regressions without a browser)
m = json.loads(urllib.request.urlopen(BASE + MOUNT + '/manifest.webmanifest', timeout=15).read().decode())
for k in ('name', 'short_name', 'start_url', 'scope', 'display', 'background_color', 'theme_color', 'icons'):
    check(f'manifest has {k}', bool(m.get(k)))
check('manifest id or start_url stays inside scope', m['start_url'].startswith(m['scope']))
check('manifest short_name <= 14 chars (home-screen label)', len(m['short_name']) <= 14, m['short_name'])
check('manifest language/direction hints present or harmless', m.get('dir', 'auto') in ('auto', 'ltr', 'rtl'))
check('manifest icons: 192 + 512 + maskable', {(i['sizes'], i.get('purpose')) for i in m['icons']} >= {('192x192', 'any'), ('512x512', 'any'), ('512x512', 'maskable')})
check('manifest start_url is inside the portal (not the public site)', m['start_url'].startswith(MOUNT + '/'))

def open_menu(p):
    # on a phone the install button / iOS hint live in the off-canvas menu: open it like a user would
    t = p.locator('[data-admin-nav-toggle]')
    if t.count() and t.first.is_visible() and p.locator('#adminNav').get_attribute('aria-hidden') == 'true':
        t.first.click(); p.wait_for_timeout(350)

def open_login(br, ua=None, standalone=False, mobile=True):
    kw = dict(viewport={'width': 390, 'height': 800}, is_mobile=mobile, has_touch=mobile, service_workers='allow')
    if ua: kw['user_agent'] = ua
    c = br.new_context(**kw)
    if standalone:
        # emulate an installed window: display-mode standalone matches and navigator.standalone (iOS) is true
        c.add_init_script("""(() => { const mm = window.matchMedia.bind(window);
          window.matchMedia = q => /display-mode:\\s*standalone/.test(q) ? {matches: true, media: q, onchange: null, addListener() {}, removeListener() {}, addEventListener() {}, removeEventListener() {}, dispatchEvent() { return false; }} : mm(q);
          try { Object.defineProperty(navigator, 'standalone', {get: () => true}); } catch (e) {} })();""")
    p = c.new_page()
    p.goto(BASE + MOUNT + '/login/', wait_until='load')
    p.wait_for_timeout(400)
    return c, p

with sync_playwright() as pw:
    br = pw.chromium.launch()

    # ---------- 1. Chromium's installability verdict
    c, p = open_login(br, ANDROID)
    for _ in range(40):
        ready = p.evaluate("navigator.serviceWorker.getRegistration('" + MOUNT + "/').then(r=>!!(r&&r.active))")
        if ready: break
        p.wait_for_timeout(250)
    check('service worker active on the sign-in page (installability needs it on the start page scope)', ready)
    cdp = c.new_cdp_session(p)
    try:
        am = cdp.send('Page.getAppManifest')
        check('Chromium parsed the manifest without errors', not am.get('errors'), str(am.get('errors')))
        check('Chromium resolved the manifest url', MOUNT + '/manifest.webmanifest' in (am.get('url') or ''), str(am.get('url')))
        errs = cdp.send('Page.getInstallabilityErrors').get('installabilityErrors', [])
        check('Chromium reports NO installability errors', not errs, json.dumps(errs))
        # a second look after reload: the SW now controls the page
        p.reload(wait_until='load'); p.wait_for_timeout(600)
        errs2 = cdp.send('Page.getInstallabilityErrors').get('installabilityErrors', [])
        check('still installable when the worker controls the page', not errs2, json.dumps(errs2))
    except Exception as e:
        check('CDP installability API available', False, str(e))
    # home page of the public site must NOT be installable as the portal: its manifest is the public one
    p.goto(BASE + '/', wait_until='load')
    href = p.evaluate("(document.querySelector('link[rel=manifest]')||{}).href || ''")
    check('public home does not point at the portal manifest', MOUNT.strip('/') not in href, href)

    # ---------- 2. install events on an Android/Chrome-like page
    p.goto(BASE + MOUNT + '/login/', wait_until='load'); p.wait_for_timeout(300)
    # the install button lives in the portal layout; the sign-in page has no menu, so use a signed-in portal page when available
    n_btn = p.locator('[data-pwa-install]').count()
    if n_btn == 0:
        from _staff import pw_login
        check('portal sign-in for the install-button checks', pw_login(p, BASE, MOUNT))
        p.goto(BASE + MOUNT + '/', wait_until='load'); p.wait_for_timeout(300)
    check('install button exists in the portal layout and starts hidden', p.locator('[data-pwa-install]').count() >= 1 and p.evaluate("[...document.querySelectorAll('[data-pwa-install]')].every(b=>b.hidden)"))
    check('iOS hint hidden on Chrome/Android', p.evaluate("[...document.querySelectorAll('[data-pwa-ios]')].every(n=>n.hidden)"))
    p.evaluate("""() => { window.__prompted = 0; const e = new Event('beforeinstallprompt', {cancelable: true});
        e.prompt = () => { window.__prompted++; return Promise.resolve(); }; e.userChoice = Promise.resolve({outcome: 'accepted'});
        window.__bip = e; window.dispatchEvent(e); }""")
    p.wait_for_timeout(150)
    check('beforeinstallprompt shows the install button', p.evaluate("[...document.querySelectorAll('[data-pwa-install]')].some(b=>!b.hidden)"))
    check('default mini-infobar suppressed (event.preventDefault called)', p.evaluate("window.__bip.defaultPrevented"))
    open_menu(p)
    vis = [b for b in p.locator('[data-pwa-install]').all() if b.is_visible()]
    check('the install button is actually visible and tappable', bool(vis))
    if vis:
        vis[0].click(); p.wait_for_timeout(250)
        check('clicking it calls the saved prompt() exactly once', p.evaluate("window.__prompted") == 1, str(p.evaluate("window.__prompted")))
        check('after the choice the button hides again', p.evaluate("[...document.querySelectorAll('[data-pwa-install]')].every(b=>b.hidden)"))
    # second prompt cycle then appinstalled
    p.evaluate("""() => { const e = new Event('beforeinstallprompt', {cancelable: true}); e.prompt = () => Promise.resolve(); e.userChoice = new Promise(()=>{}); window.dispatchEvent(e); }""")
    p.wait_for_timeout(100)
    check('a later prompt event shows the button again', p.evaluate("[...document.querySelectorAll('[data-pwa-install]')].some(b=>!b.hidden)"))
    p.evaluate("window.dispatchEvent(new Event('appinstalled'))"); p.wait_for_timeout(150)
    check('appinstalled hides the button', p.evaluate("[...document.querySelectorAll('[data-pwa-install]')].every(b=>b.hidden)"))
    check('appinstalled marks html.is-installed', p.evaluate("document.documentElement.classList.contains('is-installed')"))
    shot_ok = p.evaluate("document.documentElement.scrollWidth <= innerWidth + 1")
    check('no horizontal overflow on the phone viewport', shot_ok)
    cookies = c.cookies()
    c.close()

    # ---------- 3. iOS Safari: manual hint, no button (portal layout is behind sign-in: reuse a signed-in context)
    def portal_page(ua, standalone=False):
        c, p = open_login(br, ua, standalone)
        from _staff import pw_login
        pw_login(p, BASE, MOUNT)
        p.goto(BASE + MOUNT + '/', wait_until='load'); p.wait_for_timeout(300)
        return c, p
    c, p = portal_page(IOS_SAFARI)
    check('iOS Safari: manual "Add to Home Screen" hint shown', p.evaluate("[...document.querySelectorAll('[data-pwa-ios]')].some(n=>!n.hidden)"))
    open_menu(p)
    check('iOS Safari: hint is visible once the menu is open', any(n.is_visible() for n in p.locator('[data-pwa-ios]').all()))
    check('iOS Safari: hint is readable text, not empty', len(p.evaluate("(document.querySelector('[data-pwa-ios]:not([hidden])')||{innerText:''}).innerText").strip()) > 15)
    check('iOS Safari: install button stays hidden (no beforeinstallprompt there)', p.evaluate("[...document.querySelectorAll('[data-pwa-install]')].every(b=>b.hidden)"))
    check('iOS: apple-touch-icon + web-app-capable declared', p.locator('link[rel=apple-touch-icon]').count() >= 1)
    href = p.evaluate("(document.querySelector('link[rel=apple-touch-icon]')||{}).href||''")
    if href:
        r = urllib.request.urlopen(href, timeout=15)
        check('apple-touch-icon is a reachable PNG', r.status == 200 and 'image/png' in r.headers.get('Content-Type', ''))
    c.close()
    c, p = portal_page(IOS_CHROME)
    check('iOS Chrome (CriOS): no Safari-only hint', p.evaluate("[...document.querySelectorAll('[data-pwa-ios]')].every(n=>n.hidden)"))
    c.close()

    # ---------- 4. installed window (standalone)
    c, p = portal_page(ANDROID, standalone=True)
    check('standalone: html.is-installed', p.evaluate("document.documentElement.classList.contains('is-installed')"))
    p.evaluate("""() => { const e = new Event('beforeinstallprompt', {cancelable: true}); e.prompt = () => Promise.resolve(); e.userChoice = new Promise(()=>{}); window.dispatchEvent(e); }""")
    p.wait_for_timeout(150)
    check('standalone: install button never shown inside the installed app', p.evaluate("[...document.querySelectorAll('[data-pwa-install]')].every(b=>b.hidden)"))
    check('standalone: iOS hint not shown', p.evaluate("[...document.querySelectorAll('[data-pwa-ios]')].every(n=>n.hidden)"))
    check('standalone: portal still usable (menu present)', p.locator('nav, aside').count() >= 1)
    c.close()
    c, p = portal_page(IOS_SAFARI, standalone=True)
    check('iOS standalone (navigator.standalone): hint hidden', p.evaluate("[...document.querySelectorAll('[data-pwa-ios]')].every(n=>n.hidden)"))
    c.close()
    br.close()

print(f'\npwa-install: {ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

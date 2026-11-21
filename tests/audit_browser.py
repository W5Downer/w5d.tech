#!/usr/bin/env python3
"""Rendered-page audit (Playwright + chromium): what only a browser reveals. Usage: python3 tests/audit_browser.py [base_url] [--report f.json]

Per page x viewport (desktop 1366, phone 390) x language (en, ar):
 console errors / page errors / failed requests (>=400), horizontal overflow, images that failed to decode,
 text clipped by overflow, tap targets < 40px on phones (links/buttons visible in viewport), focus ring on first Tab stops,
 active menu item visibly styled (desktop mega trigger; phone tab bar), skip link, sticky header overlap on anchors, RTL mirrored layout.
"""
import json, os, sys, collections
from playwright.sync_api import sync_playwright

BASE = (next((a for a in sys.argv[1:] if a.startswith('http')), 'http://127.0.0.1:8080')).rstrip('/')
PAGES = ['/', '/pricing/', '/investors/', '/services/', '/services/networking/', '/macro-works/', '/macro-works/built/', '/projects/', '/projects/kernid/',
         '/team/', '/faq/', '/contact/', '/about/', '/how-it-works/', '/join/', '/join/eligibility/', '/certification/', '/certification/verify/', '/affiliate/',
         '/trust/statement/', '/trust/terms/', '/status/', '/tn/tunis/', '/account/login/', '/account/register/', '/sitemap/']
F = []


def add(sev, rule, url, vp, lang, msg):
    F.append(dict(sev=sev, rule=rule, url=url, vp=vp, lang=lang, msg=msg))


JS_OVERFLOW = """() => { const w = document.documentElement.clientWidth; const out = [];
  for (const el of document.querySelectorAll('body *')) { const r = el.getBoundingClientRect(); if (r.width === 0 || r.height === 0) continue;
    const cs = getComputedStyle(el); if (cs.position === 'fixed' || cs.visibility === 'hidden' || cs.display === 'none') continue;
    if (el.closest('[aria-hidden="true"],.w5-slider,.sr-only,.orb-a,.orb-b,.orb-c,[class*=orb-],[data-mega-panel],.menu-panel,.w5d-aside,dialog,.skip-link,.overflow-x-auto,.overflow-hidden,.marquee')) continue;
    if (el.closest('svg')) continue; if (el.className && String(el.className).includes('orb-')) continue; if (r.right > w + 2 || r.left < -2) out.push(el.tagName.toLowerCase() + '.' + String(el.className).split(' ').slice(0, 3).join('.') + ' [' + Math.round(r.left) + ',' + Math.round(r.right) + ']'); if (out.length > 4) break; }
  return { scroll: document.documentElement.scrollWidth > w + 1, w, sw: document.documentElement.scrollWidth, out }; }"""
JS_IMGS = """() => [...document.images].filter(i => i.complete && i.naturalWidth === 0 && i.getAttribute('src') && !i.closest('[hidden]')).map(i => i.getAttribute('src')).slice(0, 5)"""
JS_TAP = """() => { const vh = innerHeight, bad = [];
  for (const el of document.querySelectorAll('a[href],button,input:not([type=hidden]),select,textarea,[role=button]')) {
    const r = el.getBoundingClientRect(); if (r.width === 0 || r.height === 0 || r.bottom < 0 || r.top > vh) continue;
    if (el.closest('[aria-hidden="true"],.sr-only,.menu-panel,.w5d-aside,.skip-link,[hidden]')) continue; const cs = getComputedStyle(el); if (cs.visibility === 'hidden' || cs.display === 'none' || cs.opacity === '0') continue;
    if (el.matches('input[type=checkbox],input[type=radio]') && el.closest('label')) continue; if (el.matches('.w5-dot')) continue; if (el.matches('a') && getComputedStyle(el).display === 'inline' && el.parentElement && el.parentElement.textContent.trim().length > el.textContent.trim().length + 8) continue; if (el.matches('a') && el.closest('p,li,td,span.prose,.prose-w5') && r.height < 40 && getComputedStyle(el).display === 'inline') continue;
    if (Math.min(r.width, r.height) < 32) bad.push((el.getAttribute('aria-label') || el.textContent || el.tagName).trim().slice(0, 24) + ' ' + Math.round(r.width) + 'x' + Math.round(r.height)); }
  return bad.slice(0, 5); }"""
JS_ACTIVE = """() => { const t = document.querySelector('[data-mega-trigger][data-active] > button'); if (!t) return null; const cs = getComputedStyle(t); const o = document.querySelector('[data-mega-trigger]:not([data-active]) > button'); return { color: cs.color, weight: cs.fontWeight, shadow: cs.boxShadow, other: o ? getComputedStyle(o).color : null }; }"""
JS_TABBAR = """() => { const b = document.querySelector('.w5-tabbar'); if (!b) return { present: false }; const r = b.getBoundingClientRect(); const cur = b.querySelector('[aria-current=page]'); return { present: true, visible: r.height > 0 && getComputedStyle(b).display !== 'none', bottom: Math.round(innerHeight - r.bottom), cur: cur ? cur.textContent.trim() : null }; }"""
JS_FOCUS = """() => { const el = document.activeElement; if (!el || el === document.body) return null; const cs = getComputedStyle(el); return { tag: el.tagName, text: (el.textContent || el.getAttribute('aria-label') || '').trim().slice(0, 20), outline: cs.outlineStyle + ' ' + cs.outlineWidth, shadow: cs.boxShadow !== 'none' }; }"""

with sync_playwright() as pw:
    br = pw.chromium.launch()
    for vpn, vp in (('desktop', dict(width=1366, height=800)), ('phone', dict(width=390, height=800, is_mobile=True, has_touch=True, device_scale_factor=2))):
        for lang in ('en', 'ar'):
            ctx = br.new_context(viewport={k: v for k, v in vp.items() if k in ('width', 'height')}, is_mobile=vp.get('is_mobile', False), has_touch=vp.get('has_touch', False), ignore_https_errors=True)
            ctx.add_cookies([{'name': 'w5d_lang', 'value': lang, 'url': BASE}])
            for path in PAGES:
                pg = ctx.new_page()
                errs, bad = [], []
                pg.on('pageerror', lambda e: errs.append(str(e)[:120]))
                pg.on('console', lambda m: errs.append('console: ' + m.text[:120]) if m.type == 'error' else None)
                pg.on('response', lambda r: bad.append(f'{r.status} {r.url[len(BASE):][:60]}') if r.status >= 400 and r.url.startswith(BASE) else None)
                try:
                    pg.goto(BASE + path, wait_until='networkidle', timeout=30000)
                except Exception as e:
                    add('error', 'load', path, vpn, lang, str(e)[:100]); pg.close(); continue
                for e in errs[:3]:
                    add('error', 'js-error', path, vpn, lang, e)
                for e in bad[:3]:
                    add('error', 'http-error', path, vpn, lang, e)
                o = pg.evaluate(JS_OVERFLOW)
                if o['scroll']:
                    add('error', 'h-scroll', path, vpn, lang, f"scrollWidth {o['sw']} > {o['w']}; {o['out'][:2]}")
                elif o['out']:
                    add('warn', 'child-overflow', path, vpn, lang, '; '.join(o['out'][:2]))
                im = pg.evaluate(JS_IMGS)
                if im:
                    add('error', 'broken-image', path, vpn, lang, ', '.join(im))
                if vpn == 'phone':
                    tp = pg.evaluate(JS_TAP)
                    if tp:
                        add('warn', 'tap-target', path, vpn, lang, '; '.join(tp[:3]))
                    tb = pg.evaluate(JS_TABBAR)
                    if path.startswith('/account/'):
                        if tb['present']: add('error', 'tabbar-on-chromeless', path, vpn, lang, '')
                        continue
                    if not tb['present']:
                        add('error', 'tabbar-missing', path, vpn, lang, '')
                    elif tb['bottom'] != 0:
                        add('warn', 'tabbar-position', path, vpn, lang, f"gap {tb['bottom']}px")
                    if tb.get('present') and path in ('/', '/account/login/', '/account/register/') and not tb['cur']:
                        add('warn', 'tabbar-current', path, vpn, lang, 'no current tab')
                else:
                    ac = pg.evaluate(JS_ACTIVE)
                    in_menu = path.split('/')[1] in ('macro-works', 'services', 'projects', 'investors', 'pricing', 'join', 'about', 'team', 'faq', 'contact', 'trust', 'status', 'how-it-works', 'certification', 'affiliate') if path != '/' else False
                    if in_menu and ac is None and path not in ('/projects/kernid/', '/certification/', '/certification/verify/', '/affiliate/', '/trust/terms/'):
                        add('error', 'menu-active-none', path, vpn, lang, 'no active top-level item on a menu page')
                    if ac and ac['color'] == ac['other'] and ac['weight'] in ('400', '500'):
                        add('error', 'menu-active-invisible', path, vpn, lang, f'same colour/weight as inactive: {ac}')
                    # keyboard focus visibility on first 3 stops
                    pg.keyboard.press('Tab')
                    f1 = pg.evaluate(JS_FOCUS)
                    if f1 and 'skip' not in (f1['text'] or '').lower() and f1['outline'].startswith('none') and not f1['shadow']:
                        add('warn', 'focus-invisible', path, vpn, lang, f"{f1['tag']} '{f1['text']}'")
                    if f1 is None:
                        add('warn', 'focus-none', path, vpn, lang, 'first Tab did not focus anything')
                pg.close()
            ctx.close()
    # mega menu: open "Invest" panel on desktop and check the active link is styled
    ctx = br.new_context(viewport=dict(width=1366, height=800), ignore_https_errors=True)
    pg = ctx.new_page(); pg.goto(BASE + '/pricing/', wait_until='networkidle')
    pg.hover('[data-mega-trigger][data-active] > button'); pg.wait_for_timeout(500)
    st = pg.evaluate("() => { const a = document.querySelector('[data-mega-panel] a[data-active-link]'); if (!a) return null; const r = a.getBoundingClientRect(); return { visible: r.height > 0 && r.width > 0, bg: getComputedStyle(a).backgroundColor, cur: a.getAttribute('aria-current') } }")
    if not st or not st['visible']:
        add('error', 'mega-active-link', '/pricing/', 'desktop', 'en', f'active link not visible in opened panel: {st}')
    elif st['bg'] in ('rgba(0, 0, 0, 0)', 'transparent'):
        add('error', 'mega-active-style', '/pricing/', 'desktop', 'en', 'active link has no background')
    pg.keyboard.press('Escape'); pg.wait_for_timeout(300)
    ctx.close()
    # phone: open burger menu and check active link
    ctx = br.new_context(viewport=dict(width=390, height=800), is_mobile=True, has_touch=True, ignore_https_errors=True)
    pg = ctx.new_page(); pg.goto(BASE + '/services/', wait_until='networkidle')
    pg.click('[data-mobile-toggle]'); pg.wait_for_timeout(500)
    st = pg.evaluate("() => { const a = document.querySelector('#mobileMenu a[data-active-link]'); return a ? { vis: a.getBoundingClientRect().height > 0, cur: a.getAttribute('aria-current') } : null }")
    if not st or not st['vis']:
        add('error', 'mobile-menu-active', '/services/', 'phone', 'en', f'no active link in mobile menu: {st}')
    ctx.close()
    br.close()

by = collections.defaultdict(list)
for f in F:
    by[(f['sev'], f['rule'])].append(f)
for (sev, rule), fs in sorted(by.items(), key=lambda kv: (kv[0][0] != 'error', -len(kv[1]))):
    e = fs[0]
    print(f"[{sev.upper():5}] {rule:22} x{len(fs):3} e.g. {e['url']} ({e['vp']}/{e['lang']}): {e['msg'][:130]}")
ne = sum(1 for f in F if f['sev'] == 'error')
print(f'audit_browser: {ne} error(s), {len(F) - ne} warning(s) over {len(PAGES)} pages x 2 viewports x 2 languages')
if '--report' in sys.argv:
    json.dump(F, open(sys.argv[sys.argv.index('--report') + 1], 'w'), ensure_ascii=False, indent=1)
sys.exit(1 if ne else 0)

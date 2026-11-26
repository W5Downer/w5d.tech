#!/usr/bin/env python3
"""Help icons on every dashboard page. Usage: python3 tests/help.py [base_url]
Static: every portal menu and account panel has an entry in config/help.php, in 3 languages, both modes (tip/pop) in use, no prices.
Live (Playwright, phone size): the icon is reachable, a tooltip opens/closes (tap, Escape, outside tap), a popup opens as a dialog and closes,
nothing overflows the window, and the icon is >= 40px."""
import os, re, subprocess, sys, json
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _staff import ROOT, MOUNT, pw_login
from playwright.sync_api import sync_playwright
BASE = next((a for a in sys.argv[1:] if a.startswith('http')), 'http://127.0.0.1:8080').rstrip('/')
ok = bad = 0
def check(n, c, extra=''):
    global ok, bad
    if c: ok += 1
    else: bad += 1; print('  FAIL', n, extra)

# ---------- static
h = json.loads(subprocess.check_output(['php', '-r', 'echo json_encode(require "config/help.php");'], cwd=ROOT))
roles = json.loads(subprocess.check_output(['php', '-r', '$r=require "config/roles.php"; echo json_encode(array_map(fn($m)=>$m["slug"], $r["menus"]));'], cwd=ROOT) or b'[]')
slugs = [s for s in roles if isinstance(s, str)]
check('menu slugs loaded', len(slugs) >= 40, str(len(slugs)))
for s in slugs:
    check(f'help entry admin.{s}', f'admin.{s}' in h)
for pnl in ['overview', 'orders', 'payments', 'applications', 'notifications', 'profile', 'security', 'api', 'qcm', 'certificates', 'affiliate', 'invoices', 'plan']:
    check(f'help entry account.{pnl}', f'account.{pnl}' in h)
modes = {v[0] for v in h.values()}
check('both modes used', modes == {'tip', 'pop'}, str(modes))
for k, v in h.items():
    check(f'{k} has 3 languages', len(v) == 4 and all(isinstance(x, str) and len(x) > 25 for x in v[1:]))
    check(f'{k} no hard-coded amounts', not re.search(r'\d+\s*(TND|DT|dinars?|€|\$)', ' '.join(v[1:]), re.I))
    check(f'{k} no promise wording', not re.search(r'guarantee(d)?\b.*return|garanti.*rendement', ' '.join(v[1:]), re.I))

# ---------- live
def open_tip(p, sel):
    p.locator(sel).first.tap(); p.wait_for_timeout(150)
def visible_tip(p):
    return p.evaluate("[...document.querySelectorAll('.w5-help-tip')].filter(e=>!e.hidden).length")
def inside(p, sel):
    return p.evaluate("(s)=>{const e=[...document.querySelectorAll(s)].find(x=>x.getClientRects().length);if(!e)return null;const r=e.getBoundingClientRect();return {l:r.left,r:r.right,w:innerWidth,h:r.height,wd:r.width}}", sel)

with sync_playwright() as pw:
    br = pw.chromium.launch()
    for (w, hgt, lang) in ((360, 740, 'en'), (390, 800, 'ar'), (1280, 800, 'fr')):
        mobile = w < 1000
        c = br.new_context(viewport={'width': w, 'height': hgt}, is_mobile=mobile, has_touch=mobile, ignore_https_errors=True, service_workers='block')
        p = c.new_page(); errs = []
        p.on('pageerror', lambda e: errs.append(str(e)))
        p.on('console', lambda m: errs.append(m.text) if m.type == 'error' else None)
        if lang != 'en': p.goto(BASE + f'/language/{lang}?r=%2F')
        p.goto(BASE + MOUNT + '/login/')
        check(f'{w}/{lang} login has help icon', p.locator('.w5-help-btn').count() >= 1)
        check(f'{w}/{lang} login help is popup', p.locator('[data-help=pop]').count() == 1)
        p.locator('[data-help-pop]').first.click(); p.wait_for_timeout(200)
        check(f'{w}/{lang} login popup opens', p.evaluate("!!document.querySelector('dialog[open]')"))
        p.keyboard.press('Escape'); p.wait_for_timeout(200)
        check(f'{w}/{lang} popup closes on Escape', not p.evaluate("!!document.querySelector('dialog[open]')"))
        check(f'{w}/{lang} login signs in', pw_login(p, BASE, MOUNT))
        for slug, mode in (('command-center', 'pop'), ('countries', 'tip'), ('setup-guide', 'tip'), ('funding', 'pop'), ('team', 'pop'), ('payment-methods', 'pop'), ('campaigns/new', 'tip')):
            path = MOUNT + ('/' if slug == 'command-center' else f'/{slug}/')
            p.goto(BASE + path); p.wait_for_timeout(200)
            key = 'admin.' + ('crud-form' if '/new' in slug else slug)
            sel = f'[data-help-key="{key}"] .w5-help-btn'
            vis = p.evaluate("(s)=>[...document.querySelectorAll(s)].filter(e=>e.getClientRects().length).length", sel)
            check(f'{w}/{lang} {slug} help icon visible', vis >= 1, f'visible={vis}')
            if not vis: continue
            r = p.evaluate("(s)=>{const e=[...document.querySelectorAll(s)].find(x=>x.getClientRects().length);const r=e.getBoundingClientRect();return [r.width,r.height]}", sel)
            check(f'{w}/{lang} {slug} help icon >= 40px', r[0] >= 39.5 and r[1] >= 39.5, str(r))
            vs = f'[data-help-key="{key}"]'
            if mode == 'tip':
                p.evaluate("(s)=>[...document.querySelectorAll(s+' .w5-help-btn')].find(x=>x.getClientRects().length).click()", vs); p.wait_for_timeout(150)
                check(f'{w}/{lang} {slug} tooltip opens', visible_tip(p) == 1, str(visible_tip(p)))
                g = inside(p, '.w5-help-tip:not([hidden])')
                check(f'{w}/{lang} {slug} tooltip inside window', g and g['l'] >= -1 and g['r'] <= g['w'] + 1, str(g))
                p.keyboard.press('Escape'); p.wait_for_timeout(120)
                check(f'{w}/{lang} {slug} tooltip closes on Escape', visible_tip(p) == 0)
                p.evaluate("(s)=>[...document.querySelectorAll(s+' .w5-help-btn')].find(x=>x.getClientRects().length).click()", vs); p.wait_for_timeout(100)
                p.mouse.click(5, hgt - 5) if not mobile else p.touchscreen.tap(5, hgt - 5); p.wait_for_timeout(150)
                check(f'{w}/{lang} {slug} tooltip closes on outside tap', visible_tip(p) == 0)
            else:
                p.evaluate("(s)=>[...document.querySelectorAll(s+' .w5-help-btn')].find(x=>x.getClientRects().length).click()", vs); p.wait_for_timeout(200)
                d = p.evaluate("(()=>{const d=document.querySelector('dialog[open]');if(!d)return null;const r=d.querySelector('.w5-dialog-card').getBoundingClientRect();return {l:r.left,r:r.right,t:r.top,b:r.bottom,w:innerWidth,h:innerHeight}})()")
                check(f'{w}/{lang} {slug} popup opens', d is not None)
                if d: check(f'{w}/{lang} {slug} popup fits window', d['l'] >= -1 and d['r'] <= d['w'] + 1 and d['t'] >= -1 and d['b'] <= d['h'] + 1, str(d))
                p.locator('dialog[open] .btn-focus').first.click(); p.wait_for_timeout(200)
                check(f'{w}/{lang} {slug} popup closes with Got it', not p.evaluate("!!document.querySelector('dialog[open]')"))
        check(f'{w}/{lang} no console errors', not errs, '; '.join(errs[:3]))
        c.close()
    br.close()
print(f'\nhelp: {ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

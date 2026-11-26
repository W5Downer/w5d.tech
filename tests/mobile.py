#!/usr/bin/env python3
"""Mobile/responsive audit (Playwright). Usage: python3 tests/mobile.py [base_url] [--verbose]
Visits public, account and owner-panel pages at phone widths and fails on horizontal overflow or elements outside the window."""
import os, re, shutil, sys, glob, time
from playwright.sync_api import sync_playwright
BASE = (next((a for a in sys.argv[1:] if a.startswith('http')), 'http://127.0.0.1:8080')).rstrip('/')
VERBOSE = '--verbose' in sys.argv
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
shutil.rmtree(os.path.join(ROOT, 'storage/cache/rl'), ignore_errors=True)
OWNER_USER = os.environ.get('W5D_OWNER_USER', 'owner'); OWNER_PASS = os.environ.get('W5D_OWNER_PASS', 'w5d-demo-owner')
ADMIN = '/' + (os.environ.get('W5D_ADMIN_PATH', 'panel-x9z')).strip('/')
PUBLIC = ['/', '/for/', '/for/investor/', '/join/', '/pricing/', '/investors/', '/checkout/', '/subscription/', '/careers/', '/contact/', '/faq/', '/status/', '/macro-works/', '/services/', '/projects/', '/account/login/', '/account/register/', '/account/forgot/', '/trust/', '/join/eligibility/', '/countries/tunisia/', '/how-it-works/', '/about/', '/newsletter/', '/certification/', '/affiliate/', '/certification/verify/']
ADMIN_PAGES = ['/', '/contracts/', '/invoices/', '/my-invoices/', '/milestones/', '/plans/', '/plan-subscriptions/', '/cert-fees/', '/placements/', '/images/', '/engagements/', '/directives/', '/applicants/', '/services/', '/progressive-pricing/', '/investors/', '/variables/', '/payments-d17/', '/subscriptions/', '/inbox/', '/audit/', '/system/', '/quality/', '/manager-applications/', '/campaigns/', '/campaigns/new/', '/teams/', '/teams/new/', '/clients/', '/clients/new/', '/support/', '/payroll/', '/users/', '/academies/', '/verification/', '/weekly/', '/api-docs/', '/setup-guide/', '/team/', '/team/new/', '/funding/', '/integrations/', '/payment-methods/', '/offline/']
ok = bad = 0
JS = """() => {
  const vw = document.documentElement.clientWidth, out = [];
  const sw = document.documentElement.scrollWidth;
  const vis = e => { const cs = getComputedStyle(e); return cs.display !== 'none' && cs.visibility !== 'hidden' && e.getClientRects().length; };
  const inClip = e => { for (let p = e.parentElement; p && p !== document.body; p = p.parentElement) { const cs = getComputedStyle(p); if (/(auto|scroll|hidden|clip)/.test(cs.overflowX) && p.scrollWidth <= p.clientWidth + 1 || /(auto|scroll)/.test(cs.overflowX)) return true; } return false; };
  const rtl = getComputedStyle(document.documentElement).direction === 'rtl';
  for (const e of document.body.querySelectorAll('*')) {
    if (!vis(e) || e.closest('#w5d-loader, aside.w5d-aside:not([aria-hidden=false]), [data-mega-panel], #mobileMenu:not(.is-open), svg *, .sr-only, .skip-link, [aria-hidden=true], .pointer-events-none, [class*=orb-]')) continue;
    const r = e.getBoundingClientRect();
    if (r.width === 0 || r.height === 0) continue;
    if ((r.right > vw + 1 || r.left < -1) && !inClip(e)) out.push(e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + '.' + String(e.className && e.className.baseVal !== undefined ? e.className.baseVal : e.className).split(' ').slice(0, 3).join('.') + ' [' + Math.round(r.left) + ',' + Math.round(r.right) + ']/' + vw);
  }
  return { vw, sw, over: sw > vw + 1, out: out.slice(0, 6), n: out.length };
}"""
def check(name, cond, extra=''):
    global ok, bad
    if cond: ok += 1; VERBOSE and print(f'  ok   {name}')
    else: bad += 1; print(f'  FAIL {name} {extra}')

def audit(p, path, label):
    r = p.goto(BASE + path, wait_until='load')
    p.wait_for_timeout(250)
    res = p.evaluate(JS)
    check(f'{label} {path} status<400', r is not None and r.status < 400, str(r and r.status))
    check(f'{label} {path} no horizontal scroll', not res['over'], f"scrollWidth {res['sw']} > {res['vw']}")
    check(f'{label} {path} nothing outside window', res['n'] == 0, '; '.join(res['out']))

with sync_playwright() as pw:
    br = pw.chromium.launch()
    for (w, h, lang) in ((360, 740, 'en'), (390, 800, 'ar'), (320, 640, 'fr')):
        c = br.new_context(viewport={'width': w, 'height': h}, is_mobile=True, has_touch=True, ignore_https_errors=True)
        p = c.new_page()
        if lang != 'en': p.goto(BASE + f'/language/{lang}?r=%2F')
        print(f'public @{w} {lang}')
        for path in PUBLIC: audit(p, path, f'{w}/{lang}')
        c.close()
    # owner panel
    for (w, h, lang) in ((360, 740, 'en'), (390, 800, 'ar')):
        c = br.new_context(viewport={'width': w, 'height': h}, is_mobile=True, has_touch=True, ignore_https_errors=True)
        p = c.new_page()
        if lang != 'en': p.goto(BASE + f'/language/{lang}?r=%2F')
        p.goto(BASE + ADMIN + '/login/')
        audit(p, ADMIN + '/login/', f'{w}/{lang} admin')
        sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
        from _staff import pw_login
        pw_login(p, BASE, ADMIN)
        signed = '/login' not in p.url
        check(f'{w}/{lang} owner signs in', signed, p.url)
        if signed:
            print(f'admin @{w} {lang}')
            for path in ADMIN_PAGES: audit(p, ADMIN + path, f'{w}/{lang} admin')
            # the panel menu must be reachable and usable on a phone
            p.goto(BASE + ADMIN + '/')
            tg = p.locator('[data-admin-nav-toggle]')
            check(f'{w}/{lang} admin menu toggle present', tg.count() == 1)
            if tg.count():
                hv = p.evaluate("(()=>{const s=document.getElementById('adminNav');const cs=getComputedStyle(s);return cs.visibility})()")
                check(f'{w}/{lang} admin menu hidden by default', hv == 'hidden', str(hv))
                tg.tap(); p.wait_for_timeout(400)
                op = p.evaluate("(()=>{const s=document.getElementById('adminNav');const r=s.getBoundingClientRect();return {o:s.classList.contains('is-open'),l:r.left,r:r.right,b:r.bottom,vh:innerHeight,vw:innerWidth}})()")
                check(f'{w}/{lang} admin menu opens', op['o'] and op['r'] <= op['vw'] + 1 and op['l'] >= -1, str(op))
                check(f'{w}/{lang} admin menu fits the screen', op['b'] <= op['vh'] + 1, str(op))
                p.keyboard.press('Escape'); p.wait_for_timeout(350)
                check(f'{w}/{lang} admin menu closes on Escape', not p.evaluate("document.getElementById('adminNav').classList.contains('is-open')"))
        c.close()
    br.close()
print(f'\nmobile: {ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

#!/usr/bin/env python3
"""Motion + chromeless checks in a real browser (Playwright). Desktop 1366 must animate as much as a phone; OS reduce-motion = calm, not dead.
Usage: python3 tests/motion.py [base_url]"""
import sys
from playwright.sync_api import sync_playwright
BASE = (next((a for a in sys.argv[1:] if a.startswith('http')), 'http://localhost:8080')).rstrip('/')
ok = bad = 0
def check(n, c, x=''):
    global ok, bad
    if c: ok += 1; print('  ok  ', n)
    else: bad += 1; print('  FAIL', n, x)

ANIM = "()=>{const n={};document.getAnimations().filter(a=>a.playState==='running').forEach(a=>{const k=a.animationName||'transition';n[k]=(n[k]||0)+1});return n}"
RV = "()=>{const a=[...document.querySelectorAll('[data-rv]')];return {n:a.length,hidden:a.filter(e=>getComputedStyle(e).opacity<0.99&&e.getBoundingClientRect().top<innerHeight).length}}"

with sync_playwright() as pw:
    br = pw.chromium.launch()
    for name, kw in (('desktop 1366', dict(viewport={'width': 1366, 'height': 800})),
                     ('phone 390', dict(viewport={'width': 390, 'height': 800}, is_mobile=True, has_touch=True))):
        print(name)
        c = br.new_context(ignore_https_errors=True, **kw); p = c.new_page(); errs = []
        p.on('pageerror', lambda e: errs.append(str(e)))
        p.goto(BASE + '/pricing/'); p.wait_for_load_state('networkidle'); p.wait_for_timeout(1500)
        a = p.evaluate(ANIM)
        check(f'{name}: hero orbs animate', a.get('orbFloat', 0) >= 3, a)
        r = p.evaluate(RV)
        check(f'{name}: sections carry data-rv', r['n'] >= 3, r)
        check(f'{name}: nothing in view is left hidden', r['hidden'] == 0, r)
        # scroll down: later sections must reveal (tall blocks too)
        h = p.evaluate('document.documentElement.scrollHeight')
        for y in range(0, h, 500):
            p.evaluate(f'window.scrollTo(0,{y})'); p.wait_for_timeout(120)
        p.wait_for_timeout(900)
        left = p.evaluate("()=>[...document.querySelectorAll('[data-rv]:not(.rv-in)')].length")
        check(f'{name}: every section revealed after scrolling', left == 0, left)
        check(f'{name}: no script errors', not errs, errs[:2])
        c.close()

    print('desktop hover layer')
    c = br.new_context(ignore_https_errors=True, viewport={'width': 1366, 'height': 800}); p = c.new_page()
    p.goto(BASE + '/'); p.wait_for_load_state('networkidle')
    card = p.locator('a.group').first; card.scroll_into_view_if_needed(); p.wait_for_timeout(900)
    card.hover(); p.wait_for_timeout(500)
    t = p.evaluate("()=>getComputedStyle(document.querySelector('a.group')).transform")
    check('card lifts on hover (mouse)', t != 'none' and t != 'matrix(1, 0, 0, 1, 0, 0)', t)
    c.close()

    print('animations are automatic: no click needed, even when the OS says reduce')
    c = br.new_context(ignore_https_errors=True, viewport={'width': 1366, 'height': 800}, reduced_motion='reduce'); p = c.new_page()
    p.goto(BASE + '/'); p.wait_for_load_state('networkidle'); p.wait_for_timeout(800)
    a = p.evaluate(ANIM)
    check('OS reduce flag set: orbs still run at full speed with no click', a.get('orbFloat', 0) >= 3, a)
    d = p.evaluate("()=>getComputedStyle(document.querySelector('.orb-a')).animationDuration")
    check('full speed by default (14s)', d == '14s', d)
    check('html carries no calm attribute by default', p.evaluate("()=>document.documentElement.getAttribute('data-motion')") is None)
    btn = p.locator('[data-motion-toggle]')
    check('footer has the Reduce animations switch (visible to everyone)', btn.count() == 1 and btn.is_visible())
    btn.click(); p.wait_for_load_state('networkidle'); p.wait_for_timeout(500)
    d = p.evaluate("()=>getComputedStyle(document.querySelector('.orb-a')).animationDuration")
    check('after the visitor chooses calm, orbs slow to 40s', d == '40s', d)
    check('choice is stored', p.evaluate("localStorage.getItem('w5d-motion')") == 'calm')
    c.close()

    print('card and element animations ported from the static template')
    c = br.new_context(ignore_https_errors=True, viewport={'width': 1366, 'height': 800}); p = c.new_page()
    p.goto(BASE + '/trust/'); p.wait_for_load_state('networkidle'); p.wait_for_timeout(600)
    n = p.evaluate("()=>({rvi:document.querySelectorAll('[data-rvi]').length,spot:document.querySelectorAll('.spot').length,shine:document.querySelectorAll('.shine').length})")
    check('cards carry data-rvi, spot and shine', n['rvi'] >= 6 and n['spot'] >= 6 and n['shine'] >= 6, n)
    p.evaluate("window.scrollTo(0, 500)"); p.wait_for_timeout(1500)
    left = p.evaluate("()=>[...document.querySelectorAll('[data-rvi]')].filter(e=>e.getBoundingClientRect().top<innerHeight&&!e.classList.contains('rv-in')).length")
    check('visible cards revealed by themselves (no footer click)', left == 0, left)
    card = p.locator('a.spot').first; card.scroll_into_view_if_needed(); p.wait_for_timeout(1200)
    box = card.bounding_box(); p.mouse.move(box['x'] + 40, box['y'] + 30); p.wait_for_timeout(250)
    mx = p.evaluate("()=>document.querySelector('a.spot').style.getPropertyValue('--mx')")
    check('pointer glow follows the mouse (--mx set)', mx.endswith('px'), mx)
    p.goto(BASE + '/'); p.wait_for_load_state('networkidle'); p.wait_for_timeout(500)
    tilt = p.evaluate("()=>{const e=document.createElement('div');e.className='tilt';document.body.appendChild(e);const r=getComputedStyle(e).transformStyle;e.remove();return r}")
    check('.tilt utility is available (preserve-3d)', tilt == 'preserve-3d', tilt)
    c.close()

    print('cookie notice')
    c = br.new_context(ignore_https_errors=True, viewport={'width': 1366, 'height': 800}); p = c.new_page(); errs = []
    p.on('pageerror', lambda e: errs.append(str(e)))
    p.goto(BASE + '/'); p.wait_for_load_state('networkidle'); p.wait_for_timeout(500)
    box = p.locator('[data-cookie-notice]')
    check('first visit: notice is shown without any click', box.is_visible())
    p.locator('[data-cookie-essential]').first.click(); p.wait_for_timeout(300)
    check('choice hides it and is stored in localStorage (not a cookie)', not box.is_visible() and p.evaluate("JSON.parse(localStorage.getItem('w5d-cookies')).v") == 1)
    p.reload(); p.wait_for_load_state('networkidle'); p.wait_for_timeout(400)
    check('stays hidden on the next page load', not p.locator('[data-cookie-notice]').is_visible())
    p.locator('footer [data-cookie-open]').first.click(); p.wait_for_timeout(300)
    check('footer Cookie settings reopens it', p.locator('[data-cookie-notice]').is_visible())
    check('no script errors', not errs, errs[:2])
    c.close()

    print('chromeless pages work in a browser')
    for name, kw in (('desktop', dict(viewport={'width': 1366, 'height': 800})), ('phone', dict(viewport={'width': 390, 'height': 800}, is_mobile=True, has_touch=True))):
        for lang in ('en', 'ar'):
            c = br.new_context(ignore_https_errors=True, **kw); c.add_cookies([{'name': 'w5d_lang', 'value': lang, 'url': BASE}]); p = c.new_page()
            p.goto(BASE + '/account/login/'); p.wait_for_load_state('networkidle')
            check(f'{name} {lang}: minibar visible, no navbar/tab bar', p.locator('.w5-minibar').is_visible() and p.locator('nav.nav-solid').count() == 0 and p.locator('.w5-tabbar').count() == 0)
            top = p.evaluate("()=>document.querySelector('input[name=identifier]').getBoundingClientRect().top")
            check(f'{name} {lang}: form starts near the top (no navbar gap)', top < 330, top)
            check(f'{name} {lang}: no horizontal scroll', p.evaluate("()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth+1"))
            p.click('.w5-minibar [data-aside-open="aside-lang"]'); p.wait_for_timeout(400)
            check(f'{name} {lang}: language panel opens from the minibar', p.locator('#aside-lang.is-open').count() == 1)
            c.close()
    br.close()
print(f'motion: {ok} passed, {bad} failed'); sys.exit(1 if bad else 0)

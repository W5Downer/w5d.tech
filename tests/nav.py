#!/usr/bin/env python3
"""Regression: public nav + owner-portal shell layout (Playwright). Usage: python3 tests/nav.py [base_url]

 - public header: no horizontal overflow at 320..1920 px; from 1280 px every desktop menu label is ONE line
   (single-line height) and the menu never overlaps the logo; below 1280 the hamburger is shown instead;
 - portal shell (desktop >= 1024 px): the mobile top bar is hidden and the sidebar sits at the left edge with the
   content next to it (the old bug: the top bar became a flex item and pushed the sidebar to the right) - en/fr/ar;
 - portal login: placeholders are human text, never template syntax; password field present."""
import os, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from playwright.sync_api import sync_playwright
from _staff import pw_login, MOUNT, ROOT

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8080').rstrip('/')
ok = bad = 0


def check(name, cond, extra=''):
    global ok, bad
    if cond: ok += 1; print(f'  ok   {name}')
    else: bad += 1; print(f'  FAIL {name} {extra}')


NAV = """() => {
  const nav = document.querySelector('header nav'); const o = {ov: document.documentElement.scrollWidth - innerWidth, h: []};
  for (const b of document.querySelectorAll('[data-mega-trigger] > button')) { const r = b.getBoundingClientRect(); if (r.width) o.h.push(Math.round(r.height)); }
  const logo = nav.querySelector('a').getBoundingClientRect(), u = nav.querySelector('ul[data-mega-host]').getBoundingClientRect();
  o.menuShown = u.width > 0; o.overlap = u.width > 0 && Math.min(u.right, logo.right) > Math.max(u.left, logo.left) + 1;
  const t = document.querySelector('[data-mobile-toggle]'); o.burger = !!t && t.getBoundingClientRect().width > 0;
  return o; }"""
SHELL = """() => {
  const a = document.querySelector('#adminNav'), m = document.querySelector('#main'), t = document.querySelector('.admin-topbar');
  const ar = a.getBoundingClientRect(), mr = m.getBoundingClientRect(); const rtl = document.dir === 'rtl' || getComputedStyle(document.body).direction === 'rtl';
  return {topbar: t ? getComputedStyle(t).display : 'none', rtl, aL: Math.round(ar.left), aR: Math.round(ar.right), mL: Math.round(mr.left), mR: Math.round(mr.right), vw: innerWidth, ov: document.documentElement.scrollWidth - innerWidth}; }"""

with sync_playwright() as pw:
    br = pw.chromium.launch()
    print('public header')
    for lang in ('en', 'fr', 'ar'):
        for w in (320, 390, 768, 1024, 1279, 1280, 1366, 1440, 1920):
            c = br.new_context(viewport={'width': w, 'height': 800}, ignore_https_errors=True, service_workers='block'); c.add_cookies([{'name': 'w5d_lang', 'value': lang, 'url': BASE}])
            p = c.new_page(); p.goto(BASE + '/'); p.wait_for_timeout(300)
            r = p.evaluate(NAV)
            check(f'{lang} {w}px: no horizontal overflow', r['ov'] <= 0, r['ov'])
            if w >= 1280:
                check(f'{lang} {w}px: desktop menu shown, labels on one line, no overlap with the logo', r['menuShown'] and r['h'] and max(r['h']) <= 44 and not r['overlap'] and not r['burger'], r)
            else:
                check(f'{lang} {w}px: hamburger instead of the desktop menu', r['burger'] and not r['menuShown'], r)
            c.close()
    print('portal login + shell')
    for lang in ('en', 'fr', 'ar'):
        for w in (1024, 1366, 1920):
            c = br.new_context(viewport={'width': w, 'height': 800}, ignore_https_errors=True, service_workers='block'); c.add_cookies([{'name': 'w5d_lang', 'value': lang, 'url': BASE}])
            p = c.new_page()
            if w == 1024:
                p.goto(BASE + MOUNT + '/login/')
                html = p.content()
                ph = p.evaluate("() => [...document.querySelectorAll('input')].map(i => i.placeholder).join('|')")
                check(f'{lang}: login placeholders are plain text', '{{' not in ph and 'StaffAuth' not in ph and 'W5D\\' not in ph, ph)
                check(f'{lang}: login has password field + code alternative', p.locator('input[type=password]').count() == 1 and p.locator('input[name=do][value=request]').count() == 1)
            if not pw_login(p, BASE, MOUNT, None, ROOT):
                check(f'{lang} {w}: sign in', False); c.close(); continue
            p.goto(BASE + MOUNT + '/'); p.wait_for_timeout(300)
            r = p.evaluate(SHELL)
            check(f'{lang} {w}px: mobile top bar hidden on desktop', r['topbar'] == 'none', r)
            side_ok = (r['aR'] >= r['vw'] - 1 and r['mR'] <= r['aL'] + 1) if r['rtl'] else (r['aL'] <= 1 and r['mL'] >= r['aR'] - 1)
            check(f'{lang} {w}px: sidebar on the start edge, content beside it, no overflow', side_ok and r['ov'] <= 0, r)
            c.close()
    br.close()
print(f'\nnav: {ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

#!/usr/bin/env python3
"""Browser UI checks (Playwright + chromium). Usage: python3 tests/ui.py [base_url]
Header share/user/language icons, toggled asides (guest + signed-in), mega menu (full width, opaque, animated), mobile menu, RTL."""
import os, re, shutil, sys, time
from playwright.sync_api import sync_playwright

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8080').rstrip('/')
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
shutil.rmtree(os.path.join(ROOT, 'storage/cache/rl'), ignore_errors=True)
RUN = str(int(time.time()))
ok = bad = 0
def check(name, cond, extra=''):
    global ok, bad
    if cond: ok += 1; print(f'  ok   {name}')
    else: bad += 1; print(f'  FAIL {name} {extra}')

def vis(page, sel): return page.evaluate("(s)=>{const e=document.querySelector(s);if(!e)return false;const r=e.getBoundingClientRect();const cs=getComputedStyle(e);return r.width>0&&r.height>0&&cs.visibility!=='hidden'&&cs.display!=='none'}", sel)
def aside_open(page, id_): return page.evaluate("(i)=>{const e=document.getElementById(i);return !!e&&e.getAttribute('aria-hidden')==='false'}", id_)

with sync_playwright() as pw:
    br = pw.chromium.launch()
    errors = []
    def newctx(**kw):
        kw.setdefault('ignore_https_errors', True); c = br.new_context(**kw); p = c.new_page()
        p.on('pageerror', lambda e: errors.append(str(e)))
        p.on('console', lambda m: errors.append(m.text) if m.type == 'error' else None)
        return c, p

    # ---------------- desktop guest ----------------
    print('desktop, guest, en')
    c, p = newctx(viewport={'width': 1366, 'height': 800})
    p.goto(BASE + '/'); p.wait_for_load_state('networkidle')
    check('user icon present', p.locator('[data-aside-open="aside-user"]').count() == 1)
    check('share icon present', p.locator('[data-aside-open="aside-share"]').count() == 1)
    check('language icon present', p.locator('[data-aside-open="aside-lang"]').count() == 1)
    # order: share, user, language
    order = p.evaluate("[...document.querySelectorAll('header [data-aside-open]')].map(b=>b.dataset.asideOpen)")
    check('icons side by side in header', set(order) == {'aside-share', 'aside-user', 'aside-lang'}, str(order))
    for name, aid in (('language', 'aside-lang'), ('user', 'aside-user'), ('share', 'aside-share')):
        p.click(f'[data-aside-open="{aid}"]'); p.wait_for_timeout(450)
        check(f'{name} aside opens', aside_open(p, aid))
        check(f'{name} aside visible', vis(p, f'#{aid}'))
        check(f'{name} trigger aria-expanded', p.get_attribute(f'[data-aside-open="{aid}"]', 'aria-expanded') == 'true')
        p.keyboard.press('Escape'); p.wait_for_timeout(450)
        check(f'{name} aside closes on Escape', not aside_open(p, aid))
        p.click(f'[data-aside-open="{aid}"]'); p.wait_for_timeout(350)
        p.click(f'#{aid} [data-aside-close]'); p.wait_for_timeout(450)
        check(f'{name} aside closes with X', not aside_open(p, aid))
    # opening one closes the other
    p.click('[data-aside-open="aside-user"]'); p.wait_for_timeout(350)
    p.evaluate("document.querySelector('[data-aside-open=\"aside-lang\"]').click()"); p.wait_for_timeout(450)
    check('opening language closes user aside', aside_open(p, 'aside-lang') and not aside_open(p, 'aside-user'))
    p.keyboard.press('Escape'); p.wait_for_timeout(300)
    # guest content
    p.click('[data-aside-open="aside-user"]'); p.wait_for_timeout(450)
    u = p.inner_text('#aside-user')
    check('guest aside has sign-in link', p.locator('#aside-user a[href*="/account/login/"]').count() >= 1)
    check('guest aside has register link', p.locator('#aside-user a[href*="/account/register/"]').count() >= 1)
    check('guest aside lists audience pages', p.locator('#aside-user a[href*="/for/"]').count() >= 3)
    check('guest aside has no dashboard menu', p.locator('#aside-user form[action*="/account/logout/"]').count() == 0)
    p.keyboard.press('Escape'); p.wait_for_timeout(300)
    # share aside
    p.click('[data-aside-open="aside-share"]'); p.wait_for_timeout(450)
    check('share aside has network links', p.locator('#aside-share a[href^="https://"]').count() >= 4)
    check('share links open safely', p.evaluate("[...document.querySelectorAll('#aside-share a[href^=\"https://\"]')].every(a=>/noopener/.test(a.rel))"))
    p.keyboard.press('Escape'); p.wait_for_timeout(300)

    # mega menu
    print('mega menu')
    trig = p.locator('[data-mega-trigger]').first
    trig.hover(); p.wait_for_timeout(500)
    m = p.evaluate("""()=>{const t=document.querySelector('[data-mega-trigger]');const e=t.querySelector('[data-mega-panel]');const r=e.getBoundingClientRect();const s=e.querySelector('.mega-surface')||e;const cs=getComputedStyle(s);return {x:r.left,w:r.width,vw:innerWidth,bg:cs.backgroundColor,open:e.classList.contains('is-open'),op:getComputedStyle(e).opacity,vis:getComputedStyle(e).visibility}}""")
    check('mega panel opens on hover', m['open'] and m['vis'] == 'visible', str(m))
    check('mega panel is full viewport width', abs(m['x']) < 2 and abs(m['w'] - m['vw']) < 3, str(m))
    alpha = re.findall(r'[\d.]+', m['bg'])
    check('mega surface is 100% opaque', len(alpha) == 3 or (len(alpha) == 4 and float(alpha[3]) == 1.0), m['bg'])
    check('mega panel fully shown (opacity 1)', float(m['op']) == 1.0, str(m['op']))
    p.mouse.move(600, 600); p.wait_for_timeout(600)
    check('mega panel closes when pointer leaves', not p.evaluate("document.querySelector('[data-mega-panel]').classList.contains('is-open')"))
    nav = p.evaluate("(()=>{const n=document.querySelector('header nav')||document.querySelector('header');return getComputedStyle(n).backgroundColor})()")
    a = re.findall(r'[\d.]+', nav)
    check('nav bar background opaque', len(a) == 3 or (len(a) == 4 and float(a[3]) == 1.0), nav)
    c.close()

    # ---------------- desktop RTL ----------------
    print('desktop, guest, ar (RTL)')
    c, p = newctx(viewport={'width': 1366, 'height': 800})
    p.goto(BASE + '/language/ar?r=%2F'); p.wait_for_load_state('networkidle')
    check('/language/ar lands on home in Arabic', p.evaluate("document.documentElement.lang") == 'ar' and p.evaluate("document.documentElement.dir") == 'rtl')
    p.click('[data-aside-open="aside-user"]'); p.wait_for_timeout(450)
    box = p.evaluate("(()=>{const r=document.getElementById('aside-user').getBoundingClientRect();return {l:r.left,r:r.right,vw:innerWidth}})()")
    check('ar user aside opens and is on screen', aside_open(p, 'aside-user') and box['l'] >= -2 and box['r'] <= box['vw'] + 2, str(box))
    p.keyboard.press('Escape'); p.wait_for_timeout(300)
    c.close()

    # ---------------- register + sign in, then authenticated aside ----------------
    print('authenticated user aside')
    c, p = newctx(viewport={'width': 1366, 'height': 800})
    email = f'ui{RUN}@real.org'; pw_ = 'Str0ngPass-' + RUN
    p.goto(BASE + '/account/register/'); p.wait_for_load_state('networkidle')
    fields = p.evaluate("[...document.querySelectorAll('form input, form select')].map(e=>e.name+':'+e.type)")
    p.fill('input[name="name"]', 'UI Tester') if p.locator('input[name="name"]').count() else None
    p.fill('input[name="email"]', email)
    p.fill('input[name="password"]', pw_)
    if p.locator('input[name="password2"]').count(): p.fill('input[name="password2"]', pw_)
    for cb in p.locator('main form input[type=checkbox]').all():
        try: cb.check()
        except Exception: pass
    p.click('main form button[type=submit]'); p.wait_for_load_state('networkidle')
    # verify via the mail log link
    import glob
    txt = ''.join(open(f, encoding='utf-8', errors='replace').read() for f in sorted(glob.glob(os.path.join(ROOT, 'storage/logs/mail-*.log'))))
    blocks = [b for b in txt.split('] [') if 'to=' + email in b and '/account/verify/' in b]
    check('verification mail logged', bool(blocks), str(fields))
    if blocks:
        tok = re.search(r'/account/verify/([A-Za-z0-9_.\-]+)/', blocks[-1]).group(1)
        p.goto(BASE + f'/account/verify/{tok}/'); p.wait_for_load_state('networkidle')
        p.goto(BASE + '/account/login/'); p.wait_for_load_state('networkidle')
        p.fill('input[name="identifier"]', email); p.fill('input[name="password"]', pw_)
        p.click('main form button[type=submit]'); p.wait_for_load_state('networkidle')
        check('signed in (dashboard)', '/account' in p.url, p.url)
        p.goto(BASE + '/'); p.wait_for_load_state('networkidle')
        check('user icon shows signed-in state', p.locator('header [data-aside-open="aside-user"] span').count() >= 1 or 'text-brand' in (p.get_attribute('[data-aside-open="aside-user"]', 'class') or ''))
        p.click('[data-aside-open="aside-user"]'); p.wait_for_timeout(450)
        check('auth aside opens', aside_open(p, 'aside-user'))
        check('auth aside shows the e-mail', email in p.inner_text('#aside-user'))
        check('auth aside has dashboard link', p.locator('#aside-user a[href*="/account"]').count() >= 3)
        check('auth aside has sign-out form with csrf', p.locator('#aside-user form[action*="/account/logout/"] input[name="_csrf"]').count() == 1)
        check('auth aside has no guest sign-in form', p.locator('#aside-user a[href*="/account/register/"]').count() == 0)
        p.keyboard.press('Escape'); p.wait_for_timeout(300)
        # dashboard UX
        p.goto(BASE + '/account/'); p.wait_for_load_state('networkidle')
        check('dashboard renders heading', p.locator('h1').count() >= 1)
        check('dashboard noindex', 'noindex' in (p.get_attribute('meta[name=robots]', 'content') or ''))
        # sign out through the aside
        p.click('[data-aside-open="aside-user"]'); p.wait_for_timeout(450)
        p.click('#aside-user form[action*="/account/logout/"] button'); p.wait_for_load_state('networkidle')
        p.goto(BASE + '/'); p.wait_for_load_state('networkidle')
        p.click('[data-aside-open="aside-user"]'); p.wait_for_timeout(450)
        check('after sign-out aside is guest again', p.locator('#aside-user a[href*="/account/login/"]').count() >= 1)
    c.close()

    # ---------------- mobile ----------------
    print('mobile')
    c, p = newctx(viewport={'width': 390, 'height': 800}, is_mobile=True, has_touch=True)
    p.goto(BASE + '/'); p.wait_for_load_state('networkidle')
    check('mobile: user icon reachable', vis(p, 'header [data-aside-open="aside-user"]'))
    p.tap('header [data-aside-open="aside-user"]'); p.wait_for_timeout(500)
    box = p.evaluate("(()=>{const r=document.getElementById('aside-user').getBoundingClientRect();return {l:r.left,r:r.right,vw:innerWidth}})()")
    check('mobile: user aside opens within viewport', aside_open(p, 'aside-user') and box['r'] <= box['vw'] + 2 and box['l'] >= -2, str(box))
    p.keyboard.press('Escape'); p.wait_for_timeout(300)
    toggle = p.locator('#mobileToggle, [data-mobile-toggle], button[aria-controls="mobileMenu"]').first
    toggle.tap(); p.wait_for_timeout(500)
    check('mobile menu opens', p.evaluate("document.getElementById('mobileMenu').classList.contains('is-open')"))
    mb = p.evaluate("(()=>{const e=document.querySelector('#mobileMenu');const cs=getComputedStyle(e);return cs.backgroundColor})()")
    a = re.findall(r'[\d.]+', mb)
    check('mobile menu opaque', len(a) == 3 or (len(a) == 4 and float(a[3]) == 1.0), mb)
    sw = p.evaluate("document.documentElement.scrollWidth <= innerWidth + 1")
    check('mobile: no horizontal overflow', sw)
    c.close()

    # ---------------- audience pages, head ----------------
    print('audience pages + head')
    c, p = newctx(viewport={'width': 1280, 'height': 800})
    for aud in ('owner', 'admin', 'leader', 'member', 'investor', 'customer', 'user'):
        r = p.goto(BASE + f'/for/{aud}/')
        check(f'/for/{aud}/ 200 + single h1', r.status == 200 and p.locator('h1').count() == 1)
    p.goto(BASE + '/')
    import json
    ld = p.evaluate("[...document.querySelectorAll('script[type=\"application/ld+json\"]')].map(s=>s.textContent)")
    try: [json.loads(x) for x in ld]; good = bool(ld)
    except Exception: good = False
    check('JSON-LD parses', good)
    for sel in ('meta[property="og:title"]', 'meta[property="og:image"]', 'meta[name="twitter:card"]', 'link[rel="canonical"]', 'link[rel="alternate"][hreflang]'):
        check(f'head has {sel}', p.locator(sel).count() >= 1)
    c.close()
    br.close()

real = [e for e in errors if not re.search(r'favicon|Failed to load resource.*(404|400)', e)]
check('no JS/console errors', not real, '; '.join(real[:4]))
print(f'\nui: {ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

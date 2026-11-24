#!/usr/bin/env python3
"""Browser checks for the UX layer (Playwright + chromium). Usage: python3 tests/browser.py [base_url]
Auth modals (open, close, Esc, focus return, errors, success, register, RTL, phone), quick-find, bottom tab bar, CRUD on a phone."""
import os, re, shutil, sys, time
from playwright.sync_api import sync_playwright

BASE = (next((a for a in sys.argv[1:] if a.startswith('http')), 'http://localhost:8080')).rstrip('/')
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, 'tests'))
from _acct import verify_token
shutil.rmtree(os.path.join(ROOT, 'storage/cache/rl'), ignore_errors=True)
OWNER_USER = os.environ.get('W5D_OWNER_USER', 'owner'); OWNER_PASS = os.environ.get('W5D_OWNER_PASS', 'w5d-demo-owner')
ADMIN = '/' + os.environ.get('W5D_ADMIN_PATH', 'panel-x9z').strip('/')
RUN = str(int(time.time() * 1000))[-9:]
ok = bad = 0
def check(name, cond, extra=''):
    global ok, bad
    if cond: ok += 1; print(f'  ok   {name}')
    else: bad += 1; print(f'  FAIL {name} {extra}')

def open_login(p):
    """Open the sign-in modal from whichever entry point the header shows at this width: the text link (tablet) or,
    on desktop where the bar is kept uncrowded, the account icon -> panel -> Sign in button."""
    link = p.locator('header a[data-auth-open="login"]')
    if link.count() and link.first.is_visible():
        link.first.click()
    else:
        p.click('header [data-aside-open="aside-user"]'); p.wait_for_timeout(450)
        p.click('#aside-user a[data-auth-open="login"]')
    p.wait_for_selector('#authDialog[open] form[data-auth-form]')

def dlg_open(p, sel): return p.evaluate("(s)=>{const d=document.querySelector(s);return !!d&&d.open}", sel)
def fits(p): return p.evaluate("()=>{const d=document.documentElement;return d.scrollWidth<=d.clientWidth+1}")

with sync_playwright() as pw:
    br = pw.chromium.launch()
    errors = []
    def newctx(**kw):
        kw.setdefault('ignore_https_errors', True)
        kw.setdefault('service_workers', 'block')   # the portal worker is covered by tests/pwa.py; a self-signed cert would make its registration log noise here
        c = br.new_context(**kw); p = c.new_page()
        p.on('pageerror', lambda e: errors.append(str(e)))
        p.on('console', lambda m: errors.append(m.text) if m.type == 'error' and 'Failed to load resource' not in m.text else None)
        return c, p

    # ---------------- auth modal, desktop ----------------
    print('auth modal, desktop en')
    c, p = newctx(viewport={'width': 1366, 'height': 800})
    p.goto(BASE + '/'); p.wait_for_load_state('networkidle')
    check('dialog closed at load', not dlg_open(p, '#authDialog'))
    open_login(p)
    check('login modal opens with form', p.locator('#authDialog input[name=identifier]').count() == 1)
    check('URL unchanged (no navigation)', p.url.rstrip('/') == BASE)
    check('focus lands in dialog', p.evaluate("()=>document.getElementById('authDialog').contains(document.activeElement)"))
    p.keyboard.press('Escape'); p.wait_for_timeout(150)
    check('Esc closes', not dlg_open(p, '#authDialog'))
    open_login(p)
    p.click('[data-auth-close]'); p.wait_for_timeout(150)
    check('close button closes', not dlg_open(p, '#authDialog'))
    # wrong credentials
    open_login(p)
    p.fill('#am-identifier', 'nobody@mail.test'); p.fill('#am-password', 'wrong-password-1')
    p.click('#authDialog [data-submit]'); p.wait_for_selector('#authDialog [data-form-error]:not(.hidden), #authDialog [data-err]:not(:empty)', timeout=8000)
    check('wrong login shows an error, stays open', dlg_open(p, '#authDialog'))
    # switch to register inside the modal
    reg = p.locator('#authDialog [data-auth-open="register"]')
    check('register link inside login modal', reg.count() >= 1)
    reg.first.click(); p.wait_for_selector('#authDialog form [name=password2]')
    # empty/invalid register
    p.fill('#am-name', 'B'); p.fill('#am-email', 'not-an-email'); p.fill('#am-password', 'short'); p.fill('#am-password2', 'other')
    p.click('#authDialog [data-submit]'); p.wait_for_timeout(1200)
    errs = p.evaluate("()=>[...document.querySelectorAll('#authDialog [data-err]')].filter(e=>e.textContent.trim()).length+(document.querySelector('#authDialog [data-form-error]:not(.hidden)')?1:0)")
    check('register validation errors shown inline', errs >= 1, str(errs))
    check('still open after invalid register', dlg_open(p, '#authDialog'))
    # valid register
    email = f'b{RUN}@mail.test'; pw_ = 'Str0ngPass-' + RUN
    p.fill('#am-name', 'Browser Tester'); p.fill('#am-email', email); p.fill('#am-password', pw_); p.fill('#am-password2', pw_)
    p.check('#am-accept'); p.click('#authDialog [data-submit]')
    p.wait_for_selector('#authDialog [data-form-ok]:not(.hidden), #authDialog [data-err]:not(:empty), #authDialog [data-form-error]:not(.hidden)', timeout=8000)
    check('register succeeds (confirmation message)', p.locator('#authDialog [data-form-ok]:not(.hidden)').count() == 1,
          p.evaluate("()=>document.getElementById('authDialog').innerText.slice(0,300)"))
    tok = None
    for _ in range(10):
        tok = verify_token(ROOT, email)
        if tok: break
        time.sleep(0.3)
    check('verification mail logged', bool(tok))
    if tok:
        p.goto(BASE + f'/account/verify/{tok}/')
        p.goto(BASE + '/'); p.wait_for_load_state('networkidle')
        open_login(p)
        p.fill('#am-identifier', email); p.fill('#am-password', pw_); p.click('#authDialog [data-submit]')
        p.wait_for_function("()=>!document.getElementById('authDialog')", timeout=10000); p.wait_for_load_state('networkidle')
        check('modal login signs in (header shows account state)', p.locator('header [data-auth-open="login"]').count() == 0 and p.locator('#authDialog').count() == 0)
    c.close()

    # ---------------- gated page opens the modal path + next ----------------
    print('docs gate -> login link keeps next')
    c, p = newctx(viewport={'width': 1366, 'height': 800})
    p.goto(BASE + '/developers/'); p.wait_for_load_state('networkidle')
    check('guest on private docs is sent to sign-in', '/account/login' in p.url and 'next=' in p.url, p.url)
    c.close()

    # ---------------- quick find ----------------
    print('quick find')
    c, p = newctx(viewport={'width': 1366, 'height': 800})
    p.goto(BASE + '/'); p.wait_for_load_state('networkidle')
    p.keyboard.press('/'); p.wait_for_selector('#quickDialog[open]')
    check('"/" opens quick-find', True)
    p.keyboard.type('eligib'); p.wait_for_timeout(150)
    shown = p.evaluate("()=>[...document.querySelectorAll('#quickDialog [data-quick-item]')].filter(l=>!l.classList.contains('hidden')).length")
    check('typing filters the list', 0 < shown < 40, str(shown))
    p.keyboard.press('Enter')
    try: p.wait_for_url(lambda u: u.rstrip('/') != BASE, timeout=8000)
    except Exception: pass
    p.wait_for_load_state('load')
    check('Enter follows first result', p.url.rstrip('/') != BASE, p.url)
    p.goto(BASE + '/'); p.wait_for_load_state('networkidle'); p.keyboard.press('Control+k'); p.wait_for_selector('#quickDialog[open]')
    p.wait_for_function("()=>document.activeElement && document.activeElement.id==='quickInput'")
    p.keyboard.type('zzzzqq'); p.wait_for_timeout(150)
    check('empty state shown', p.locator('[data-quick-empty]:not(.hidden)').count() == 1)
    p.keyboard.press('Escape'); p.wait_for_timeout(150)
    check('Esc closes quick-find', not dlg_open(p, '#quickDialog'))
    check('home path chooser rendered', p.locator('[data-path-chooser], .path-chooser').count() >= 1 or p.locator('a[href$="/checkout/"]').count() >= 1)
    c.close()

    # ---------------- phone: tab bar + modal (en, ar) ----------------
    for lang, vw in (('en', 360), ('ar', 390), ('fr', 320)):
        print(f'phone {vw} {lang}: tab bar + modal')
        c, p = newctx(viewport={'width': vw, 'height': 740}, is_mobile=True, has_touch=True)
        if lang != 'en': p.goto(BASE + f'/language/{lang}?r=%2F')
        p.goto(BASE + '/'); p.wait_for_load_state('networkidle')
        check(f'{lang} tab bar visible', p.locator('.w5-tabbar').is_visible())
        n = p.locator('.w5-tabbar > *').count()
        check(f'{lang} five tabs', n == 5, str(n))
        box = p.evaluate("()=>{const r=document.querySelector('.w5-tabbar').getBoundingClientRect();return [r.bottom,innerHeight,r.left,r.right,innerWidth]}")
        check(f'{lang} tab bar pinned bottom, full width', abs(box[0] - box[1]) < 2 and box[2] <= 1 and box[3] >= box[4] - 1, str(box))
        tiny = p.evaluate("()=>[...document.querySelectorAll('.w5-tabbar>*')].filter(e=>{const r=e.getBoundingClientRect();return r.height<44||r.width<44}).length")
        check(f'{lang} tab targets >= 44px', tiny == 0, str(tiny))
        p.click('.w5-tabbar [data-auth-open="login"]'); p.wait_for_selector('#authDialog[open] form[data-auth-form]')
        r = p.evaluate("()=>{const c=document.querySelector('#authDialog .w5-dialog-card').getBoundingClientRect();return [c.left,c.right,innerWidth]}")
        check(f'{lang} modal fits phone width', r[0] >= 0 and r[1] <= r[2] + 1, str(r))
        check(f'{lang} no horizontal scroll with modal open', fits(p))
        p.keyboard.press('Escape'); p.wait_for_timeout(150)
        p.click('.w5-tabbar [data-quick-open]'); p.wait_for_selector('#quickDialog[open]')
        check(f'{lang} tab "search" opens quick-find', True)
        p.keyboard.press('Escape'); p.wait_for_timeout(150)
        p.click('.w5-tabbar [data-tab-menu]'); p.wait_for_timeout(500)
        check(f'{lang} tab "menu" opens the menu panel', p.evaluate("()=>document.getElementById('mobileMenu').classList.contains('is-open')"))
        c.close()

    # ---------------- desktop: tab bar hidden ----------------
    c, p = newctx(viewport={'width': 1280, 'height': 800})
    p.goto(BASE + '/'); p.wait_for_load_state('networkidle')
    check('tab bar hidden on desktop', not p.locator('.w5-tabbar').is_visible())
    c.close()

    # ---------------- owner CRUD on a phone ----------------
    print('owner CRUD on a phone')
    c, p = newctx(viewport={'width': 360, 'height': 740}, is_mobile=True, has_touch=True)
    sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
    from _staff import pw_login
    pw_login(p, BASE, ADMIN)
    check('owner signed in on phone', '/login' not in p.url, p.url)
    for path in ('/campaigns/', '/campaigns/new/', '/teams/', '/clients/new/', '/support/', '/payroll/', '/users/', '/api-docs/'):
        r = p.goto(BASE + ADMIN + path); p.wait_for_timeout(200)
        check(f'{path} loads', r.status < 400, str(r.status))
        check(f'{path} no horizontal scroll', fits(p))
    p.goto(BASE + ADMIN + '/campaigns/new/')
    title = f'Browser campaign {RUN}'
    f = p.locator('form[action*="/save"]'); check('create form present', f.count() >= 1)
    names = p.evaluate("()=>[...document.querySelectorAll('form[action*=\"/save\"] [name]')].map(e=>e.name)")
    check('form has CSRF + fields', '_csrf' in names and len(names) >= 3, str(names))
    c.close()

    print('\nconsole/page errors:', len(errors))
    for e in errors[:8]: print('   ', e[:200])
    check('no JS errors', len(errors) == 0)
    br.close()

print(f'\n{ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

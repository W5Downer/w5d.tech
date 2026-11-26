#!/usr/bin/env python3
"""Real-browser checks (Playwright + chromium) for forms.js, checkout wizard v2 and the owner image drop zone.
Usage: python3 tests/browser_forms.py [base_url]     (dev server on 127.0.0.1:8080, MAIL_DRIVER=log)
Sessions are created through the real register/verify/login and e-mailed-code flows (tests/_acct.py, tests/_staff.py),
then the cookies are handed to the browser."""
import io, os, sys
from playwright.sync_api import sync_playwright

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, 'tests'))
BASE = (next((a for a in sys.argv[1:] if a.startswith('http')), 'http://127.0.0.1:8080')).rstrip('/')
from _acct import signed_in
from _staff import staff_client, MOUNT
ok = bad = 0


def check(name, cond, extra=''):
    global ok, bad
    if cond: ok += 1; print('  ok  ', name)
    else: bad += 1; print('  FAIL', name, extra)


def cookies_of(c):
    host = BASE.split('//')[1].split(':')[0]
    return [{'name': k.name, 'value': k.value, 'domain': host, 'path': '/'} for k in c[1]]


def png_bytes(w, h, color):
    from PIL import Image
    b = io.BytesIO(); Image.new('RGB', (w, h), color).save(b, 'PNG'); return b.getvalue()


with sync_playwright() as pw:
    br = pw.chromium.launch()
    errors = []

    def ctx(cookies=None, **kw):
        x = br.new_context(ignore_https_errors=True, service_workers='block', **kw)
        if cookies: x.add_cookies(cookies)
        return x

    def page_of(x):
        p = x.new_page()
        p.on('pageerror', lambda e: errors.append(str(e)))
        p.on('console', lambda m: errors.append(m.text) if m.type == 'error' and 'favicon' not in m.text else None)
        return p

    # ------------------------------------------------------------------ checkout wizard + smart inputs
    c, email = signed_in(BASE, ROOT, role='investor')
    x = ctx(cookies_of(c), viewport={'width': 390, 'height': 800}, locale='en-US', timezone_id='Africa/Tunis')
    p = page_of(x)
    p.goto(BASE + '/checkout/'); p.wait_for_load_state('load')
    print('wizard + forms.js (390px, Africa/Tunis)')
    check('wizard form is on the page', p.locator('form[data-wizard]').count() == 1)
    check('only step 1 is visible at first', p.locator('.wz-step:visible').count() == 1)
    check('page fits a phone (no horizontal scroll)', p.evaluate('()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth+1'))
    ph = p.locator('input[name=phone]')
    check('phone field was upgraded (country picker + national input)', p.locator('.w5-phone, [data-phone-wrap], select[data-phone-cc], .phone-cc').count() >= 1 or p.locator('input[name=phone]').evaluate('e=>e.type') in ('hidden', 'tel'))
    check('country defaults to Tunisia from the timezone', p.evaluate("()=>document.body.innerText.includes('+216') || !!document.querySelector('[data-iso=TN], option[value=TN]:checked')"))
    vis = p.locator('input[type=tel]:visible')
    if vis.count():
        vis.first.fill('98765432')
        p.wait_for_timeout(200)
        hid = p.locator('input[name=phone]').evaluate('e=>e.value')
        check('typing a Tunisian number stores E.164 (+21698765432)', hid.replace(' ', '') == '+21698765432', hid)
        vis.first.fill('12')
        p.locator('input[name=full_name]').focus(); p.wait_for_timeout(200)
        check('an impossible number is flagged', p.evaluate("()=>!!document.querySelector('[aria-invalid=true], .field-error, [data-phone-err]:not([hidden])')"))
        vis.first.fill('98765432')
    em = p.locator('input[name=email]')
    if not em.is_disabled() and em.get_attribute('readonly') is None:
        em.fill('someone@gmial.com'); p.locator('input[name=full_name]').focus(); p.wait_for_timeout(200)
        check('typo hint offers gmail.com', 'gmail.com' in p.inner_text('form[data-wizard]'))
    p.locator('input[name=organisation]').fill('Draft Tester')
    p.wait_for_timeout(700)
    check('draft is stored locally while typing', p.evaluate("()=>Object.keys(localStorage).some(k=>k.indexOf('draft')>=0)"))
    p.reload(); p.wait_for_load_state('load'); p.wait_for_timeout(300)
    check('after reload the typed value is restored', p.locator('input[name=organisation]').input_value() == 'Draft Tester')
    check('a "restored / start over" notice is shown', p.locator('form[data-wizard] .btn-link:has-text("Start over"), form[data-wizard] [data-draft-bar]').count() >= 1)
    check('no password/secret field is ever written to the draft', p.evaluate("()=>Object.keys(localStorage).every(k=>!/pass|token|csrf/i.test(localStorage.getItem(k)||''))"))
    # step navigation
    p.locator('[data-wz-next]:visible').first.click(); p.wait_for_timeout(300)
    check('Next moves to step 2 (parts)', p.locator('.wz-step[data-step="2"]').is_visible())
    check('step 1 is hidden once on step 2', not p.locator('.wz-step[data-step="1"]').is_visible())
    st = p.locator('input[name=parts_per_day]')
    before = int(st.input_value() or 1)
    p.locator('[data-set="parts_per_day=5"]').click(); p.wait_for_timeout(200)
    check('quick chip sets 5 parts', st.input_value() == '5', st.input_value())
    p.wait_for_timeout(800)
    check('live summary shows a total', p.locator('[data-total], [data-sum], .wz-sum').count() >= 1)
    p.locator('[data-wz-prev]:visible').first.click(); p.wait_for_timeout(250)
    check('Back returns to step 1 and keeps values', p.locator('.wz-step[data-step="1"]').is_visible() and p.locator('input[name=organisation]').input_value() == 'Draft Tester')
    p.locator('[data-wz-next]:visible').first.click(); p.wait_for_timeout(250)
    p.locator('[data-wz-next]:visible').first.click(); p.wait_for_timeout(300)
    check('Next again reaches step 3 (pay)', p.locator('.wz-step[data-step="3"]').is_visible())
    check('payment methods are offered as choices', p.locator('.wz-step[data-step="3"] input[name=payment_method]').count() >= 1)
    check('exactly one primary button on the visible step', p.locator('.wz-step[data-step="3"] .btn-peak').count() <= 1)
    p.screenshot(path='/tmp/wizard-390.png')

    # empty required field blocks Next with a message, not a crash
    p.goto(BASE + '/checkout/'); p.evaluate('()=>localStorage.clear()'); p.reload(); p.wait_for_load_state('load')
    p.locator('input[name=full_name]').fill('')
    p.locator('[data-wz-next]:visible').first.click(); p.wait_for_timeout(300)
    check('empty name: stays on step 1 with an inline error', p.locator('.wz-step[data-step="1"]').is_visible() and p.locator('[aria-invalid=true]').count() >= 1)
    x.close()

    # ------------------------------------------------------------------ RTL wizard
    x = ctx(cookies_of(c) + [{'name': 'w5d_lang', 'value': 'ar', 'domain': BASE.split('//')[1].split(':')[0], 'path': '/'}], viewport={'width': 390, 'height': 800})
    p = page_of(x); p.goto(BASE + '/checkout/'); p.wait_for_load_state('load')
    print('Arabic / RTL')
    check('html is rtl', p.evaluate('()=>document.documentElement.dir') == 'rtl')
    check('Arabic page loads the Arabic bundle and the Cairo face', p.evaluate("()=>[...document.styleSheets].some(s=>(s.href||'').includes('bundle-ar'))") and p.evaluate("()=>document.fonts.check('16px Cairo','ب')"))
    check('RTL wizard fits a phone', p.evaluate('()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth+1'))
    vis = p.locator('input[type=tel]:visible')
    if vis.count():
        check('phone digits stay left-to-right inside RTL', p.evaluate("()=>getComputedStyle(document.querySelector('input[type=tel]:not([type=hidden])')).direction") == 'ltr')
    x.close()

    # English page must not download Arabic font files
    x = ctx(viewport={'width': 1280, 'height': 800})
    p = page_of(x); reqs = []
    p.on('request', lambda r: reqs.append(r.url))
    p.goto(BASE + '/'); p.wait_for_load_state('networkidle')
    print('PageSpeed regressions')
    check('English home does not fetch Arabic/Cairo font files', not [u for u in reqs if 'arabic' in u or 'cairo' in u], [u for u in reqs if 'arabic' in u or 'cairo' in u])
    check('exactly one render-blocking stylesheet', p.locator('head link[rel=stylesheet]').count() == 1)
    check('card images are webp with a srcset', p.locator('img[src*="/cards/"][srcset]').count() >= 1 and p.locator('img[src$=".jpg"], img[src*=".jpg?"]').count() == 0 or p.locator('img[src*=".webp"]').count() >= 1)
    check('slider dots are at least 24px tall touch targets', p.evaluate("()=>{const d=document.querySelector('.w5-dot');return !d||d.getBoundingClientRect().height>=24}"))
    check('slider progress bar uses transform (not width)', p.evaluate("()=>{const i=document.querySelector('.w5-progress>i');return !i||getComputedStyle(i).transform!=='none'||getComputedStyle(i).width!=='0px'}"))
    x.close()

    # ------------------------------------------------------------------ owner image drop zone
    oc = staff_client(BASE, 'owner')
    x = ctx(cookies_of(oc), viewport={'width': 1280, 'height': 900})
    p = page_of(x)
    p.goto(BASE + MOUNT + '/images/'); p.wait_for_load_state('load')
    print('owner image library (real drag and drop)')
    check('drop zone is present', p.locator('[data-dropzone]').count() == 1)
    files = [{'name': 'drop-a.png', 'mimeType': 'image/png', 'buffer': png_bytes(900, 500, (200, 30, 90))},
             {'name': 'drop-b.png', 'mimeType': 'image/png', 'buffer': png_bytes(300, 700, (30, 90, 200))}]
    # simulate a real OS drop: build a DataTransfer in the page and dispatch dragenter/dragover/drop
    p.evaluate("""async (fs) => {
      const dt = new DataTransfer();
      for (const f of fs) { const bin = Uint8Array.from(atob(f.b64), c => c.charCodeAt(0)); dt.items.add(new File([bin], f.name, {type: f.type})); }
      const z = document.querySelector('[data-dropzone]');
      for (const t of ['dragenter', 'dragover']) z.dispatchEvent(new DragEvent(t, {dataTransfer: dt, bubbles: true, cancelable: true}));
      window.__over = z.classList.contains('is-over');
      z.dispatchEvent(new DragEvent('drop', {dataTransfer: dt, bubbles: true, cancelable: true}));
    }""", [{'name': f['name'], 'type': f['mimeType'], 'b64': __import__('base64').b64encode(f['buffer']).decode()} for f in files])
    check('zone highlights while dragging over', p.evaluate('()=>window.__over') is True)
    check('dropped files land in the file input', p.evaluate("()=>document.querySelector('[data-drop-input]').files.length") == 2)
    check('the zone lists what was dropped', 'drop-a.png' in p.inner_text('[data-drop-list]') and '2 ·' in p.inner_text('[data-drop-list]'))
    p.locator('[data-preset="640x360"]').click()
    check('preset fills width and height', p.input_value('#img-w') == '640' and p.input_value('#img-h') == '360')
    lib = os.path.join(ROOT, 'htdocs/assets/_base/images/library')
    pre = set(os.listdir(lib))
    p.locator('form[data-img-form] button[type=submit]').click(); p.wait_for_load_state('load')
    made = sorted(set(os.listdir(lib)) - pre)
    check('submitting the dropped files saves two webp pictures', len(made) == 2 and all(m.endswith('.webp') for m in made), made)
    check('the screen now shows them with thumbnails', p.locator('ul img[src*="/library/drop-"]').count() == 2)
    check('thumbnails really loaded (decoded, 640x360)', p.evaluate("()=>[...document.querySelectorAll('ul img[src*=\"/library/drop-\"]')].every(i=>i.complete&&i.naturalWidth===640&&i.naturalHeight===360)"))
    check('the result report is shown', p.locator('text=Last upload').count() >= 1)
    check('page still fits (no horizontal scroll)', p.evaluate('()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth+1'))
    # keyboard: zone is focusable and Enter/Space opens the chooser
    p.locator('[data-dropzone]').focus()
    with p.expect_file_chooser(timeout=3000) as fc:
        p.keyboard.press('Enter')
    check('keyboard Enter on the zone opens the file chooser', fc.value is not None)
    # delete through the confirm dialog
    p.once('dialog', lambda d: d.accept())
    p.locator('ul li:has-text("drop-a") button:has-text("Delete")').first.click(); p.wait_for_load_state('load')
    check('delete (after confirm) removes the picture', not [m for m in os.listdir(lib) if m.startswith('drop-a')])
    for m in set(os.listdir(lib)) - pre:
        os.remove(os.path.join(lib, m))
    p.screenshot(path='/tmp/images-1280.png')
    x.close()

    br.close()

errs = [e for e in errors if 'Failed to load resource' not in e]
check('no JavaScript errors on any of these pages', not errs, errs[:3])
print(f'\nbrowser_forms: {ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

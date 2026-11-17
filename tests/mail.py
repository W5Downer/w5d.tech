#!/usr/bin/env python3
"""Mail + unsubscribe tests (stdlib only). Usage: python3 tests/mail.py [base_url]
Needs MAIL_DRIVER=log and a dev/demo database. Renders all 5 mail types x 3 languages through the real Mail class."""
import glob, json, os, re, shutil, subprocess, sys, time, urllib.error, urllib.parse, urllib.request, http.cookiejar

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8080').rstrip('/')
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
shutil.rmtree(os.path.join(ROOT, 'storage/cache/rl'), ignore_errors=True)
RUN = str(int(time.time()))
ok = bad = 0

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None

def client():
    jar = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect)

def call(c, method, path, data=None):
    body = urllib.parse.urlencode(data).encode() if data is not None else None
    req = urllib.request.Request(BASE + path, data=body, method=method)
    if body: req.add_header('Content-Type', 'application/x-www-form-urlencoded')
    try: r = c.open(req, timeout=20)
    except urllib.error.HTTPError as e: r = e
    return r.status if hasattr(r, 'status') else r.code, dict(r.headers), r.read().decode('utf-8', 'replace')

def check(name, cond, extra=''):
    global ok, bad
    if cond: ok += 1; print(f'  ok   {name}')
    else: bad += 1; print(f'  FAIL {name} {extra}')

def php(code):
    out = subprocess.run(['php', '-r', "require '" + ROOT + "/bin/_boot.php'; " + code], capture_output=True, text=True, cwd=ROOT)
    return out.stdout.strip()

def log_text():
    t = ''
    for f in sorted(glob.glob(os.path.join(ROOT, 'storage/logs/mail-*.log'))): t += open(f, encoding='utf-8', errors='replace').read()
    return t

def entries(email):
    return [b for b in re.split(r'(?=\[\d{4}-\d\d-\d\dT)', log_text()) if 'to=' + email + ' ' in b]

SAMPLES = {
 'verify_email': {'name': 'Amal', 'url': 'https://w5d.tech/account/verify/abc/'},
 'password_reset': {'name': 'Amal', 'url': 'https://w5d.tech/account/reset/abc/', 'minutes': 60},
 'order_receipt': {'name': 'Amal', 'ref': 'ORD-T1', 'parts': 2, 'days': 30, 'start': '2026-12-01', 'total': 120, 'currency': 'TND', 'd17': {'amount': 120, 'gsm': '+21612345678', 'name': 'W5D'}, 'pay_url': 'https://w5d.tech/pay/ORD-T1/?s=x', 'key': 'KEY123'},
 'payment_status': {'name': 'Amal', 'ref': 'PAY-T1', 'order_ref': 'ORD-T1', 'amount': 120, 'currency': 'TND', 'status': 'rejected', 'note': 'Wrong ref', 'pay_url': 'https://w5d.tech/pay/ORD-T1/?s=x'},
 'form_ack': {'kind': 'contact', 'name': 'Amal', 'ref': 'C-T1'},
}

print('render matrix: 5 types x 3 languages')
for lang in ('en', 'fr', 'ar'):
    for t, d in SAMPLES.items():
        to = f'rt-{RUN}-{lang}-{t}@mail.test'.replace('@mail.test', '@real.org')
        res = php("var_export(W5D\\Mail::send(" + json.dumps(to) + "," + json.dumps(t) + ",json_decode(" + json.dumps(json.dumps(d)) + ",true)," + json.dumps(lang) + "));")
        e = entries(to)
        body = e[-1] if e else ''
        check(f'{lang}/{t} sent', res == 'true' and bool(e), res)
        check(f'{lang}/{t} unsubscribe/preferences link in footer', '/unsubscribe/?e=' in body)
        check(f'{lang}/{t} service mail has no List-Unsubscribe header', 'List-Unsubscribe:' not in body)
        check(f'{lang}/{t} honesty limits present', ('W5D' in body) and len(body) > 400)

print('html structure')
html = php("echo W5D\\Lang::using('ar', fn()=>view('mail.verify_email',['name'=>'A','url'=>'https://w5d.tech/x/','subject'=>'s','to'=>'a@real.org','unsub'=>'https://w5d.tech/unsubscribe/?e=a','category'=>'transactional','isStaff'=>false]));")
check('ar html is RTL', 'dir="rtl"' in html and 'lang="ar"' in html)
check('html has preheader', '<!--pre-->' in html)
check('html has dark-mode media query', 'prefers-color-scheme: dark' in html)
check('html has viewport meta', 'name="viewport"' in html)
check('html uses no external assets', not re.search(r'(src|href)="https?://(?!w5d\.tech|localhost)', html))
check('html has bulletproof button', 'bgcolor="#6d28d9"' in html)

print('newsletter: subscribe -> List-Unsubscribe -> one-click -> suppression')
nl = f'nl{RUN}@real.org'
c = client()
s, h, t = call(c, 'GET', '/newsletter/')
tok = re.search(r'name="_csrf" value="([0-9a-f]+)"', t)
check('newsletter form has csrf', bool(tok))
s, h, t = call(c, 'POST', '/newsletter/', {'_csrf': tok.group(1) if tok else '', 'email': nl, 'accept': '1', 'website': ''})
check('newsletter submit redirects', s in (302, 303), str(s))
e = entries(nl)
check('welcome mail logged', len(e) == 1)
m = re.search(r'List-Unsubscribe: <([^>]+)>', e[0]) if e else None
check('updates mail has List-Unsubscribe header', bool(m))
link = m.group(1) if m else ''
path = link.split('//', 1)[-1]; path = path[path.index('/'):] if link else ''
s, h, t = call(client(), 'GET', path)
check('GET unsubscribe link shows confirm page', s == 200 and 'Yes, unsubscribe me' in t)
check('unsubscribe page is noindex', 'noindex' in h.get('X-Robots-Tag', ''))
s, h, t = call(client(), 'POST', path, {'List-Unsubscribe': 'One-Click'})
check('RFC 8058 one-click POST (no CSRF) accepted', s == 200 and t.strip() == 'OK', f'{s} {t[:60]}')
check('address is now suppressed', php("var_export(W5D\\Mail::suppressed(" + json.dumps(nl) + "));") == 'true')
before = len(entries(nl))
res = php("var_export(W5D\\Mail::send(" + json.dumps(nl) + ",'form_ack',['kind'=>'newsletter','name'=>'','ref'=>'']));")
check('updates mail NOT sent after unsubscribe', res == 'false' and len(entries(nl)) == before, res)
res = php("var_export(W5D\\Mail::send(" + json.dumps(nl) + ",'verify_email',['name'=>'A','url'=>'https://w5d.tech/x/']));")
check('service mail still sent after unsubscribe', res == 'true', res)
s, h, t = call(client(), 'GET', path)
check('GET after unsubscribe shows done state', s == 200 and 'unsubscribed' in t.lower())

print('resubscribe via page (CSRF)')
c = client()
s, h, t = call(c, 'GET', path)
tok = re.search(r'name="_csrf" value="([0-9a-f]+)"', t)
q = urllib.parse.parse_qs(urllib.parse.urlparse(link).query)
s, h, t = call(c, 'POST', '/unsubscribe/', {'_csrf': tok.group(1) if tok else '', 'e': q['e'][0], 's': q['s'][0], 'action': 'resubscribe'})
check('resubscribe ok', s == 200 and 'Welcome back' in t, str(s))
check('suppression removed', php("var_export(W5D\\Mail::suppressed(" + json.dumps(nl) + "));") == 'false')
s, h, t = call(client(), 'POST', '/unsubscribe/', {'e': q['e'][0], 's': q['s'][0], 'action': 'unsubscribe'})
check('POST without CSRF and without one-click is refused', s == 400, str(s))
check('still subscribed after refused POST', php("var_export(W5D\\Mail::suppressed(" + json.dumps(nl) + "));") == 'false')

print('tamper / invalid')
for name, p in [('no params', '/unsubscribe/'), ('bad signature', path.replace('&s=', '&s=00')), ('other email', path.replace('nl' + RUN, 'zz' + RUN)), ('empty sig', re.sub(r'&s=[0-9a-f]+', '&s=', path))]:
    s, h, t = call(client(), 'GET', path if False else p)
    check(f'{name} -> 400 friendly page', s == 400 and 'not valid' in t, str(s))
s, h, t = call(client(), 'POST', path.replace('&s=', '&s=00'), {'List-Unsubscribe': 'One-Click'})
check('one-click with bad signature refused', s == 400, str(s))
for lang in ('fr', 'ar'):
    s, h, t = call(client(), 'GET', re.sub(r'&l=en', '&l=' + lang, path))
    check(f'unsubscribe page language {lang}', s == 200 and f'lang="{lang}"' in t, str(s))

print(f'\nmail: {ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

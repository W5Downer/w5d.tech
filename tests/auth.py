#!/usr/bin/env python3
"""Account flows (stdlib only). Usage: python3 tests/auth.py [base_url]
Needs MAIL_DRIVER=log (links are read from storage/logs/mail-*.log). Dev/demo database only."""
import glob, os, re, shutil, sys, time, urllib.error, urllib.parse, urllib.request, http.cookiejar

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8080').rstrip('/')
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
shutil.rmtree(os.path.join(ROOT, 'storage/cache/rl'), ignore_errors=True)
RUN = str(int(time.time()))
ok = bad = 0

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None

def client():
    jar = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect), jar

def call(c, method, path, data=None, lang='en', cookies=True):
    opener, jar = c
    body = urllib.parse.urlencode(data).encode() if data is not None else None
    req = urllib.request.Request(BASE + path, data=body, method=method)
    if body: req.add_header('Content-Type', 'application/x-www-form-urlencoded')
    try: r = opener.open(req, timeout=20)
    except urllib.error.HTTPError as e: r = e
    return r.status if hasattr(r, 'status') else r.code, dict(r.headers), r.read().decode('utf-8', 'replace')

def check(name, cond, extra=''):
    global ok, bad
    if cond: ok += 1; print(f'  ok   {name}')
    else: bad += 1; print(f'  FAIL {name} {extra}')

def csrf(c, path):
    s, h, t = call(c, 'GET', path)
    m = re.search(r'name="_csrf" value="([0-9a-f]+)"', t)
    return m.group(1) if m else ''

def last_link(kind, email):
    """Newest verify/reset link in the mail log addressed to email."""
    txt = ''
    for f in sorted(glob.glob(os.path.join(ROOT, 'storage/logs/mail-*.log'))): txt += open(f, encoding='utf-8', errors='replace').read()
    blocks = [b for b in txt.split('] [') if ('to=' + email) in b and ('/account/' + kind + '/') in b]
    if not blocks: return None
    m = re.search(r'/account/' + kind + r'/([A-Za-z0-9_.\-]+)/', blocks[-1])
    return m.group(1) if m else None

email = f'test{RUN}@mail.test'; pw = 'Str0ngPass-' + RUN
print('register / verify / login')
c = client()
t = csrf(c, '/account/register/')
s, h, b = call(c, 'POST', '/account/register/', {'_csrf': t, 'name': 'Test User', 'email': email, 'password': 'short', 'password2': 'short', 'role': 'investor', 'accept': '1'})
check('weak password bounced', s == 302 and 'register' in h.get('Location', ''), (s, h.get('Location')))
s, h, b = call(c, 'GET', '/account/register/')
check('password error shown', 'At least 10' in b or 'Password' in b)
t = csrf(c, '/account/register/')
s, h, b = call(c, 'POST', '/account/register/', {'_csrf': t, 'name': 'Test User', 'email': email, 'password': pw, 'password2': pw, 'role': 'investor', 'accept': '1'})
check('register redirects to login', s == 302 and 'login' in h.get('Location', ''), (s, b[:200]))
t = csrf(c, '/account/login/')
s, h, b = call(c, 'POST', '/account/login/', {'_csrf': t, 'identifier': email, 'password': pw})
check('login blocked until verified', s == 302 and 'login' in h.get('Location', ''))
s, h, b = call(c, 'GET', '/account/login/')
check('unverified message', 'Confirm your e-mail' in b)
tok = last_link('verify', email)
check('verification link in mail log', bool(tok))
if tok:
    s, h, b = call(c, 'GET', f'/account/verify/{tok}/')
    check('verify link accepted', s == 302, s)
    s, h, b = call(c, 'GET', f'/account/verify/{tok}/')
    check('verify link single-use (410)', s == 410, s)
t = csrf(c, '/account/login/')
s, h, b = call(c, 'POST', '/account/login/', {'_csrf': t, 'identifier': email, 'password': 'wrong-password-1'})
check('wrong password rejected', s == 302 and 'login' in h.get('Location', ''))
t = csrf(c, '/account/login/')
s, h, b = call(c, 'POST', '/account/login/', {'_csrf': t, 'identifier': email, 'password': pw, 'remember': '1'})
check('login ok -> /account/', s == 302 and h.get('Location', '').rstrip('/').endswith('/account'), (s, h.get('Location')))
rem = [x for x in c[1] if x.name == 'w5d_remember']
check('remember-me cookie set', bool(rem))
for p in ['/account/', '/account/orders/', '/account/payments/', '/account/applications/', '/account/notifications/', '/account/profile/', '/account/security/']:
    s, h, b = call(c, 'GET', p)
    check(f'dashboard {p}', s == 200 and '</html>' in b, s)
s, h, b = call(c, 'GET', '/account/nope/')
check('unknown panel 404', s == 404, s)
t = csrf(c, '/account/profile/')
s, h, b = call(c, 'POST', '/account/profile/', {'_csrf': t, 'name': 'Test User 2', 'phone': '+216 20 123 456', 'lang': 'fr'})
check('profile saved', s == 302)
s, h, b = call(c, 'GET', '/account/profile/')
check('profile persisted', 'Test User 2' in b and '+216 20 123 456' in b)

print('remember-me restores session')
req = urllib.request.Request(BASE + '/account/', headers={'Cookie': 'w5d_remember=' + rem[0].value})
try: r = client()[0].open(req, timeout=20)
except urllib.error.HTTPError as e: r = e
s, b = r.code, r.read().decode('utf-8', 'replace')
check('remember cookie logs in a fresh browser', s == 200 and 'Test User 2' in b, s)

print('logout')
t = csrf(c, '/account/security/')
s, h, b = call(c, 'POST', '/account/logout/', {'_csrf': t})
check('logout redirects', s == 302)
s, h, b = call(c, 'GET', '/account/')
check('dashboard needs login', s == 302 and 'login' in h.get('Location', ''))
s, h, b = call(c, 'POST', '/account/logout/', {})
check('logout without csrf does nothing harmful', s in (302, 403))

print('forgot / reset')
c3 = client()
t = csrf(c3, '/account/forgot/')
s, h, b = call(c3, 'POST', '/account/forgot/', {'_csrf': t, 'email': email})
check('forgot answers neutrally', s == 302)
t = csrf(c3, '/account/forgot/')
s, h, b = call(c3, 'POST', '/account/forgot/', {'_csrf': t, 'email': 'nobody' + RUN + '@mail.test'})
check('unknown e-mail answers the same', s == 302)
tok = last_link('reset', email)
check('reset link in mail log', bool(tok))
if tok:
    s, h, b = call(c3, 'GET', f'/account/reset/{tok}/')
    check('reset form', s == 200 and 'name="password2"' in b, s)
    npw = 'N3wStrongPass-' + RUN
    t = csrf(c3, f'/account/reset/{tok}/')
    s, h, b = call(c3, 'POST', f'/account/reset/{tok}/', {'_csrf': t, 'password': npw, 'password2': npw})
    check('password reset', s == 302 and 'login' in h.get('Location', ''), (s, b[:200]))
    s, h, b = call(c3, 'GET', f'/account/reset/{tok}/')
    check('reset link single-use', s == 410, s)
    t = csrf(c3, '/account/login/')
    s, h, b = call(c3, 'POST', '/account/login/', {'_csrf': t, 'identifier': email, 'password': pw})
    s2, h2, _ = call(c3, 'GET', '/account/')
    check('old password no longer works', s2 == 302 and 'login' in h2.get('Location', ''))
    t = csrf(c3, '/account/login/')
    s, h, b = call(c3, 'POST', '/account/login/', {'_csrf': t, 'identifier': email, 'password': npw})
    check('new password works', s == 302 and '/account' in h.get('Location', '') and 'login' not in h.get('Location', ''))

print('safety')
c4 = client()
s, h, b = call(c4, 'GET', '/account/login/?next=https://evil.test/')
t = csrf(c4, '/account/login/?next=https://evil.test/')
s, h, b = call(c4, 'POST', '/account/login/?next=https://evil.test/', {'_csrf': t, 'identifier': email, 'password': npw})
check('open redirect refused', 'evil.test' not in h.get('Location', ''), h.get('Location'))
c4 = client()
s, h, b = call(c4, 'POST', '/account/login/', {'identifier': email, 'password': npw})
s2, h2, _ = call(c4, 'GET', '/account/')
check('login without csrf refused', s == 302 and 'login' in h.get('Location', '') and s2 == 302 and 'login' in h2.get('Location', ''))
s, h, b = call(c4, 'GET', '/pay/ORD-00000000/?s=bad')
check('pay page needs valid signature', s == 404, s)
s, h, b = call(c4, 'GET', '/account/social/google/')
check('disabled social provider is not a crash', s == 302 and 'login' in h.get('Location', ''), (s, h.get('Location')))

print(f'\n{ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

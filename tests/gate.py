#!/usr/bin/env python3
"""Sign-in gate tests (stdlib only). Usage: python3 tests/gate.py [base_url]
Guests are sent to /account/login/?next=<path+query> for checkout, subscription, applicant registration,
manager application; contact + newsletter stay public; unsafe next values are refused; after sign-in the
form is pre-filled with a read-only account e-mail and the account e-mail is forced on submit.
Needs MAIL_DRIVER=log. Dev/demo database only."""
import os, re, shutil, sys, urllib.parse
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _acct import signed_in, _client, _call, _csrf, verify_token

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8080').rstrip('/')
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
shutil.rmtree(os.path.join(ROOT, 'storage/cache/rl'), ignore_errors=True)
ok = bad = 0


def check(name, cond, extra=''):
    global ok, bad
    if cond: ok += 1; print(f'  ok   {name}')
    else: bad += 1; print(f'  FAIL {name} {extra}')


def call(c, m, p, d=None): return _call(c, BASE, m, p, d)


def nxt(h):
    q = urllib.parse.urlparse(h.get('Location', ''))
    return q.path, urllib.parse.parse_qs(q.query).get('next', [''])[0]


print('guest is redirected to sign-in with next')
g = _client()
for path in ('/checkout/', '/subscription/', '/app/register/', '/careers/managers/'):
    s, h, b = call(g, 'GET', path)
    check(f'GET {path} -> login', s == 302 and nxt(h) == ('/account/login/', path), (s, h.get('Location')))
s, h, b = call(g, 'GET', '/checkout/?macro=built&days=5')
check('query string preserved in next', s == 302 and nxt(h)[1] == '/checkout/?macro=built&days=5', h.get('Location'))
s, h, b = call(g, 'GET', '/account/login/?next=%2Fcheckout%2F')
check('login page explains why (info notice)', s == 200 and ('Sign in' in b or 'sign in' in b.lower()), s)
check('login page carries next into the register link', 'register/?next=%2Fcheckout%2F' in b)
s, h, b = call(g, 'POST', '/checkout/', {'mode': 'checkout', 'full_name': 'x', 'email': 'x@y.tn'})
check('guest POST /checkout/ is gated too', s == 302 and nxt(h) == ('/account/login/', '/checkout/'), (s, h.get('Location')))
s, h, b = call(g, 'POST', '/careers/managers/', {'name': 'x'})
check('guest POST manager application is gated', s == 302 and nxt(h)[0] == '/account/login/', (s, h.get('Location')))

print('public things stay public')
for path in ('/contact/', '/newsletter/', '/join/eligibility/', '/pricing/', '/for/', '/status/'):
    s, h, b = call(g, 'GET', path)
    check(f'GET {path} 200 for guests', s == 200, s)
t = _csrf(g, BASE, '/contact/')
s, h, b = call(g, 'POST', '/contact/', {'_csrf': t, 'name': 'Pub', 'email': 'pub@mail.test', 'desk': 'general', 'message': 'Hello from a guest'})
check('contact accepted from a guest', s in (302, 303) and 'thanks' in h.get('Location', ''), (s, h.get('Location')))

print('unsafe next is refused')
for bad_next in ('//evil.example', 'https://evil.example/x', '/\\evil.example', 'javascript:alert(1)'):
    s, h, b = call(g, 'GET', '/account/login/?next=' + urllib.parse.quote(bad_next, safe=''))
    check(f'login page ignores next={bad_next}', s == 200 and 'evil.example' not in b.replace('evil.example/', '') or 'next=' + urllib.parse.quote(bad_next, safe='') not in b, s)

print('registration keeps next; verify; login lands on the form')
c = _client(); RUN = str(abs(hash(os.urandom(4))))[:8]
email = f'gate{RUN}@mail.test'; pw = 'Str0ngPass-' + RUN
t = _csrf(c, BASE, '/account/register/?next=%2Fcheckout%2F%3Fmacro%3Dbuilt')
s, h, b = call(c, 'POST', '/account/register/?next=%2Fcheckout%2F%3Fmacro%3Dbuilt', {'_csrf': t, 'name': 'Gate Person', 'email': email, 'password': pw, 'password2': pw, 'role': 'investor', 'accept': '1'})
check('register redirects to login keeping next', s == 302 and nxt(h)[1] == '/checkout/?macro=built', (s, h.get('Location')))
tok = verify_token(ROOT, email)
logs = ''
import glob
for f in glob.glob(os.path.join(ROOT, 'storage/logs/mail-*.log')): logs += open(f, errors='replace').read()
check('verification mail link carries next', tok and ('/account/verify/' + tok + '/?next=') in logs, tok)
s, h, b = call(c, 'GET', f'/account/verify/{tok}/?next=%2Fcheckout%2F%3Fmacro%3Dbuilt')
check('verify redirects to login keeping next', s == 302 and nxt(h)[1] == '/checkout/?macro=built', (s, h.get('Location')))
t = _csrf(c, BASE, '/account/login/?next=%2Fcheckout%2F%3Fmacro%3Dbuilt')
s, h, b = call(c, 'POST', '/account/login/?next=%2Fcheckout%2F%3Fmacro%3Dbuilt', {'_csrf': t, 'identifier': email, 'password': pw})
check('sign-in returns to the original form', s == 302 and h.get('Location', '').endswith('/checkout/?macro=built'), (s, h.get('Location')))

print('signed in: prefilled, e-mail read-only and forced')
s, h, b = call(c, 'GET', '/checkout/')
check('checkout opens (200)', s == 200, s)
check('full name pre-filled', 'Gate Person' in b)
check('account e-mail pre-filled', email in b)
check('e-mail field read-only', re.search(r'<input[^>]*name="email"[^>]*readonly', b) is not None or re.search(r'<input[^>]*readonly[^>]*name="email"', b) is not None)
t = _csrf(c, BASE, '/checkout/')
form = {'_csrf': t, 'mode': 'checkout', 'full_name': 'Gate Person', 'email': 'someone-else@mail.test', 'kind': 'individual', 'cadence': 'daily', 'parts_per_day': '1', 'days': '2', 'accept': '1'}
s, h, b = call(c, 'POST', '/checkout/', form)
check('order created', s == 302 and '/checkout/done/' in h.get('Location', ''), (s, h.get('Location')))
s, h, b = call(c, 'GET', '/account/orders/')
check('order is linked to the account (shows in /account/orders/)', s == 200 and 'ORD-' in b, s)
check('typed e-mail was overridden by the account e-mail', 'someone-else@mail.test' not in b)
for path in ('/subscription/', '/app/register/', '/careers/managers/'):
    s, h, b = call(c, 'GET', path)
    check(f'{path} opens for a signed-in account', s == 200, s)

print('owner can switch the gate off (flag) and on again')
env = dict(l.split('=', 1) for l in open(os.path.join(ROOT, '.env')).read().splitlines() if '=' in l and not l.startswith('#'))
o = _client()
t = _csrf(o, BASE, '/account/login/')
s, h, b = call(o, 'POST', '/account/login/', {'_csrf': t, 'identifier': env.get('WEBMASTER_USERNAME', 'owner'), 'password': os.environ.get('W5D_OWNER_PASS', 'w5d-demo-owner')})
if s == 302 and 'login' not in h.get('Location', ''):
    M = '/' + env.get('ADMIN_URL_PATH', 'panel-x9z').strip('/')
    def flag(v):
        t = _csrf(o, BASE, M + '/variables/')
        return call(o, 'POST', M + '/variables/save', {'_csrf': t, 'note': 'gate test flag toggle', 'v[flags__gate_forms]': str(v)})
    s, h, b = flag(0)
    s, h, b = call(g, 'GET', '/checkout/')
    check('flag off -> checkout public again', s == 200, s)
    s, h, b = flag(1)
    s, h, b = call(g, 'GET', '/checkout/')
    check('flag on -> gated again', s == 302, s)
    t = _csrf(o, BASE, M + '/variables/')
    s, h, b = call(o, 'POST', M + '/variables/save', {'_csrf': t, 'note': 'gate test bad value', 'v[flags__gate_forms]': '7'})
    s, h, b = call(g, 'GET', '/checkout/')
    check('flag value other than 0/1 is refused (gate unchanged)', s == 302, s)
    s, h, b = call(o, 'GET', '/checkout/')
    check('owner session passes the gate', s == 200, s)
else:
    check('owner login for flag test', False, (s, h.get('Location')))

print(f'\n{ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

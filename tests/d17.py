#!/usr/bin/env python3
"""D17 (Tunisia) payment flow + admin verification (stdlib only). Usage: python3 tests/d17.py [base_url]
Needs PAYMENT_D17_ENABLED=true + PAYMENT_D17_GSM in .env and MAIL_DRIVER=log. Dev/demo database only."""
import glob, os, re, shutil, sys, time, urllib.error, urllib.parse, urllib.request, http.cookiejar, datetime
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _acct import signed_in

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

def call(c, method, path, data=None):
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

env = dict(l.split('=', 1) for l in open(os.path.join(ROOT, '.env')).read().splitlines() if '=' in l and not l.startswith('#'))
start = (datetime.date.today() + datetime.timedelta(days=2)).isoformat()
c, email = signed_in(BASE, ROOT, 'customer', 'D17 Buyer', 'd17')
base = {'full_name': 'D17 Buyer', 'email': email, 'kind': 'individual', 'parts_per_day': '2', 'days': '3', 'start_on': start, 'cadence': 'daily', 'accept': '1', 'mode': 'checkout'}

print('checkout with D17')
s, h, t = call(c, 'GET', '/checkout/')
check('wizard offers D17', 'value="d17"' in t, s)
tok = csrf(c, '/checkout/')
s, h, t = call(c, 'POST', '/checkout/', {**base, '_csrf': tok, 'payment_method': 'd17', 'phone': '+33 6 12 34 56 78'})
check('non-Tunisian phone refused for D17', s == 302 and '/checkout/done/' not in h.get('Location', ''), (s, h.get('Location')))
tok = csrf(c, '/checkout/')
s, h, t = call(c, 'POST', '/checkout/', {**base, '_csrf': tok, 'payment_method': 'd17', 'phone': '+216 20 123 456'})
loc = h.get('Location', '')
check('D17 order accepted', s == 302 and '/checkout/done/' in loc, (s, loc))
ref = re.search(r'(ORD-[A-F0-9]{8})', loc).group(1) if loc else ''
s, h, t = call(c, 'GET', loc)
check('done page links to the payment page', s == 200 and '/pay/' + ref in t, s)
m = re.search(r'href="([^"]*/pay/' + ref + r'/\?s=[0-9a-f]+)"', t)
pay = urllib.parse.urlparse(m.group(1)).path + '?' + urllib.parse.urlparse(m.group(1)).query if m else ''
check('signed pay link present', bool(pay), pay)

print('mail')
txt = ''.join(open(f, encoding='utf-8', errors='replace').read() for f in sorted(glob.glob(os.path.join(ROOT, 'storage/logs/mail-*.log'))))
blk = [b for b in txt.split('] [') if 'to=' + email in b and 'order_receipt' in b]
check('order_receipt mail sent with D17 block', bool(blk) and '+21620123456' in blk[-1])

print('pay page')
s, h, t = call(client(), 'GET', pay)
check('pay page renders', s == 200 and '+21620123456' in t and 'name="txn_ref"' in t, s)
s, h, t = call(client(), 'GET', pay.replace('?s=', '?s=0'))
check('bad signature -> 404', s == 404, s)
c2 = client()
tk = csrf(c2, pay)
s, h, t = call(c2, 'POST', pay, {'_csrf': tk, 'sender_gsm': '+33612345678', 'txn_ref': 'ABC123456'})
check('foreign sender GSM refused', s == 302)
s, h, t = call(c2, 'GET', pay)
check('error shown', 'Tunisian' in t)
tk = csrf(c2, pay)
s, h, t = call(c2, 'POST', pay, {'_csrf': tk, 'sender_gsm': '20 123 456', 'txn_ref': 'd17-' + RUN[-6:]})
check('reference submitted', s == 302)
s, h, t = call(c2, 'GET', pay)
check('status pending + reference shown', ('D17-' + RUN[-6:]) in t and 'Waiting for verification' in t)
tk = csrf(c2, pay)
s, h, t = call(c2, 'POST', pay, {'_csrf': tk, 'sender_gsm': '20 123 456', 'txn_ref': 'D17-' + RUN[-6:]})
s, h, t = call(c2, 'GET', pay)
check('same reference can be re-sent by the same payment', 'name="txn_ref"' in t)

print('admin verification')
M = '/' + env.get('ADMIN_URL_PATH', 'panel-x9z')
a = client()
from _staff import staff_login
staff_login(BASE, ROOT, None, M, client=a)
check('owner signs in (e-mail code)', True)
s, h, t = call(a, 'GET', M + '/payments-d17/')
check('payments screen lists the payment', s == 200 and ref in t and 'D17-' + RUN[-6:] in t, s)
pid = re.search(r'name="id" value="(\d+)"', t[t.find(ref):]).group(1) if ref in t else None
check('payment id found', bool(pid))
s, h, t = call(a, 'POST', M + '/payments/review', {'_csrf': 'bad', 'id': pid, 'do': 'verify'})
s2, h2, t2 = call(a, 'GET', M + '/payments-d17/')
check('review with bad CSRF changes nothing', 'verified' not in t2[t2.find(ref):t2.find(ref) + 1500].split('</tr>')[0])
tk = re.search(r'name="_csrf" value="([0-9a-f]+)"', t2).group(1)
s, h, t = call(a, 'POST', M + '/payments/review', {'_csrf': tk, 'id': pid, 'do': 'reject', 'note': 'Amount mismatch'})
s, h, t = call(a, 'GET', M + '/payments-d17/')
check('rejected + note stored', 'rejected' in t and 'Amount mismatch' in t)
s, h, t = call(c2, 'GET', pay)
check('buyer sees rejection and can resubmit', 'Rejected' in t and 'name="txn_ref"' in t)
tk = csrf(c2, pay)
call(c2, 'POST', pay, {'_csrf': tk, 'sender_gsm': '20123456', 'txn_ref': 'FIX' + RUN[-6:]})
tk = re.search(r'name="_csrf" value="([0-9a-f]+)"', call(a, 'GET', M + '/payments-d17/')[2]).group(1)
s, h, t = call(a, 'POST', M + '/payments/review', {'_csrf': tk, 'id': pid, 'do': 'verify', 'note': 'ok'})
s, h, t = call(c2, 'GET', pay)
check('verified: buyer sees Verified, form gone', 'Verified' in t and 'name="txn_ref"' not in t)
txt = ''.join(open(f, encoding='utf-8', errors='replace').read() for f in sorted(glob.glob(os.path.join(ROOT, 'storage/logs/mail-*.log'))))
check('payment_status mails sent (rejected + verified)', len([b for b in txt.split('] [') if 'to=' + email in b and 'payment_status' in b]) >= 2)
s, h, t = call(a, 'GET', M + '/audit/')
check('audit log has payment.verified', 'payment.verified' in t)
s, h, t = call(a, 'GET', M + '/mail-outbox/')
check('mail outbox screen renders', s == 200 and 'order_receipt' in t, s)
s, h, t = call(a, 'GET', M + '/system/')
check('system screen shows D17 + mail driver', 'D17' in t and 'Mail driver' in t)
s, h, t = call(client(), 'POST', M + '/payments/review', {'id': pid, 'do': 'verify'})
check('anonymous cannot review', s in (302, 403))

print(f'\n{ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

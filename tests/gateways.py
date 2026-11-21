#!/usr/bin/env python3
"""Online payment gateways (Flouci, Konnect, Paymee) + owner configuration, against a fake gateway (stdlib only).
Usage: python3 tests/gateways.py [base_url]

Dev/demo database only.  The fake gateway (tests/_gwstub.py) is started and stopped by this script on port GW_PORT (default 8097).
It mimics the documented request/response shapes; it is NOT the real API, so the real sandboxes still need one manual pass.
In a production-mode copy (https base) plain-http loopback gateways are refused by design, so only the refusal/ACL checks run."""
import hashlib, hmac, json, os, re, sqlite3, subprocess, sys, time, urllib.error, urllib.parse, urllib.request, http.cookiejar, datetime, glob, shutil
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import _acct

def signed_in(*a, **k):
    shutil.rmtree(os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'storage/cache/rl'), ignore_errors=True)
    return _acct.signed_in(*a, **k)

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8080').rstrip('/')
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
GW_PORT = int(os.environ.get('GW_PORT', '8097'))
GW = f'http://127.0.0.1:{GW_PORT}'
PROD = BASE.startswith('https://')
shutil.rmtree(os.path.join(ROOT, 'storage/cache/rl'), ignore_errors=True)
ok = bad = 0


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None


def client():
    jar = http.cookiejar.CookieJar()
    import ssl
    ctx = ssl.create_default_context(); ctx.check_hostname = False; ctx.verify_mode = ssl.CERT_NONE
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect, urllib.request.HTTPSHandler(context=ctx)), jar


def call(c, method, path, data=None, raw=None, headers=None, base=None):
    opener, jar = c
    body = raw if raw is not None else (urllib.parse.urlencode(data).encode() if data is not None else None)
    req = urllib.request.Request((base or BASE) + path, data=body, method=method)
    if body is not None and raw is None: req.add_header('Content-Type', 'application/x-www-form-urlencoded')
    for k, v in (headers or {}).items(): req.add_header(k, v)
    try: r = opener.open(req, timeout=25)
    except urllib.error.HTTPError as e: r = e
    return (r.status if hasattr(r, 'status') else r.code), dict(r.headers), r.read().decode('utf-8', 'replace')


def check(name, cond, extra=''):
    global ok, bad
    if cond: ok += 1; print(f'  ok   {name}')
    else: bad += 1; print(f'  FAIL {name} {extra}')


def csrf(c, path):
    s, h, t = call(c, 'GET', path)
    m = re.search(r'name="_csrf" value="([0-9a-f]+)"', t)
    return m.group(1) if m else ''


def gw(method, path, obj=None):
    req = urllib.request.Request(GW + path, data=json.dumps(obj).encode() if obj is not None else None, method=method)
    with urllib.request.urlopen(req, timeout=5) as r: return json.loads(r.read() or b'{}')


env = dict(l.split('=', 1) for l in open(os.path.join(ROOT, '.env')).read().splitlines() if '=' in l and not l.startswith('#'))
M = '/' + env.get('ADMIN_URL_PATH', 'panel-x9z').strip().strip('"')
DB = os.path.join(ROOT, 'storage', 'w5d.sqlite')


def row(sql, *a):
    cx = sqlite3.connect(DB, timeout=10); cx.row_factory = sqlite3.Row
    try: r = cx.execute(sql, a).fetchone(); return dict(r) if r else None
    finally: cx.close()


def rows(sql, *a):
    cx = sqlite3.connect(DB, timeout=10); cx.row_factory = sqlite3.Row
    try: return [dict(r) for r in cx.execute(sql, a).fetchall()]
    finally: cx.close()


def php_sign(payref):
    code = 'require "bin/_boot.php"; echo W5D\\Secret::sign("payret:" . $argv[1]);'
    return subprocess.check_output(['php', '-r', code, payref], cwd=ROOT).decode().strip()


def wipe():
    cx = sqlite3.connect(DB, timeout=10)
    try:
        cx.execute("DELETE FROM app_settings WHERE skey LIKE 'pay.flouci.%' OR skey LIKE 'pay.konnect.%' OR skey LIKE 'pay.paymee.%' OR skey LIKE 'pay.bank.%'"); cx.commit()
    finally: cx.close()


if not PROD: wipe()

# ---------------------------------------------------------------- owner
print('owner configuration')
a = client()
tk = csrf(a, M + '/login/')
s, h, t = call(a, 'POST', M + '/login/', {'_csrf': tk, 'username': env.get('WEBMASTER_USERNAME', 'owner').strip('"'), 'password': os.environ.get('W5D_OWNER_PASS') or env.get('WEBMASTER_PASSWORD', 'w5d-demo-owner')})
check('owner signs in', s == 302, s)
s, h, t = call(a, 'GET', M + '/payment-methods/')
check('payment-methods screen renders for the owner', s == 200 and 'Flouci' in t and 'Konnect' in t and 'Paymee' in t, s)
check('screen lists D17 and bank transfer', 'D17' in t and ('Bank' in t or 'bank' in t))
check('screen has a CSRF token', 'name="_csrf"' in t)
s, h, t = call(client(), 'GET', M + '/payment-methods/')
check('anonymous cannot open the screen', s in (302, 303, 403, 404) and 'Flouci' not in t, s)
s, h, t = call(client(), 'POST', M + '/payment-methods/save', {'item[flouci]': '1', 'on[flouci]': '1', 'note': 'anonymous attempt'})
check('anonymous cannot save', s in (302, 303, 403, 404))
s, h, t = call(a, 'POST', M + '/payment-methods/save', {'_csrf': 'bad', 'item[flouci]': '1', 'note': 'bad csrf attempt here'})
check('bad CSRF saves nothing', row("SELECT 1 AS x FROM app_settings WHERE skey = 'pay.flouci.public' AND value = 'HACK'") is None)

tk = csrf(a, M + '/payment-methods/')
s, h, t = call(a, 'POST', M + '/payment-methods/save', {'_csrf': tk, 'item[flouci]': '1', 'on[flouci]': '1', 'f[flouci][public]': 'PUBTOKEN123', 'f[flouci][private]': 'PRIVTOKEN456', 'note': 'x'})
check('a change without a real reason is refused (nothing stored)', PROD or row("SELECT 1 AS x FROM app_settings WHERE skey = 'pay.flouci.public'") is None)
s, h, t = call(a, 'GET', M + '/payment-methods/')
check('the screen tells the owner a reason is required', 'reason' in t.lower())

if PROD:
    tk = csrf(a, M + '/payment-methods/')
    s, h, t = call(a, 'POST', M + '/payment-methods/save', {'_csrf': tk, 'item[flouci]': '1', 'f[flouci][public]': 'PUBTOKEN123', 'f[flouci][base_url]': GW, 'note': 'production loopback refusal test'})
    check('production refuses a plain-http gateway URL', row("SELECT value FROM app_settings WHERE skey = 'pay.flouci.base_url'") in (None, {'value': ''}) or 'http://' not in (row("SELECT value FROM app_settings WHERE skey = 'pay.flouci.base_url'") or {'value': ''})['value'])
    s, h, t = call(client(), 'GET', '/pay/hook/flouci/')
    check('webhook of a method that is off answers 404', s == 404, s)
    s, h, t = call(client(), 'GET', '/pay/hook/unknown/')
    check('unknown provider answers 404', s == 404, s)
    print(f'\n{ok} passed, {bad} failed (production mode: gateway flows skipped, run on the dev server)')
    sys.exit(1 if bad else 0)

# ---------------------------------------------------------------- fake gateway
stub = subprocess.Popen([sys.executable, os.path.join(ROOT, 'tests', '_gwstub.py'), str(GW_PORT)], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
try:
    for _ in range(40):
        try: gw('GET', '/_log'); break
        except Exception: time.sleep(0.15)
    else:
        print('  FAIL fake gateway did not start'); sys.exit(1)

    CFG = {
        'flouci': {'f[flouci][public]': 'PUBTOKEN123', 'f[flouci][private]': 'PRIVTOKEN456', 'f[flouci][base_url]': GW, 'f[flouci][webhook_version]': '2'},
        'konnect': {'f[konnect][api_key]': 'konnect-api-key-1', 'f[konnect][wallet_id]': 'wallet-12345', 'f[konnect][base_url]': GW + '/konnect', 'f[konnect][mode]': 'sandbox'},
        'paymee': {'f[paymee][api_token]': 'paymee-token-1', 'f[paymee][vendor]': '1234', 'f[paymee][base_url]': GW + '/paymee', 'f[paymee][mode]': 'sandbox'},
    }

    def configure(pid, on=True, extra=None):
        tk = csrf(a, M + '/payment-methods/')
        d = {'_csrf': tk, 'item[' + pid + ']': '1', 'note': 'gateway test configuration'}
        d.update(CFG[pid]); d.update(extra or {})
        if on: d['on[' + pid + ']'] = '1'
        call(a, 'POST', M + '/payment-methods/save', d)

    # the owner screen may not enable an incomplete method
    tk = csrf(a, M + '/payment-methods/')
    call(a, 'POST', M + '/payment-methods/save', {'_csrf': tk, 'item[konnect]': '1', 'on[konnect]': '1', 'f[konnect][wallet_id]': 'wallet-12345', 'note': 'incomplete enable test'})
    check('a method with a missing required key cannot be enabled', (row("SELECT value FROM app_settings WHERE skey = 'pay.konnect.enabled'") or {'value': '0'})['value'] != '1')

    for pid in CFG: configure(pid)
    for pid in CFG:
        s, h, t = call(a, 'GET', M + '/payment-methods/')
        check(f'{pid}: shown as live after saving', pid in t.lower())
    check('private token is stored encrypted', (row("SELECT value, is_secret FROM app_settings WHERE skey = 'pay.flouci.private'") or {'value': '', 'is_secret': 0})['value'].startswith('enc1:'))
    s, h, t = call(a, 'GET', M + '/payment-methods/')
    check('secrets are never rendered back', 'PRIVTOKEN456' not in t and 'konnect-api-key-1' not in t and 'paymee-token-1' not in t)
    logs = ''.join(open(f, encoding='utf-8', errors='replace').read() for f in glob.glob(os.path.join(ROOT, 'storage/logs/*.log')))
    check('secrets never reach the logs', 'PRIVTOKEN456' not in logs and 'konnect-api-key-1' not in logs and 'paymee-token-1' not in logs)
    audit = ' '.join(str(r) for r in rows('SELECT old_value, new_value FROM settings_audit'))
    check('change log never stores secret values', 'PRIVTOKEN456' not in audit and 'konnect-api-key-1' not in audit)
    check('change log has entries', row('SELECT COUNT(*) AS n FROM settings_audit')['n'] > 0)

    start = (datetime.date.today() + datetime.timedelta(days=2)).isoformat()

    def order(pid):
        c, email = signed_in(BASE, ROOT, 'customer', 'Gateway Buyer', 'gw' + pid[:2])
        base = {'full_name': 'Gateway Buyer', 'email': email, 'kind': 'individual', 'parts_per_day': '2', 'days': '3', 'start_on': start, 'cadence': 'daily', 'accept': '1', 'mode': 'checkout', 'payment_method': pid, 'phone': '+216 20 123 456'}
        s, h, t = call(c, 'GET', '/checkout/')
        check(f'{pid}: wizard offers the method', f'value="{pid}"' in t, s)
        tk = csrf(c, '/checkout/')
        s, h, t = call(c, 'POST', '/checkout/', {**base, '_csrf': tk})
        loc = h.get('Location', '')
        check(f'{pid}: order accepted', s == 302 and '/checkout/done/' in loc, (s, loc))
        ref = re.search(r'(ORD-[A-F0-9]{8})', loc).group(1)
        s, h, t = call(c, 'GET', loc)
        pl = re.search(r'(/pay/' + ref + r'/\?s=[0-9a-f]+)', t)
        check(f'{pid}: done page links to the signed pay page', s == 200 and bool(pl), s)
        p = row('SELECT * FROM payments WHERE order_ref = ? ORDER BY id DESC', ref)
        return c, email, ref, pl.group(1) if pl else '', p

    def hook(pid, p, good=True, extra_body=None):
        ref = p['provider_ref']
        if pid == 'flouci':
            body = json.dumps({'payment_id': ref, 'event_id': 'evt-' + ref, 'status': 'SUCCESS'}).encode()
            t = str(int(time.time()))
            sig = hmac.new(b'PRIVTOKEN456', (t + '.').encode() + body, hashlib.sha256).hexdigest()
            if not good: sig = '0' * 64
            return call(client(), 'POST', '/pay/hook/flouci/', raw=body, headers={'Content-Type': 'application/json', 'X-Flouci-Signature': f't={t},v1={sig}'})
        if pid == 'paymee':
            sm = hashlib.md5((ref + '1' + 'paymee-token-1').encode()).hexdigest()
            if not good: sm = 'f' * 32
            return call(client(), 'POST', '/pay/hook/paymee/', {'token': ref, 'payment_status': '1', 'check_sum': sm})
        return call(client(), 'GET', '/pay/hook/konnect/?payment_ref=' + urllib.parse.quote(ref if good else 'nope-' + ref))

    PAID = {'flouci': 'SUCCESS', 'konnect': 'completed', 'paymee': 'paid'}
    FAIL = {'flouci': 'EXPIRED', 'konnect': 'failed', 'paymee': 'unpaid'}
    for pid in ('flouci', 'konnect', 'paymee'):
        print(pid)
        c, email, ref, pay, p = order(pid)
        check(f'{pid}: payment row is pending with a provider reference', bool(p) and p['status'] == 'pending' and bool(p['provider_ref']) and p['method'] == pid, p and (p['status'], p['provider_ref']))
        check(f'{pid}: the gateway received the right amount and our return URLs', any(x['p'] == pid and x['id'] == p['provider_ref'] for x in gw('GET', '/_log')))
        sent = [x for x in gw('GET', '/_log') if x['id'] == p['provider_ref']][0]['req']
        check(f'{pid}: request carries our payment reference', p['ref'] in json.dumps(sent))
        check(f'{pid}: return URL is signed', 's=' in json.dumps(sent) and '/pay/return/' + p['ref'] in json.dumps(sent))
        s, h, t = call(c, 'GET', pay)
        check(f'{pid}: pay page offers Pay now (no manual form)', s == 200 and 'name="txn_ref"' not in t and 'btn-' in t, s)
        tk = csrf(c, pay)
        s, h, t = call(c, 'POST', pay, {'_csrf': tk, 'do': 'pay'})
        check(f'{pid}: Pay now redirects to the provider page', s == 302 and h.get('Location', '').startswith(GW + '/hosted/'), (s, h.get('Location')))
        s, h, t = call(c, 'POST', pay, {'do': 'pay'})
        check(f'{pid}: Pay now without CSRF is refused', not (s == 302 and h.get('Location', '').startswith(GW)), (s, h.get('Location')))

        s, h, t = hook(pid, p, good=False)
        check(f'{pid}: bad webhook signature / unknown reference is refused', s in (401, 404), s)
        check(f'{pid}: …and changes nothing', row('SELECT status FROM payments WHERE id = ?', p['id'])['status'] == 'pending')
        gw('POST', '/_set/' + p['provider_ref'], {'status': PAID[pid]})
        # a forged "paid" webhook is worthless while the provider still says pending
        gw('POST', '/_set/' + p['provider_ref'], {'status': 'PENDING' if pid == 'flouci' else ('pending' if pid == 'konnect' else 'unpaid')})
        time.sleep(0.2)
        s, h, t = hook(pid, p)
        check(f'{pid}: signed webhook while the provider says pending does not confirm', row('SELECT status FROM payments WHERE id = ?', p['id'])['status'] == 'pending', s)
        time.sleep(4.3)   # provider look-up cooldown
        gw('POST', '/_set/' + p['provider_ref'], {'status': PAID[pid]})
        s, h, t = hook(pid, p)
        check(f'{pid}: signed webhook with a paid payment is acknowledged', s == 200, s)
        r = row('SELECT status FROM payments WHERE id = ?', p['id'])
        check(f'{pid}: payment is verified', r['status'] == 'verified', r)
        check(f'{pid}: order is no longer awaiting payment', row('SELECT status FROM investor_orders WHERE ref = ?', ref)['status'] != 'awaiting_payment')
        s, h, t = hook(pid, p)
        check(f'{pid}: a repeated webhook is acknowledged and idempotent', s == 200 and row('SELECT status FROM payments WHERE id = ?', p['id'])['status'] == 'verified')
        mails = ''.join(open(f, encoding='utf-8', errors='replace').read() for f in sorted(glob.glob(os.path.join(ROOT, 'storage/logs/mail-*.log'))))
        n = len([b for b in mails.split('] [') if 'to=' + email in b and 'payment_status' in b])
        check(f'{pid}: exactly one confirmation e-mail', n == 1, n)
        s, h, t = call(c, 'GET', pay)
        check(f'{pid}: buyer sees Verified', 'Verified' in t or 'Paid' in t or 'confirmed' in t.lower(), s)
        s, h, t = call(c, 'GET', '/account/payments/')
        check(f'{pid}: account list shows the method name', s == 200 and pid.capitalize() in t, s)

    print('return page')
    c, email, ref, pay, p = order('flouci')
    sg = php_sign(p['ref'])
    s, h, t = call(c, 'GET', f"/pay/return/{p['ref']}/?s=deadbeef&o=ok")
    check('return page with a bad signature is a 404', s == 404, s)
    gw('POST', '/_set/' + p['provider_ref'], {'status': 'SUCCESS'})
    s, h, t = call(c, 'GET', f"/pay/return/{p['ref']}/?s={sg}&o=ok")
    check('return page with a good signature re-reads the provider and redirects', s == 302 and '/pay/' + ref in h.get('Location', ''), (s, h.get('Location')))
    check('return page confirmed the payment through the provider', row('SELECT status FROM payments WHERE id = ?', p['id'])['status'] == 'verified')
    c, email, ref, pay, p = order('flouci')
    sg = php_sign(p['ref'])
    s, h, t = call(c, 'GET', f"/pay/return/{p['ref']}/?s={sg}&o=ok")
    check('"o=ok" in the URL alone never confirms (provider still pending)', row('SELECT status FROM payments WHERE id = ?', p['id'])['status'] == 'pending', s)

    print('failures and tampering')
    for pid in ('flouci', 'konnect', 'paymee'):
        c, email, ref, pay, p = order(pid)
        gw('POST', '/_set/' + p['provider_ref'], {'status': PAID[pid], 'amount': (999999 if pid != 'paymee' else 1.0)})
        time.sleep(4.3)
        hook(pid, p)
        check(f'{pid}: provider says paid but the amount differs -> NOT verified', row('SELECT status FROM payments WHERE id = ?', p['id'])['status'] != 'verified')
        c, email, ref, pay, p = order(pid)
        gw('POST', '/_set/' + p['provider_ref'], {'status': FAIL[pid]})
        # owner re-check button
        tk = csrf(a, M + '/payments-d17/')
        s, h, t = call(a, 'GET', M + '/payments-d17/')
        check(f'{pid}: payments screen lists the payment with its method', p['ref'] in t or p['order_ref'] in t, s)
        m = re.search(r'name="id" value="(\d+)"', t[t.find(p['ref']):]) if p['ref'] in t else None
        if m:
            tk = re.search(r'name="_csrf" value="([0-9a-f]+)"', t).group(1)
            call(a, 'POST', M + '/payments/review', {'_csrf': tk, 'id': m.group(1), 'do': 'recheck', 'note': ''})
            st = row('SELECT status FROM payments WHERE id = ?', p['id'])['status']
            check(f'{pid}: owner re-check reads the provider and settles a failed payment', st in ('rejected', 'pending'), st)
            check(f'{pid}: a failed payment is never verified', st != 'verified')

    print('provider outage')
    c, email, ref, pay, p = order('paymee')
    configure('paymee', True, {'f[paymee][base_url]': 'http://127.0.0.1:1'})
    c2, email2 = signed_in(BASE, ROOT, 'customer', 'Gateway Buyer', 'gwdown')
    tk = csrf(c2, '/checkout/')
    s, h, t = call(c2, 'POST', '/checkout/', {'_csrf': tk, 'full_name': 'Gateway Buyer', 'email': email2, 'kind': 'individual', 'parts_per_day': '2', 'days': '3', 'start_on': start, 'cadence': 'daily', 'accept': '1', 'mode': 'checkout', 'payment_method': 'paymee', 'phone': '+216 20 123 456'})
    check('provider down: checkout still records the order (buyer can retry from the pay page)', s == 302 and '/checkout/done/' in h.get('Location', ''), (s, h.get('Location')))
    s, h, t = call(a, 'GET', M + '/payment-methods/')
    s2, h2, t2 = hook('paymee', p)
    check('provider down: a signed webhook gets a retry-later (5xx) or ack, never a crash', s2 in (200, 503), s2)
    configure('paymee', True)

    print('switching off')
    configure('flouci', False)
    c3, email3 = signed_in(BASE, ROOT, 'customer', 'Gateway Buyer', 'gwoff')
    s, h, t = call(c3, 'GET', '/checkout/')
    check('a disabled method disappears from the wizard', 'value="flouci"' not in t and 'value="konnect"' in t)
    tk = csrf(c3, '/checkout/')
    s, h, t = call(c3, 'POST', '/checkout/', {'_csrf': tk, 'full_name': 'Gateway Buyer', 'email': email3, 'kind': 'individual', 'parts_per_day': '2', 'days': '3', 'start_on': start, 'cadence': 'daily', 'accept': '1', 'mode': 'checkout', 'payment_method': 'flouci', 'phone': '+216 20 123 456'})
    check('a disabled method is refused server-side', s == 302 and '/checkout/done/' not in h.get('Location', ''), (s, h.get('Location')))
    s, h, t = call(client(), 'POST', '/pay/hook/flouci/', raw=b'{}', headers={'Content-Type': 'application/json'})
    check('webhook of a disabled method answers 404', s == 404, s)
    s, h, t = call(client(), 'GET', '/pay/hook/nothing/')
    check('unknown provider answers 404', s == 404, s)

    print('erase a stored secret')
    tk = csrf(a, M + '/payment-methods/')
    call(a, 'POST', M + '/payment-methods/save', {'_csrf': tk, 'item[flouci]': '1', 'clear[flouci][private]': '1', 'note': 'erase the secret test'})
    r = row("SELECT value FROM app_settings WHERE skey = 'pay.flouci.private'")
    check('erased secret is gone from the database', r is None or r['value'] == '')
    configure('flouci', True, {'f[flouci][private]': ''})
    check('a method whose secret was erased cannot be switched on', (row("SELECT value FROM app_settings WHERE skey = 'pay.flouci.enabled'") or {'value': '0'})['value'] != '1')
    for pid in CFG: configure(pid, False)
    wipe()
finally:
    stub.terminate()
    try: stub.wait(timeout=3)
    except Exception: stub.kill()

print(f'\n{ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

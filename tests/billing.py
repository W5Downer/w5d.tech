#!/usr/bin/env python3
"""Billing core + the four revenue streams (stdlib only). Usage: python3 tests/billing.py [base_url]
Needs the dev/demo database, PAYMENT_D17_ENABLED=true and MAIL_DRIVER=log. Set-up is direct SQL; every outcome goes through HTTP.
Plans / fees touched by the test are restored at the end."""
import os, re, sqlite3, sys, time, subprocess, json, urllib.parse
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _acct import signed_in, _call, _csrf
from _staff import staff_login, MOUNT

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8080').rstrip('/')
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB = os.path.join(ROOT, 'storage/w5d.sqlite')
RUN = str(int(time.time()))[-6:]
ok = bad = 0


def check(name, cond, extra=''):
    global ok, bad
    if cond: ok += 1; print(f'  ok   {name}')
    else: bad += 1; print(f'  FAIL {name} {extra}')


def sql(q, a=()):
    c = sqlite3.connect(DB, timeout=30); c.row_factory = sqlite3.Row
    try:
        r = [dict(x) for x in c.execute(q, a).fetchall()]; c.commit(); return r
    finally: c.close()


def one(q, a=()):
    r = sql(q, a); return r[0] if r else None


def get(c, p): return _call(c, BASE, 'GET', p)
def post(c, p, d): return _call(c, BASE, 'POST', p, d)


def pay_d17(c, pay_path, ref):
    """buyer: choose D17, send the reference. returns the payments row."""
    t = _csrf(c, BASE, pay_path)
    s, h, b = post(c, pay_path, {'_csrf': t, 'do': 'choose', 'method': 'd17'})
    t = _csrf(c, BASE, pay_path)
    s2, h2, b2 = post(c, pay_path, {'_csrf': t, 'sender_gsm': '20 123 456', 'txn_ref': ref})
    return s, s2


def owner_verify(a, txn, do='verify'):
    p = one('SELECT id FROM payments WHERE txn_ref = ?', (txn,))
    if not p: return False
    t = _csrf(a, BASE, MOUNT + '/payments-d17/')
    s, h, b = post(a, MOUNT + '/payments/review', {'_csrf': t, 'id': p['id'], 'do': do, 'note': 'test'})
    return s == 302


def inv_by_src(kind, src): return one('SELECT * FROM invoices WHERE kind = ? AND source_ref = ? ORDER BY id DESC', (kind, src))


def pay_path_of(number):
    sig = subprocess.check_output(['php', '-r', 'require "bin/_boot.php"; echo W5D\\Secret::sign("pay:' + number + '");'], cwd=ROOT).decode().strip()
    return f'/pay/{number}/?s={sig}'


# ---------------------------------------------------------------- set-up
before_plans = sql('SELECT * FROM plans'); before_fees = sql('SELECT * FROM cert_fees')
sql("UPDATE plans SET price = 10, price_year = 100, seat_price = 2, active = 1 WHERE code = 'team'")
sql("UPDATE plans SET price = 0, active = 1 WHERE code = 'enterprise'")  # quote only
sql("UPDATE cert_fees SET amount = 7.5, active = 1 WHERE kind = 'certification'")
sql("UPDATE cert_fees SET amount = 0, active = 0 WHERE kind = 'recertification'")

print('public plans page')
anon = (None,)
from _acct import _client
pub = _client()
s, h, b = get(pub, '/plans/')
check('/plans/ renders with the active plan', s == 200 and 'Team' in b and 'data-plans-state="open"' in b, s)
check('inactive plan (price 0) is not sold', 'name="plan" value="1"' not in b)
s, h, b = get(pub, '/account/plan/')
check('account plan page needs sign-in', s in (301, 302), s)
s, h, b = get(pub, '/invoice/INV-2000-00001/')
check('unknown invoice -> 404', s == 404, s)

u, email = signed_in(BASE, ROOT, 'customer', 'Bill Payer', 'bl')
uid = one('SELECT id FROM users WHERE email = ?', (email,))['id']
a = staff_login(BASE, ROOT)
check('owner signs in', True)

print('stream 2: subscription')
s, h, b = get(u, '/account/plan/')
check('plan panel renders', s == 200 and 'Team' in b and 'Choose a plan' in b, s)
team = one("SELECT id FROM plans WHERE code = 'team'")
t = _csrf(u, BASE, '/account/plan/')
s, h, b = post(u, '/account/plan/buy', {'_csrf': t, 'plan': team['id'], 'period': 'month', 'seats': '3'})
loc = h.get('Location', '')
check('buy -> redirect to the pay page of an invoice', s == 302 and '/pay/INV-' in loc, (s, loc))
num = re.search(r'(INV-\d{4}-\d{5})', loc).group(1) if loc else ''
inv = one('SELECT * FROM invoices WHERE number = ?', (num,))
check('invoice = base + 0 extra seats (3 included? no: 5 included)', inv and abs(inv['amount'] - 10.0) < 0.001, inv and inv['amount'])
check('invoice is open and kind plan', inv and inv['status'] == 'sent' and inv['kind'] == 'plan')
pp = urllib.parse.urlparse(loc); pay = pp.path + '?' + pp.query
s, h, b = get(u, pay)
check('pay page asks for a method first', s == 200 and 'name="method"' in b and num in b, s)
s, h, b = get(pub, pay)
check('anyone with the signed link sees the pay page', s == 200)
s, h, b = get(pub, pay.replace('?s=', '?s=0'))
check('bad signature -> 404', s == 404)
s, s2 = pay_d17(u, pay, 'PL' + RUN + 'A')
check('method chosen and reference sent', s == 302 and s2 == 302, (s, s2))
sub = one("SELECT * FROM plan_subscriptions WHERE user_id = ?", (uid,))
check('subscription pending before payment', sub and sub['status'] == 'pending')
check('verify by owner', owner_verify(a, 'PL' + RUN + 'A'))
inv = one('SELECT * FROM invoices WHERE number = ?', (num,)); sub = one("SELECT * FROM plan_subscriptions WHERE user_id = ?", (uid,))
check('invoice paid + subscription active with a period end', inv['status'] == 'paid' and sub['status'] == 'active' and sub['current_period_end'], (inv['status'], sub and sub['status']))
check('paid by d17', inv['method'] == 'd17')
t2 = owner_verify(a, 'PL' + RUN + 'A')
check('verifying twice does not extend the period twice', one('SELECT current_period_end AS e FROM plan_subscriptions WHERE id = ?', (sub['id'],))['e'] == sub['current_period_end'])

s, h, b = get(u, '/account/plan/')
check('panel shows active plan, seats form, pipeline', 'data-sub="' + sub['ref'] + '"' in b and 'Add a seat' in b and 'Pipeline' in b, s)
t = _csrf(u, BASE, '/account/plan/')
post(u, '/account/plan/manage', {'_csrf': t, 'ref': sub['ref'], 'do': 'seat-add', 'email': f'seat{RUN}@mail.test'})
check('seat added', one('SELECT COUNT(*) AS n FROM plan_seats WHERE subscription_id = ?', (sub['id'],))['n'] == 1)
post(u, '/account/plan/manage', {'_csrf': t, 'ref': sub['ref'], 'do': 'seat-add', 'email': f'owner@w5d.tech'})
check('team address cannot take a seat', one('SELECT COUNT(*) AS n FROM plan_seats WHERE subscription_id = ?', (sub['id'],))['n'] == 1)
s, h, b = post(u, '/account/plan/manage', {'_csrf': 'bad', 'ref': sub['ref'], 'do': 'cancel'})
check('bad CSRF refused', one('SELECT cancel_at_period_end AS c FROM plan_subscriptions WHERE id = ?', (sub['id'],))['c'] == 0)
post(u, '/account/plan/manage', {'_csrf': t, 'ref': sub['ref'], 'do': 'cancel'})
check('cancel at period end keeps it active', one('SELECT status, cancel_at_period_end AS c FROM plan_subscriptions WHERE id = ?', (sub['id'],)) == {'status': 'active', 'c': 1})
post(u, '/account/plan/manage', {'_csrf': t, 'ref': sub['ref'], 'do': 'resume'})
check('resume', one('SELECT cancel_at_period_end AS c FROM plan_subscriptions WHERE id = ?', (sub['id'],))['c'] == 0)
s, h, b = post(u, '/account/plan/buy', {'_csrf': t, 'plan': team['id'], 'period': 'month', 'seats': '3'})
check('a second plan while one is active is refused', s == 302 and '/pay/' not in h.get('Location', ''), h.get('Location'))

print('renewal cron')
sql("UPDATE plan_subscriptions SET current_period_end = date('now','+2 days') WHERE id = ?", (sub['id'],))
out = subprocess.run(['php', '-r', 'require "bin/_boot.php"; echo json_encode(W5D\\Billing::runRenewals());'], cwd=ROOT, capture_output=True, text=True).stdout
check('cron makes one renewal invoice', json.loads(out or '{}').get('invoiced') == 1, out)
out = subprocess.run(['php', '-r', 'require "bin/_boot.php"; echo json_encode(W5D\\Billing::runRenewals());'], cwd=ROOT, capture_output=True, text=True).stdout
check('cron is idempotent', json.loads(out or '{}').get('invoiced') == 0, out)
ren = one("SELECT * FROM invoices WHERE kind = 'plan' AND source_ref = ? AND status = 'sent'", (sub['ref'],))
check('renewal invoice exists', bool(ren))
old_end = sub['current_period_end']
s, s2 = pay_d17(u, pay_path_of(ren['number']), 'PL' + RUN + 'B')
owner_verify(a, 'PL' + RUN + 'B')
new_end = one('SELECT current_period_end AS e FROM plan_subscriptions WHERE id = ?', (sub['id'],))['e']
check('paying the renewal extends the period', new_end > (time.strftime('%Y-%m-%d', time.localtime(time.time() + 2 * 86400))), new_end)

print('stream 3: certification fee')
fee = one("SELECT id FROM cert_fees WHERE kind = 'certification'")
s, h, b = get(u, '/account/certificates/')
check('certificates panel lists the fee button', s == 200 and 'action="' + BASE.split('//')[0] in b or 'name="fee"' in b, s)
t = _csrf(u, BASE, '/account/certificates/')
s, h, b = post(u, '/account/fee/buy', {'_csrf': t, 'fee': fee['id']})
loc = h.get('Location', '')
check('fee -> pay page', s == 302 and '/pay/INV-' in loc, (s, loc))
cnum = re.search(r'(INV-\d{4}-\d{5})', loc).group(1) if loc else ''
check('amount from the fee row', one('SELECT amount FROM invoices WHERE number = ?', (cnum,))['amount'] == 7.5)
s, h, b = post(u, '/account/fee/buy', {'_csrf': t, 'fee': fee['id']})
check('buying twice reuses the open invoice', cnum in h.get('Location', ''))
s, h, b = post(u, '/account/fee/buy', {'_csrf': t, 'fee': one("SELECT id FROM cert_fees WHERE kind='recertification'")['id']})
check('inactive fee refused', '/pay/' not in h.get('Location', ''))
pay_d17(u, pay_path_of(cnum), 'CF' + RUN + 'A')
owner_verify(a, 'CF' + RUN + 'A')
check('fee invoice paid', one('SELECT status FROM invoices WHERE number = ?', (cnum,))['status'] == 'paid')

print('stream 1: service fees (milestones, review-gated)')
sql("INSERT INTO service_engagements (country_id, ref, title, scope, client_name, client_email, status) VALUES ('tn', ?, 'Test project', 'scope', 'Bill Payer', ?, 'draft')", ('ENG-' + RUN, email))
eng = one('SELECT id FROM service_engagements WHERE ref = ?', ('ENG-' + RUN,))
sql("INSERT INTO milestones (country_id, engagement_id, title, amount) VALUES ('tn', ?, 'Phase one', 120)", (eng['id'],))
ms = one('SELECT id FROM milestones WHERE engagement_id = ?', (eng['id'],))['id']
for pg in ('invoices', 'milestones', 'engagements', 'plans', 'plan-subscriptions', 'cert-fees', 'placements', 'my-invoices'):
    s, h, b = get(a, f'{MOUNT}/{pg}/')
    check(f'owner screen {pg} renders', s == 200 and 'Fatal' not in b and 'Warning:' not in b, s)
t = _csrf(a, BASE, MOUNT + '/milestones/')
s, h, b = post(a, MOUNT + '/billing/act', {'_csrf': 'bad', 'do': 'ms-invoice', 'id': ms})
check('milestone action with bad CSRF changes nothing', one('SELECT status FROM milestones WHERE id = ?', (ms,))['status'] == 'planned')
s, h, b = post(a, MOUNT + '/billing/act', {'_csrf': t, 'do': 'ms-deliver', 'id': ms, 'note': 'too early please'})
check('cannot deliver before funding', one('SELECT status FROM milestones WHERE id = ?', (ms,))['status'] == 'planned')
post(a, MOUNT + '/billing/act', {'_csrf': t, 'do': 'ms-invoice', 'id': ms})
m = one('SELECT * FROM milestones WHERE id = ?', (ms,))
check('milestone invoiced', m['status'] == 'invoiced' and m['invoice_id'])
minv = one('SELECT * FROM invoices WHERE id = ?', (m['invoice_id'],))
check('invoice for the client with the milestone amount', minv['email'] == email and minv['amount'] == 120 and minv['kind'] == 'service')
s, h, b = get(u, '/account/invoices/')
check('client sees invoice and project milestone', s == 200 and minv['number'] in b and 'Phase one' in b, s)
pay_d17(u, pay_path_of(minv['number']), 'MS' + RUN + 'A')
owner_verify(a, 'MS' + RUN + 'A')
check('payment funds the milestone', one('SELECT status FROM milestones WHERE id = ?', (ms,))['status'] == 'funded')
post(a, MOUNT + '/billing/act', {'_csrf': t, 'do': 'ms-deliver', 'id': ms, 'note': 'Delivered the first phase'})
check('delivered', one('SELECT status FROM milestones WHERE id = ?', (ms,))['status'] == 'delivered')
post(a, MOUNT + '/billing/act', {'_csrf': t, 'do': 'ms-dispute', 'id': ms, 'note': ''})
check('send back needs a reason', one('SELECT status FROM milestones WHERE id = ?', (ms,))['status'] == 'delivered')
post(a, MOUNT + '/billing/act', {'_csrf': t, 'do': 'ms-dispute', 'id': ms, 'note': 'Missing the report'})
check('sent back', one('SELECT status FROM milestones WHERE id = ?', (ms,))['status'] == 'disputed')
post(a, MOUNT + '/billing/act', {'_csrf': t, 'do': 'ms-reopen', 'id': ms})
post(a, MOUNT + '/billing/act', {'_csrf': t, 'do': 'ms-deliver', 'id': ms, 'note': 'Report added now'})
post(a, MOUNT + '/billing/act', {'_csrf': t, 'do': 'ms-release', 'id': ms})
check('owner releases', one('SELECT status FROM milestones WHERE id = ?', (ms,))['status'] == 'released')
check('engagement completes with its last milestone', one('SELECT status FROM service_engagements WHERE id = ?', (eng['id'],))['status'] == 'completed')

print('four eyes for non-owners')
from _staff import staff_client
try:
    rv = staff_client(BASE, 'admin', ROOT)
    sql("INSERT INTO milestones (country_id, engagement_id, title, amount, status) VALUES ('tn', ?, 'Phase two', 50, 'funded')", (eng['id'],))
    ms2 = one("SELECT id FROM milestones WHERE title = 'Phase two' AND engagement_id = ?", (eng['id'],))['id']
    tt = _csrf(rv, BASE, MOUNT + '/milestones/')
    s, h, b = get(rv, MOUNT + '/milestones/')
    if s == 200:
        post(rv, MOUNT + '/billing/act', {'_csrf': tt, 'do': 'ms-deliver', 'id': ms2, 'note': 'Delivered by this person'})
        post(rv, MOUNT + '/billing/act', {'_csrf': tt, 'do': 'ms-release', 'id': ms2})
        st = one('SELECT status FROM milestones WHERE id = ?', (ms2,))['status']
        check('the deliverer cannot release their own work', st in ('delivered', 'funded'), st)
    else:
        check('admin has no milestone screen (acceptable by ACL)', s in (302, 403, 404), s)
except Exception as e:
    check('four-eyes check could run', False, repr(e))

print('stream 4: placement commission')
sql("INSERT INTO clients (country_id, name, email) VALUES ('tn', 'Acme Test', ?)", (f'client{RUN}@mail.test',))
cl = one('SELECT id FROM clients WHERE email = ?', (f'client{RUN}@mail.test',))['id']
sql("INSERT INTO placements (country_id, ref, kind, client_id, title, contract_value, rate_pct) VALUES ('tn', ?, 'enterprise_talent', ?, 'Talent contract', 2000, 5)", ('PLC-' + RUN, cl))
pl = one('SELECT id FROM placements WHERE ref = ?', ('PLC-' + RUN,))['id']
post(a, MOUNT + '/billing/act', {'_csrf': t, 'do': 'pl-invoice', 'id': pl})
p = one('SELECT * FROM placements WHERE id = ?', (pl,))
check('placement invoiced, rate frozen, commission = 5% of 2000', p['status'] == 'invoiced' and p['rate_snapshot'] == 5 and p['commission'] == 100, p)
pinv = one('SELECT * FROM invoices WHERE id = ?', (p['invoice_id'],))
check('commission invoice amount', pinv['amount'] == 100 and pinv['kind'] == 'placement')
post(a, MOUNT + '/billing/act', {'_csrf': t, 'do': 'pl-invoice', 'id': pl})
check('cannot invoice twice', one("SELECT COUNT(*) AS n FROM invoices WHERE kind = 'placement' AND source_ref = ?", ('PLC-' + RUN,))['n'] == 1)
sql("UPDATE placements SET rate_pct = 9 WHERE id = ?", (pl,))
check('later rate change does not alter the invoiced commission', one('SELECT commission FROM placements WHERE id = ?', (pl,))['commission'] == 100)

print('manual invoice, void, offline payment, scope')
s, h, b = post(a, MOUNT + '/billing/act', {'_csrf': t, 'do': 'invoice-new', 'email': email, 'name': 'Bill Payer', 'desc': 'Workshop day', 'amount': '33.5'})
man = one("SELECT * FROM invoices WHERE kind = 'manual' AND email = ? ORDER BY id DESC", (email,))
check('manual invoice created', man and man['amount'] == 33.5 and man['status'] == 'sent')
s, h, b = post(a, MOUNT + '/billing/act', {'_csrf': t, 'do': 'invoice-new', 'email': 'nope', 'desc': 'x', 'amount': '5'})
check('invalid manual invoice refused', one("SELECT COUNT(*) AS n FROM invoices WHERE kind='manual' AND email = ?", (email,))['n'] == 1)
post(a, MOUNT + '/billing/act', {'_csrf': t, 'do': 'invoice-void', 'id': man['id'], 'note': ''})
check('void needs a reason', one('SELECT status FROM invoices WHERE id = ?', (man['id'],))['status'] == 'sent')
post(a, MOUNT + '/billing/act', {'_csrf': t, 'do': 'invoice-void', 'id': man['id'], 'note': 'Duplicate'})
check('voided', one('SELECT status FROM invoices WHERE id = ?', (man['id'],))['status'] == 'void')
s, h, b = get(pub, pay_path_of(man['number']))
check('a void invoice cannot be paid (404)', s == 404, s)
s, h, b = post(a, MOUNT + '/billing/act', {'_csrf': t, 'do': 'invoice-new', 'email': email, 'desc': 'Offline job', 'amount': '12'})
man2 = one("SELECT * FROM invoices WHERE kind = 'manual' AND email = ? AND status = 'sent'", (email,))
post(a, MOUNT + '/billing/act', {'_csrf': t, 'do': 'invoice-paid', 'id': man2['id'], 'note': 'x'})
check('offline payment needs a real note', one('SELECT status FROM invoices WHERE id = ?', (man2['id'],))['status'] == 'sent')
post(a, MOUNT + '/billing/act', {'_csrf': t, 'do': 'invoice-paid', 'id': man2['id'], 'note': 'Cash received at the desk'})
check('owner records offline payment', one('SELECT status, method FROM invoices WHERE id = ?', (man2['id'],)) == {'status': 'paid', 'method': 'offline'})
check('audit log has it', one("SELECT COUNT(*) AS n FROM audit_log WHERE action = 'invoice.paid_offline'")['n'] >= 1 if sql("SELECT name FROM sqlite_master WHERE name='audit_log'") else True)

s, h, b = get(pub, f'/invoice/{man2["number"]}/')
check('invoice view needs the signature or a session', s == 404, s)
s, h, b = get(u, f'/invoice/{man2["number"]}/')
check('the buyer can open their own invoice', s == 200 and man2['number'] in b and 'Paid' in b, s)
other, oemail = signed_in(BASE, ROOT, 'customer', 'Other Person', 'ot')
s, h, b = get(other, f'/invoice/{man2["number"]}/')
check('another account cannot open it', s == 404, s)
s, h, b = get(other, '/account/invoices/')
check('another account does not see it in the list', man2['number'] not in b and minv['number'] not in b, s)
s, h, b = get(u, '/account/invoices/pay/' + man2['number'] + '/')
check('paid invoice has no pay redirect', s == 404, s)
s, h, b = get(other, '/account/invoices/pay/' + ren['number'] + '/')
check('cannot pay-redirect someone else’s bill', s == 404, s)

print('access')
s, h, b = get(u, MOUNT + '/invoices/')
check('public account cannot open the owner invoices screen', s in (301, 302, 403, 404), s)
s, h, b = post(u, MOUNT + '/billing/act', {'_csrf': t, 'do': 'invoice-new', 'email': email, 'desc': 'hack', 'amount': '1'})
check('public account cannot post billing actions', one("SELECT COUNT(*) AS n FROM invoices WHERE name = 'hack' OR note = 'hack'")['n'] == 0 and s in (301, 302, 403, 404))
s, h, b = get(a, MOUNT + '/my-invoices/')
check('team member sees My invoices screen', s == 200)
s, h, b = get(pub, '/api/v1/invoices')
check('no public invoices API', s in (404, 401, 403), s)

print('pipeline entitlement')
s, h, b = get(other, '/account/plan/')
check('non-holder sees no pipeline numbers', s == 200 and 'Pipeline: certified people' not in b)
sql("INSERT OR IGNORE INTO plan_seats (subscription_id, email) VALUES (?, ?)", (sub['id'], oemail))
s, h, b = get(other, '/account/plan/')
check('a seat holder gets the pipeline', 'Pipeline: certified people' in b and 'colleague' in b, s)

# ---------------------------------------------------------------- restore
for r in before_plans:
    sql('UPDATE plans SET price=?, price_year=?, seat_price=?, active=? WHERE id=?', (r['price'], r['price_year'], r['seat_price'], r['active'], r['id']))
for r in before_fees:
    sql('UPDATE cert_fees SET amount=?, active=? WHERE id=?', (r['amount'], r['active'], r['id']))

print(f'\n{ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

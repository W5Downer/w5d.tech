#!/usr/bin/env python3
"""Affiliate programme end-to-end suite: apply (pending), owner approval, ?ref= cookie + click dedupe, first-touch,
attribution at sign-up, signup bonus on verify, payment commission via a verified QCM fee, referral decisions,
hold days / minimum / D17 number / one open payout, owner settlement, suspension, pause switch, ACL and CSRF.
Usage: python3 tests/affiliate.py [base_url]   (dev/demo DB, MAIL_DRIVER=log). Test rows and variable overrides are removed at the end.
"""
import os, re, sys, time
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _prog import *
from _acct import _client

AFF = '/account/affiliate/'
made_aff, made_users, made_rules = [], [], []


def mk_rule(**kw):
    d = dict(name='TEST ' + uniq(), purpose='practice', level=1, service_slug='networking', question_count=5, minutes=5, pass_pct=60, fee=0, min_level=0,
             min_affiliates=0, min_tasks=0, min_practice_pct=0, cooldown_hours=0, max_per_day=0, active=1, sort=999)
    d.update(kw)
    return run(f'INSERT INTO qcm_rules ({",".join(d)}) VALUES ({",".join("?" * len(d))})', tuple(d.values()))


def override(path, value):
    run('INSERT OR REPLACE INTO variable_overrides (path,value,changed_by) VALUES (?,?,?)', (path, str(value), 'tests/affiliate.py'))


def clear_overrides():
    run("DELETE FROM variable_overrides WHERE changed_by = 'tests/affiliate.py'")


def cookie(c, name='w5d_ref'):
    return next((k.value for k in c[1] if k.name == name), None)


def ua(c, s):
    c[0].addheaders = [('User-Agent', s)]


def aff_of(uid):
    return q1('SELECT * FROM affiliates WHERE user_id=?', (uid,))


def apply_form(c, **kw):
    d = {'pitch': 'I share it with my class', 'payout_gsm': '', 'accept': '1'}
    d.update(kw)
    d = {k: v for k, v in d.items() if v is not None}
    return post(c, '/account/affiliate/apply', d, token_from=AFF)


def settle(o, pid, **kw):
    return post(o, M + '/affiliates/payout', dict({'id': pid}, **kw), token_from=M + '/affiliates/')


def lines(affid, status=None):
    sql = 'SELECT * FROM affiliate_referrals WHERE affiliate_id=?' + (' AND status=?' if status else '') + ' ORDER BY id'
    return qall(sql, (affid, status) if status else (affid,))


def flash(c, needle):
    return needle in call(c, 'GET', AFF)[2]


try:
    reset_rl()
    clear_overrides()
    o = owner()

    section('guests and the public programme page')
    guest = _client()
    s, h, b = call(guest, 'GET', AFF)
    check('guest is sent to sign-in for the affiliate panel', s == 302 and '/account/login/' in loc(h), s)
    s, h, b = call(guest, 'POST', '/account/affiliate/apply', {'pitch': 'x', 'accept': '1'})
    check('apply without a session does not create anything', s in (302, 401, 403) and q1('SELECT COUNT(*) FROM affiliates WHERE pitch=?', ('x',)) == 0, s)
    s, h, b = call(guest, 'GET', '/affiliate/')
    check('public programme page renders with one h1', s == 200 and b.count('<h1') == 1, s)
    low = re.sub(r'<[^>]+>', ' ', b.lower())
    promises = [w for w in ('guaranteed income', 'get rich', 'passive income', 'revenu garanti', 'earn money fast', 'unlimited earnings') if w in low
                and not re.search(r'\bnot\s+(?:a\s+|an\s+)?' + re.escape(w), low) and not re.search(r'\bno\s+' + re.escape(w), low)]
    check('programme page makes no income promise (negated mentions such as "not a guaranteed income" are fine)', not promises, promises)
    check('programme page says commissions are not a salary', 'not a salary' in low)

    section('apply: validation, CSRF, honeypot, pending state')
    a1, a1e, a1u = new_user('user', 'Sami Affiliate', 'af')
    made_users.append(a1u)
    s, h, b = call(a1, 'GET', AFF)
    check('panel shows the join form (pitch, D17, accept) with CSRF', s == 200 and 'name="pitch"' in b and 'name="payout_gsm"' in b and 'name="accept"' in b and 'name="_csrf"' in b, s)
    s, h, b = call(a1, 'POST', '/account/affiliate/apply', {'pitch': 'no token', 'accept': '1'})
    check('apply without CSRF token creates nothing', aff_of(a1u) is None, s)
    apply_form(a1, accept=None)
    check('apply without accepting the rules creates nothing', aff_of(a1u) is None)
    apply_form(a1, payout_gsm='+33612345678')
    check('a non-Tunisian D17 number is refused', aff_of(a1u) is None)
    apply_form(a1, payout_gsm='abc')
    check('a junk D17 number is refused', aff_of(a1u) is None)
    apply_form(a1, pitch='x' * 601)
    check('a pitch over 600 characters is refused', aff_of(a1u) is None)
    apply_form(a1, website='http://spam.example')
    check('honeypot field filled -> nothing created', aff_of(a1u) is None)
    s, h, b = apply_form(a1)
    A1 = aff_of(a1u)
    check('valid application -> row created, status pending (owner approves by hand)', s == 302 and A1 and A1['status'] == 'pending', A1)
    check('code is 8 characters from the unambiguous alphabet', bool(re.match(r'^[A-HJKMNP-Z2-9]{8}$', A1['code'])), A1['code'])
    made_aff.append(A1['id'])
    check('the owner team is e-mailed about the application', 'Affiliate application' in mail_log() and A1['code'] in mail_log())
    b = call(a1, 'GET', AFF)[2]
    check('panel says pending and does not show a live link yet', 'pending' in b.lower() and '?ref=' + A1['code'] not in b)
    apply_form(a1, pitch='Updated pitch', payout_gsm='+21622345678')
    A1 = aff_of(a1u)
    check('re-submitting updates the profile (no second row, same code)', A1['pitch'] == 'Updated pitch' and A1['payout_gsm'] == '+21622345678' and q1('SELECT COUNT(*) FROM affiliates WHERE user_id=?', (a1u,)) == 1)

    section('pending affiliates are not tracked')
    v0 = _client()
    call(v0, 'GET', '/?ref=' + A1['code'])
    check('?ref= of a pending affiliate sets no cookie and logs no click', cookie(v0) is None and q1('SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_id=?', (A1['id'],)) == 0)

    section('owner approval: CSRF + ACL')
    s, h, b = call(o, 'GET', M + '/affiliates/')
    check('owner screen lists the application with an Approve button', s == 200 and A1['code'] in b and 'Approve' in b, s)
    call(o, 'POST', M + '/affiliates/status', {'id': A1['id'], 'status': 'active'})
    check('approve without CSRF does nothing', aff_of(a1u)['status'] == 'pending')
    post(a1, M + '/affiliates/status', {'id': A1['id'], 'status': 'active'}, token_from=AFF)
    check('a normal account cannot approve itself', aff_of(a1u)['status'] == 'pending')
    for role in ('leader', 'manager'):
        post(staff(role), M + '/affiliates/status', {'id': A1['id'], 'status': 'active'}, token_from='/account/')
        check(f'{role} (no affiliates permission) cannot approve', aff_of(a1u)['status'] == 'pending')
    post(o, M + '/affiliates/status', {'id': A1['id'], 'status': 'bogus'}, token_from=M + '/affiliates/')
    check('an unknown status value changes nothing', aff_of(a1u)['status'] == 'pending')
    ad = staff('admin')
    post(ad, M + '/affiliates/status', {'id': A1['id'], 'status': 'active'}, token_from=M + '/affiliates/')
    check('admin role can approve (affiliates.update)', aff_of(a1u)['status'] == 'active' and aff_of(a1u)['approved_at'])
    check('affiliate is e-mailed + notified about the decision', mail_to(a1e, A1['code']) and q1('SELECT COUNT(*) FROM notifications WHERE user_id=?', (a1u,)) >= 1)
    b = call(a1, 'GET', AFF)[2]
    check('panel now shows the personal link and the code', f'?ref={A1["code"]}' in b and A1['code'] in b)

    section('tracking: cookie, one click per visitor per day, first touch')
    v1 = _client(); ua(v1, 'Visitor-One/1.0')
    s, h, b = call(v1, 'GET', '/?ref=' + A1['code'])
    ck = next((k for k in v1[1] if k.name == 'w5d_ref'), None)
    days = (ck.expires - time.time()) / 86400 if ck and ck.expires else 0
    check('?ref= sets the w5d_ref cookie (HttpOnly, SameSite=Lax, ~30 days)', bool(ck) and ck.value == A1['code'] and ck.has_nonstandard_attr('HttpOnly') and ck.get_nonstandard_attr('SameSite') == 'Lax' and 29 <= days <= 31, (ck and (ck.value, ck._rest, round(days, 1))))
    check('one click logged', q1('SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_id=?', (A1['id'],)) == 1)
    call(v1, 'GET', '/pricing/?ref=' + A1['code'])
    call(v1, 'GET', '/?ref=' + A1['code'].lower())
    check('same visitor, same day: still one click (also for lower-case code)', q1('SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_id=?', (A1['id'],)) == 1)
    v2 = _client(); ua(v2, 'Visitor-Two/1.0')
    call(v2, 'GET', '/about/?ref=' + A1['code'])
    check('another visitor adds a second click and landing path is stored', q1('SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_id=?', (A1['id'],)) == 2 and q1("SELECT COUNT(*) FROM affiliate_clicks WHERE landing='/about/'") >= 1)
    for junk in ('ZZZZZZZZ', '<script>', '../../etc', 'A' * 80):
        vj = _client(); s, h, b = call(vj, 'GET', '/?ref=' + urllib.parse.quote(junk))
        check(f'unknown/junk ref {junk[:10]!r}: no cookie, no 5xx', cookie(vj) is None and s == 200, s)
    s, h, b = call(_client(), 'POST', '/contact/?ref=' + A1['code'], {})
    check('POST with ?ref= is never tracked', q1('SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_id=?', (A1['id'],)) == 2)
    call(a1, 'GET', '/?ref=' + A1['code'])
    check('the affiliate clicking their own link: no cookie, no click', cookie(a1) is None and q1('SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_id=?', (A1['id'],)) == 2)

    section('second affiliate and first-touch')
    a2, a2e, a2u = new_user('user', 'Rim Second', 'af')
    made_users.append(a2u)
    apply_form(a2, payout_gsm='+21622345678')
    A2 = aff_of(a2u); made_aff.append(A2['id'])
    post(o, M + '/affiliates/status', {'id': A2['id'], 'status': 'active'}, token_from=M + '/affiliates/')
    call(v1, 'GET', '/?ref=' + A2['code'])
    check('first touch wins: a later link of another affiliate does not replace the cookie', cookie(v1) == A1['code'])

    section('attribution at sign-up and signup bonus')
    nue, nuu = register_login(v1, 'user', 'Referred Person', 'rf')
    made_users.append(nuu)
    check('account created with the cookie is bound to affiliate 1', q1('SELECT referred_by FROM users WHERE id=?', (nuu,)) == A1['id'])
    sg = lines(A1['id'], 'pending')
    check('verification credits a signup line: 2.00 pending (auto_approve off)', len(sg) == 1 and sg[0]['kind'] == 'signup' and abs(sg[0]['commission'] - 2.0) < 0.001 and sg[0]['user_id'] == nuu, sg)
    ur, _, uu = new_user('user', 'Organic Person', 'rf')
    made_users.append(uu)
    check('an account without a cookie has no referrer and credits nobody', q1('SELECT referred_by FROM users WHERE id=?', (uu,)) is None)
    call(v1, 'GET', f'/account/verify/{"x" * 20}/')
    check('re-clicking a bad verify link does not pay twice', len(lines(A1['id'])) == 1)
    b = call(a1, 'GET', AFF)[2]
    check('affiliate panel: clicks 2, confirmed sign-ups 1, pending 2.00', re.search(r'>\s*2\s*</p>', b) is not None and '2.00' in b, '')

    section('payment commission from a verified QCM fee (5%)')
    rid = mk_rule(name='TEST aff fee', fee=100.0); made_rules.append(rid)
    s, h, b = post(v1, '/account/qcm/buy', {'rule': rid}, token_from='/account/qcm/')
    pay = re.search(r'(/pay/INV-\d{4}-\d{5}/\?s=[0-9a-f]+)', loc(h)).group(1)
    ref = q1("SELECT ref FROM qcm_passes WHERE rule_id=? ORDER BY id DESC", (rid,))
    post(v1, pay, {'do': 'choose', 'method': 'd17'}, token_from=pay)
    post(v1, pay, {'sender_gsm': '+21622345678', 'txn_ref': 'AFF' + uniq()}, token_from=pay)
    check('an unverified fee earns no commission', not [x for x in lines(A1['id']) if x['kind'] == 'payment'])
    pid = q1('SELECT id FROM qcm_passes WHERE ref=?', (ref,))
    post(o, M + '/qcm-review/pass', {'id': pid, 'decision': 'verify'}, token_from=M + '/qcm-review/')
    pay = [x for x in lines(A1['id']) if x['kind'] == 'payment']
    pref = q1("SELECT y.ref FROM payments y JOIN invoices v ON v.id = y.invoice_id WHERE v.source_ref = ? ORDER BY y.id DESC", (ref,))
    check('verifying the fee credits 5.00 on a 100.00 base, keyed by the payment ref', len(pay) == 1 and abs(pay[0]['commission'] - 5.0) < 0.001 and abs(pay[0]['base_amount'] - 100.0) < 0.001 and pay[0]['ref_key'] == pref, pay)
    post(o, M + '/qcm-review/pass', {'id': pid, 'decision': 'verify'}, token_from=M + '/qcm-review/')
    check('verifying again cannot pay twice', len([x for x in lines(A1['id']) if x['kind'] == 'payment']) == 1)
    try:
        run("INSERT INTO affiliate_referrals (affiliate_id,kind,user_id,ref_key,commission) VALUES (?,?,?,?,1)", (A1['id'], 'payment', nuu, pref)); dup = True
    except Exception:
        dup = False
    check('database UNIQUE(kind, ref_key) blocks a duplicate credit', not dup)

    section('owner decides referral lines (approve / reject)')
    s, h, b = call(o, 'GET', M + '/affiliates/')
    check('owner screen lists pending referral lines', 'Referrals' in b or 'referral' in b.lower())
    sg, py = lines(A1['id'], 'pending')[0], lines(A1['id'], 'pending')[1]
    call(o, 'POST', M + '/affiliates/referral', {'id': sg['id'], 'decision': 'approve'})
    check('decision without CSRF does nothing', q1('SELECT status FROM affiliate_referrals WHERE id=?', (sg['id'],)) == 'pending')
    post(a1, M + '/affiliates/referral', {'id': sg['id'], 'decision': 'approve'}, token_from=AFF)
    check('the affiliate cannot approve their own credit', q1('SELECT status FROM affiliate_referrals WHERE id=?', (sg['id'],)) == 'pending')
    post(o, M + '/affiliates/referral', {'id': sg['id'], 'decision': 'approve'}, token_from=M + '/affiliates/')
    post(o, M + '/affiliates/referral', {'id': py['id'], 'decision': 'approve'}, token_from=M + '/affiliates/')
    check('owner approves both lines (approved_at set)', [q1('SELECT status FROM affiliate_referrals WHERE id=?', (x,)) for x in (sg['id'], py['id'])] == ['approved', 'approved'] and q1('SELECT approved_at FROM affiliate_referrals WHERE id=?', (sg['id'],)))
    post(o, M + '/affiliates/referral', {'id': sg['id'], 'decision': 'reject'}, token_from=M + '/affiliates/')
    check('an already decided line cannot be flipped', q1('SELECT status FROM affiliate_referrals WHERE id=?', (sg['id'],)) == 'approved')
    rj = run("INSERT INTO affiliate_referrals (affiliate_id,kind,user_id,ref_key,commission,status) VALUES (?,?,?,?,9,'pending')", (A1['id'], 'certificate', nuu, 'c-t-' + uniq()))
    post(o, M + '/affiliates/referral', {'id': rj, 'decision': 'reject'}, token_from=M + '/affiliates/')
    check('rejecting a line marks it rejected and it is not payable', q1('SELECT status FROM affiliate_referrals WHERE id=?', (rj,)) == 'rejected')

    section('payout rules: minimum, hold days, D17 number, one open request')
    override('affiliate.min_payout', 5)
    s, h, b = post(a1, '/account/affiliate/payout', {}, token_from=AFF)
    check('approved but still inside the 14-day hold -> refused (below minimum)', q1('SELECT COUNT(*) FROM affiliate_payouts WHERE affiliate_id=?', (A1['id'],)) == 0)
    check('the refusal is explained to the affiliate (flash message)', flash(a1, 'below the minimum payout'))
    run("UPDATE affiliate_referrals SET approved_at = datetime('now','-20 days') WHERE id IN (?,?)", (sg['id'], py['id']))
    fresh = run("INSERT INTO affiliate_referrals (affiliate_id,kind,user_id,ref_key,commission,status,approved_at) VALUES (?,?,?,?,3,'approved',datetime('now'))", (A1['id'], 'certificate', nuu, 'c-f-' + uniq()))
    override('affiliate.min_payout', 50)
    post(a1, '/account/affiliate/payout', {}, token_from=AFF)
    check('7.00 available is below the default 50.00 minimum -> refused', q1('SELECT COUNT(*) FROM affiliate_payouts WHERE affiliate_id=?', (A1['id'],)) == 0)
    override('affiliate.min_payout', 5)
    run("UPDATE affiliates SET payout_gsm = '' WHERE id=?", (A1['id'],))
    post(a1, '/account/affiliate/payout', {}, token_from=AFF)
    check('no D17 number on file -> refused', q1('SELECT COUNT(*) FROM affiliate_payouts WHERE affiliate_id=?', (A1['id'],)) == 0)
    call(a1, 'POST', '/account/affiliate/payout', {})
    check('payout request without CSRF does nothing', q1('SELECT COUNT(*) FROM affiliate_payouts WHERE affiliate_id=?', (A1['id'],)) == 0)
    apply_form(a1, payout_gsm='+21622345678')
    post(a1, '/account/affiliate/payout', {}, token_from=AFF)
    P = q1("SELECT * FROM affiliate_payouts WHERE affiliate_id=? AND status='requested'", (A1['id'],))
    check('request created for 7.00 only (the fresh line is still on hold)', P and abs(P['amount'] - 7.0) < 0.001 and P['gsm'] == '+21622345678', P)
    check('the included lines are locked to the payout, the fresh one is not', q1('SELECT paid_in FROM affiliate_referrals WHERE id=?', (sg['id'],)) == P['id'] and q1('SELECT paid_in FROM affiliate_referrals WHERE id=?', (fresh,)) is None)
    check('staff are e-mailed about the request', 'Affiliate payout requested' in mail_log())
    post(a1, '/account/affiliate/payout', {}, token_from=AFF)
    check('a second request while one is open is refused', q1('SELECT COUNT(*) FROM affiliate_payouts WHERE affiliate_id=?', (A1['id'],)) == 1)
    s, h, b = call(o, 'GET', M + '/affiliates/')
    check('owner sees the payout with D17 number, amount and Mark paid / Reject', str(P['gsm']) in b and '7.00' in b and 'Mark paid' in b)

    section('owner settles the payout')
    settle(o, P['id'], decision='paid', txn_ref='ab')
    check('paid needs a real D17 reference (6-32 letters/digits): short one refused', q1('SELECT status FROM affiliate_payouts WHERE id=?', (P['id'],)) == 'requested')
    settle(o, P['id'], decision='reject', note='')
    check('rejecting needs a short reason', q1('SELECT status FROM affiliate_payouts WHERE id=?', (P['id'],)) == 'requested')
    call(o, 'POST', M + '/affiliates/payout', {'id': P['id'], 'decision': 'paid', 'txn_ref': 'D17TXN123456'})
    check('settling without CSRF does nothing', q1('SELECT status FROM affiliate_payouts WHERE id=?', (P['id'],)) == 'requested')
    post(a1, M + '/affiliates/payout', {'id': P['id'], 'decision': 'paid', 'txn_ref': 'D17TXN123456'}, token_from=AFF)
    check('the affiliate cannot pay themselves', q1('SELECT status FROM affiliate_payouts WHERE id=?', (P['id'],)) == 'requested')
    settle(o, P['id'], decision='reject', note='GSM did not match your account')
    check('reject with a note: payout rejected, lines released (paid_in cleared)', q1('SELECT status FROM affiliate_payouts WHERE id=?', (P['id'],)) == 'rejected' and q1('SELECT paid_in FROM affiliate_referrals WHERE id=?', (sg['id'],)) is None)
    check('affiliate is e-mailed the rejection reason', mail_to(a1e, 'GSM did not match'))
    post(a1, '/account/affiliate/payout', {}, token_from=AFF)
    P2 = q1("SELECT * FROM affiliate_payouts WHERE affiliate_id=? AND status='requested'", (A1['id'],))
    check('after a rejection the affiliate can ask again', bool(P2) and abs(P2['amount'] - 7.0) < 0.001)
    settle(o, P2['id'], decision='paid', txn_ref='d17txn987654')
    P2 = q1('SELECT * FROM affiliate_payouts WHERE id=?', (P2['id'],))
    check('paid: reference stored upper-case, paid_at set, lines flagged paid', P2['status'] == 'paid' and P2['txn_ref'] == 'D17TXN987654' and P2['paid_at'] and q1("SELECT COUNT(*) FROM affiliate_referrals WHERE paid_in=? AND status='paid'", (P2['id'],)) == 2)
    check('affiliate is e-mailed the D17 reference', mail_to(a1e, 'D17TXN987654'))
    settle(o, P2['id'], decision='reject', note='too late to reject')
    check('a settled payout cannot be settled again', q1('SELECT status FROM affiliate_payouts WHERE id=?', (P2['id'],)) == 'paid')
    b = call(a1, 'GET', AFF)[2]
    check('panel shows the paid total 7.00', '7.00' in b)

    section('suspension and the pause switch')
    post(o, M + '/affiliates/status', {'id': A1['id'], 'status': 'suspended'}, token_from=M + '/affiliates/')
    check('owner can suspend', aff_of(a1u)['status'] == 'suspended')
    v3 = _client(); ua(v3, 'Visitor-Three/1.0')
    call(v3, 'GET', '/?ref=' + A1['code'])
    check('a suspended affiliate\'s link sets no cookie', cookie(v3) is None)
    n_lines = len(lines(A1['id']))
    run('UPDATE users SET email_verified_at = NULL WHERE id=?', (uu,))
    run('UPDATE users SET referred_by=? WHERE id=?', (A1['id'], uu))
    sp = register_login(_client(), 'user', 'Late Referral', 'rf')
    made_users.append(sp[1])
    post(v1, '/account/qcm/buy', {'rule': rid}, token_from='/account/qcm/')
    check('a suspended affiliate earns nothing on new events from earlier referrals', len(lines(A1['id'])) == n_lines)
    s, h, b = post(a1, '/account/affiliate/payout', {}, token_from=AFF)
    check('a suspended affiliate cannot request a payout', q1("SELECT COUNT(*) FROM affiliate_payouts WHERE affiliate_id=? AND status='requested'", (A1['id'],)) == 0)
    post(o, M + '/affiliates/status', {'id': A1['id'], 'status': 'active'}, token_from=M + '/affiliates/')
    check('owner can reactivate', aff_of(a1u)['status'] == 'active')
    override('affiliate.enabled', 0)
    v4 = _client(); ua(v4, 'Visitor-Four/1.0')
    call(v4, 'GET', '/?ref=' + A1['code'])
    check('programme paused: ref link sets no cookie', cookie(v4) is None)
    a3, a3e, a3u = new_user('user', 'Blocked Applicant', 'af')
    made_users.append(a3u)
    apply_form(a3)
    check('programme paused: new applications are refused', aff_of(a3u) is None)
    b = call(a3, 'GET', AFF)[2]
    check('programme paused: the panel says so and offers no join form', 'paused' in b.lower() and 'name="pitch"' not in b)
    override('affiliate.enabled', 1)

    section('auto-approve switch')
    override('affiliate.auto_approve', 1)
    a4, a4e, a4u = new_user('user', 'Auto Approved', 'af')
    made_users.append(a4u)
    apply_form(a4)
    A4 = aff_of(a4u)
    if A4: made_aff.append(A4['id'])
    check('with auto_approve=1 a new affiliate is active at once', A4 and A4['status'] == 'active' and A4['approved_at'])
    override('affiliate.auto_approve', 0)

    section('owner screen is gated')
    s, h, b = call(guest, 'GET', M + '/affiliates/')
    check('guest -> sign-in redirect', s == 302, s)
    s, h, b = call(a1, 'GET', M + '/affiliates/')
    check('normal account cannot open the owner affiliates screen', s in (302, 403), s)
    s, h, b = call(staff('leader'), 'GET', M + '/affiliates/')
    check('leader (no affiliates permission) -> 403', s in (302, 403), s)
    s, h, b = call(ad, 'GET', M + '/affiliates/')
    check('admin role can open it', s == 200, s)
    check('no password hash / token on the screen', 'password_hash' not in b and '$2y$' not in b and '$argon' not in b)
finally:
    clear_overrides()
    for r in made_rules:
        run('DELETE FROM qcm_passes WHERE rule_id=?', (r,))
        run('DELETE FROM qcm_rules WHERE id=?', (r,))
    for a in made_aff:
        run('DELETE FROM affiliate_referrals WHERE affiliate_id=?', (a,))
        run('DELETE FROM affiliate_payouts WHERE affiliate_id=?', (a,))
        run('DELETE FROM affiliate_clicks WHERE affiliate_id=?', (a,))
    for u in made_users:
        run('DELETE FROM affiliates WHERE user_id=?', (u,))
        run('UPDATE users SET referred_by=NULL WHERE id=?', (u,))
    reset_rl()
finish('affiliate')

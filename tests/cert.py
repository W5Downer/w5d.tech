#!/usr/bin/env python3
"""Certificate end-to-end suite: owner issue/revoke (CSRF, ACL, reason), public verifier, print page, JSON API, masking,
valid/revoked/expired states, rate limit, malformed codes, holder panel.
Usage: python3 tests/cert.py [base_url]   (dev/demo DB, MAIL_DRIVER=log). Test certificates are deleted at the end.
"""
import json, os, re, sys, time
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _prog import *
from _acct import _client

CODE_RE = r'^W5D-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$'
made_users = []


def issue(o, uid, svc='networking', lvl=1):
    s, h, b = post(o, M + '/certificates/issue', {'user_id': uid, 'service': svc, 'level': lvl}, token_from=M + '/certificates/')
    return s, h


def last_code(uid):
    return q1('SELECT code FROM certificates WHERE user_id=? ORDER BY id DESC LIMIT 1', (uid,))


try:
    reset_rl()
    section('owner issues a certificate')
    o = owner()
    holder, hemail, huid = new_user('user', 'Amel Ben Trabelsi', 'ce')
    made_users.append(huid)
    s, h, b = call(o, 'GET', M + '/certificates/')
    check('owner portal certificates screen renders', s == 200 and 'certificates' in b.lower(), s)
    s, h = issue(o, huid)
    check('issue redirects back to the screen', s == 302 and '/certificates' in loc(h), (s, loc(h)))
    code = last_code(huid)
    check('code has the W5D-XXXX-XXXX-XXXX shape (no look-alike characters)', bool(code and re.match(CODE_RE, code) and not re.search('[01OIL]', code[4:].replace('-', ''))), code)
    row = q1('SELECT * FROM certificates WHERE code=?', (code,))
    check('status valid, issued_by owner, expires ~24 months out', row['status'] == 'valid' and row['issued_by'].startswith('owner:') and row['expires_at'] > time.strftime('%Y-%m-%d', time.localtime(time.time() + 86400 * 700)), row)
    check('holder gets a notification + an e-mail', q1('SELECT COUNT(*) FROM notifications WHERE user_id=?', (huid,)) >= 1 and mail_to(hemail, code))
    s, h, b = call(holder, 'GET', '/account/certificates/')
    check('holder panel lists the code, level and print link', s == 200 and code in b and f'/certificate/{code}/' in b, s)

    section('owner issue: CSRF, ACL, validation')
    n = q1('SELECT COUNT(*) FROM certificates')
    s, h, b = call(o, 'POST', M + '/certificates/issue', {'user_id': huid, 'service': 'networking', 'level': 1})
    check('issue without CSRF token does nothing', q1('SELECT COUNT(*) FROM certificates') == n, s)
    for bad in ({'user_id': 999999, 'service': 'networking', 'level': 1}, {'user_id': huid, 'service': '../x', 'level': 1}, {'user_id': huid, 'service': 'networking', 'level': 9}, {'user_id': huid, 'service': 'no-such-service', 'level': 1}):
        issue_s = post(o, M + '/certificates/issue', bad, token_from=M + '/certificates/')[0]
    check('unknown user / path-like service / level 9 issue nothing', q1('SELECT COUNT(*) FROM certificates') == n)
    s, h, b = post(holder, M + '/certificates/issue', {'user_id': huid, 'service': 'networking', 'level': 2}, token_from='/account/certificates/')
    check('a normal account cannot issue (redirect/403)', s in (302, 403) and q1('SELECT COUNT(*) FROM certificates') == n, s)
    for role in ('leader', 'manager'):
        sc = staff(role)
        s, h, b = post(sc, M + '/certificates/issue', {'user_id': huid, 'service': 'networking', 'level': 2}, token_from='/account/')
        check(f'{role} (no certificates permission) cannot issue', q1('SELECT COUNT(*) FROM certificates') == n, s)
    ad = staff('admin')
    s, h, b = call(ad, 'GET', M + '/certificates/')
    check('admin role can open the certificates screen', s == 200, s)
    s, h, b = post(ad, M + '/certificates/issue', {'user_id': huid, 'service': 'networking', 'level': 2}, token_from=M + '/certificates/')
    check('admin role can issue (certificates.update)', s == 302 and q1('SELECT COUNT(*) FROM certificates') == n + 1, s)
    code2 = last_code(huid)
    check('issuing a second level keeps the first valid', q1('SELECT status FROM certificates WHERE code=?', (code,)) == 'valid')
    s, h = issue(o, huid, 'networking', 1)
    check('re-issuing the same service+level renews: old one is revoked "renewed"', q1('SELECT revoke_reason FROM certificates WHERE code=?', (code,)) == 'renewed' and last_code(huid) != code)
    code = last_code(huid)

    section('public verifier page')
    guest = _client()
    reset_rl()
    s, h, b = call(guest, 'GET', '/certification/verify/')
    check('empty form: 200, indexable, has labelled input', s == 200 and 'for="f-code"' in b and 'noindex' not in b, s)
    s, h, b = call(guest, 'GET', f'/certification/verify/?code={code}')
    check('valid code -> "valid" state', s == 200 and 'data-cert-state="valid"' in b, s)
    check('holder shown as first name + last initial only', 'Amel T.' in b and 'Trabelsi' not in b)
    check('no e-mail address on the public page', hemail not in b and '@mail.test' not in b)
    check('result page is noindex', 'noindex' in b)
    s, h, b = call(guest, 'GET', f'/certification/verify/?code={code.lower()}')
    check('lower-case code is accepted (normalised)', 'data-cert-state="valid"' in b)
    s, h, b = call(guest, 'GET', '/certification/verify/?code=W5D-AAAA-BBBB-CCCC')
    check('unknown well-formed code -> missing state (role=alert)', 'data-cert-state="missing"' in b and 'role="alert"' in b, s)
    for junk in ('x', '<script>alert(1)</script>', "W5D-'; DROP TABLE certificates;--", 'W5D-' + 'A' * 400, '%00'):
        s, h, b = call(guest, 'GET', '/certification/verify/?code=' + urllib.parse.quote(junk))
        check(f'junk code {junk[:18]!r}: no 5xx, no reflected markup', s < 500 and '<script>alert(1)' not in b, s)
    check('certificates table survived the injection probe', q1('SELECT COUNT(*) FROM certificates') >= 3)
    for lang in ('fr', 'ar'):
        g = _client()
        call(g, 'GET', f'/language/{lang}')
        s, h, b = call(g, 'GET', f'/certification/verify/?code={code}')
        want = 'Certificat valide' if lang == 'fr' else 'شهادة صالحة'
        check(f'{lang}: verifier speaks {lang} and dir is right', want in b and (lang != 'ar' or 'dir="rtl"' in b), s)

    section('JSON API')
    reset_rl()
    s, h, b = call(guest, 'GET', f'/api/certificates/{code}.json')
    j = json.loads(b) if s == 200 else {}
    rec = j.get('certificate', {})
    check('200 with the public record', s == 200 and rec.get('code') == code and rec.get('status') == 'valid' and rec.get('issuer') == 'W5D', s)
    check('record has exactly the public fields (no user id / e-mail / attempt)', set(rec) == {'code', 'status', 'holder', 'service', 'service_title', 'level', 'level_label', 'score', 'issued_at', 'expires_at', 'issuer'}, sorted(rec))
    check('holder is masked in JSON too', rec.get('holder') == 'Amel T.')
    hl = {k.lower(): v for k, v in h.items()}
    check('headers: json, noindex, no-store', 'json' in hl.get('content-type', '') and 'noindex' in hl.get('x-robots-tag', '') and 'no-store' in hl.get('cache-control', ''), hl)
    s, h, b = call(guest, 'GET', '/api/certificates/W5D-AAAA-BBBB-CCCC.json')
    check('unknown code -> 404 {"error":"not_found"}', s == 404 and json.loads(b).get('error') == 'not_found', s)
    s, h, b = call(guest, 'GET', '/api/certificates/not-a-code.json')
    check('malformed code -> 404, not 500', s == 404, s)
    s, h, b = call(guest, 'GET', f'/api/certificates/{code.lower()}.json')
    check('lower-case code works on the API', s == 200)

    section('print page')
    s, h, b = call(guest, 'GET', f'/certificate/{code}/')
    hl = {k.lower(): v for k, v in h.items()}
    check('print page renders: holder mask, code, verify URL, one <h1>', s == 200 and 'Amel T.' in b and code in b and '/certification/verify/?code=' in b and b.count('<h1') == 1, s)
    check('print page is noindex (header + meta)', 'noindex' in hl.get('x-robots-tag', '') and 'noindex' in b)
    check('print page keeps the honest disclaimer (not a job / not income)', 'not a promise of income' in b)
    check('print button is not inline-script (CSP-safe data-print)', 'data-print' in b and 'onclick' not in b)
    s, h, b = call(guest, 'GET', '/certificate/W5D-AAAA-BBBB-CCCC/')
    check('print page of an unknown code -> 404', s == 404, s)

    section('revoke: reason, CSRF, ACL, public effect')
    cid = q1('SELECT id FROM certificates WHERE code=?', (code,))
    s, h, b = call(o, 'POST', M + '/certificates/revoke', {'id': cid, 'reason': 'No csrf token here'})
    check('revoke without CSRF does nothing', q1('SELECT status FROM certificates WHERE id=?', (cid,)) == 'valid', s)
    post(o, M + '/certificates/revoke', {'id': cid, 'reason': 'abc'}, token_from=M + '/certificates/')
    check('a reason shorter than 5 characters is refused', q1('SELECT status FROM certificates WHERE id=?', (cid,)) == 'valid')
    post(holder, M + '/certificates/revoke', {'id': cid, 'reason': 'holder tries to revoke'}, token_from='/account/certificates/')
    check('a normal account cannot revoke', q1('SELECT status FROM certificates WHERE id=?', (cid,)) == 'valid')
    s, h, b = post(o, M + '/certificates/revoke', {'id': cid, 'reason': 'Cheating detected in the round'}, token_from=M + '/certificates/')
    check('owner revokes with a reason', s == 302 and q1('SELECT status FROM certificates WHERE id=?', (cid,)) == 'revoked')
    reset_rl()
    s, h, b = call(guest, 'GET', f'/certification/verify/?code={code}')
    check('verifier now says revoked (amber, not green)', 'data-cert-state="revoked"' in b and 'Revoked certificate' in b and 'emerald' not in b.split('data-cert-state')[1][:200], s)
    s, h, b = call(guest, 'GET', f'/api/certificates/{code}.json')
    check('API reports status revoked (still 200: the record exists)', s == 200 and json.loads(b)['certificate']['status'] == 'revoked')
    s, h, b = call(guest, 'GET', f'/certificate/{code}/')
    check('print page stamps REVOKED', 'REVOKED' in b and 'data-cert-status="revoked"' in b)
    post(o, M + '/certificates/revoke', {'id': cid, 'reason': 'Second revoke attempt'}, token_from=M + '/certificates/')
    check('revoking twice keeps the first reason', q1('SELECT revoke_reason FROM certificates WHERE id=?', (cid,)) == 'Cheating detected in the round')

    section('expiry is computed')
    run("UPDATE certificates SET expires_at = datetime('now','-1 day') WHERE code=?", (code2,))
    reset_rl()
    s, h, b = call(guest, 'GET', f'/certification/verify/?code={code2}')
    check('past expires_at -> "expired" state, no manual flag needed', 'data-cert-state="expired"' in b and 'Expired certificate' in b, s)
    check('expired certificate does not count for the holder level', q1("SELECT COUNT(*) FROM certificates WHERE user_id=? AND status='valid' AND expires_at >= datetime('now')", (huid,)) <= 1)

    section('verifier rate limit (per IP)')
    reset_rl()
    limit = int(q1("SELECT value FROM settings WHERE key='cert.verify_per_10min'") or 30) if q1("SELECT name FROM sqlite_master WHERE name='settings'") else 30
    codes_seen = []
    for i in range(limit + 3):
        s, h, b = call(guest, 'GET', f'/api/certificates/{code2}.json')
        codes_seen.append(s)
    check(f'API answers 429 once {limit} checks are used up', codes_seen[:limit].count(200) == limit and codes_seen[-1] == 429, codes_seen[-5:])
    s, h, b = call(guest, 'GET', f'/certification/verify/?code={code2}')
    check('page shares the same bucket and says "too many checks"', s == 429 and 'Too many checks' in b, s)
    s, h, b = call(guest, 'GET', '/certification/verify/')
    check('the empty form still opens while limited', s == 200)
    reset_rl()

finally:
    for uid in made_users:
        run('DELETE FROM certificates WHERE user_id=?', (uid,))
    run("DELETE FROM certificates WHERE issued_by LIKE 'owner:%' AND user_id NOT IN (SELECT id FROM users)")
    reset_rl()
finish('cert')

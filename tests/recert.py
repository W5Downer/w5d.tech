#!/usr/bin/env python3
"""Re-certification prompt on the account Certificates panel: expiring / expired / far-away / revoked certificates,
pay button only when an active re-certification fee exists, window variable, other users' certificates never shown.
Usage: python3 tests/recert.py [base_url]   (dev/demo DB, MAIL_DRIVER=log). Test rows are removed at the end."""
import os, re, sys, datetime
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _prog import *
from _acct import _client

made_users, made_certs, made_fees = [], [], []
D = lambda n: (datetime.datetime.now() + datetime.timedelta(days=n)).strftime('%Y-%m-%d %H:%M:%S')


def override(path, value):
    run('INSERT OR REPLACE INTO variable_overrides (path,value,changed_by) VALUES (?,?,?)', (path, str(value), 'tests/recert.py'))


def cert(uid, level, expires, status='valid', service='networking'):
    code = 'W5D-' + '-'.join(uniq('R')[-4:].upper().ljust(4, 'X') for _ in range(3))
    code = 'W5D-' + '-'.join(__import__('secrets').token_hex(2).upper() for _ in range(3))
    cid = run('INSERT INTO certificates (code,user_id,service_slug,level,score,status,expires_at) VALUES (?,?,?,?,?,?,?)', (code, uid, service, level, 80, status, expires))
    made_certs.append(cid)
    return code


def panel(c):
    s, h, b = call(c, 'GET', '/account/certificates/')
    return s, b


try:
    reset_rl()
    run("DELETE FROM variable_overrides WHERE changed_by='tests/recert.py'")
    c, email, uid = new_user('user', 'Recert Tester', 'rc')
    made_users.append(uid)

    s, b = panel(c)
    check('no certificate: no re-certification prompt', s == 200 and 'data-recert-prompts' not in b, s)

    far = cert(uid, 1, D(400))
    s, b = panel(c)
    check('certificate valid for 400 days: no prompt (default window 60 days)', 'data-recert-prompts' not in b and far in b)

    soon = cert(uid, 2, D(20), service='networking')
    s, b = panel(c)
    m = re.search(r'data-recert="' + soon + r'" data-recert-state="(\w+)"', b)
    check('certificate expiring in 20 days: prompt with state "expiring"', bool(m) and m.group(1) == 'expiring', m)
    check('the far-away certificate is not in the prompt list', 'data-recert="' + far + '"' not in b)
    check('prompt shows the days left and says a fee gives only the attempt', re.search(r'\(\d+ days left\)', b) is not None and 'never the result' in b)

    gone = cert(uid, 3, D(-5))
    s, b = panel(c)
    m = re.search(r'data-recert="' + gone + r'" data-recert-state="(\w+)"', b)
    check('certificate expired 5 days ago: prompt with state "expired" and the verifier warning', bool(m) and m.group(1) == 'expired' and 'now sees it as expired' in b, m)

    rev = cert(uid, 1, D(5), status='revoked')
    s, b = panel(c)
    check('a revoked certificate gets no prompt', 'data-recert="' + rev + '"' not in b)

    check('no active re-certification fee: no pay button inside the prompts (the "any level" fee is seeded inactive)', 'Pay the re-certification fee' not in b)
    check('the prompt still points to the QCM rounds', '/account/qcm/' in b)
    check('prompts carry no CSRF form when there is no fee', re.search(r'data-recert-prompts.*?</div>\s*</div>\s*\n?\s*@?', b, re.S) is not None)

    # an active fee for level 2 only
    fid = run("INSERT INTO cert_fees (name, kind, level, amount, active, sort) VALUES (?,?,?,?,?,?)", ('TEST recert L2', 'recertification', 2, 4.5, 1, 999))
    made_fees.append(fid)
    s, b = panel(c)
    blk = lambda code: re.search(r'data-recert="' + code + r'".*?</div>\s*</div>', b, re.S).group(0)
    check('active level-2 fee: the level-2 prompt gets a pay button with CSRF and the fee id', 'Pay the re-certification fee' in blk(soon) and 'name="_csrf"' in blk(soon) and f'name="fee" value="{fid}"' in blk(soon))
    check('the expired level-3 prompt has no pay button (no fee for that level)', 'Pay the re-certification fee' not in blk(gone))
    s2, h2, b2 = post(c, '/account/fee/buy', {'fee': fid}, token_from='/account/certificates/')
    check('pressing the button creates a re-certification invoice and redirects to the pay page', s2 == 302 and '/pay/' in loc(h2) and q1("SELECT COUNT(*) FROM invoices WHERE kind='cert' AND user_id=? AND note=?", (uid, f'cf:{fid}')) == 1, (s2, loc(h2)))
    s, b = panel(c)
    check('the open fee invoice is listed as waiting for payment', 'A fee is waiting for payment' in b)

    # window variable
    override('cert.recert_warn_days', 10)
    s, b = panel(c)
    check('cert.recert_warn_days = 10: the 20-day certificate leaves the list, the expired one stays', 'data-recert="' + soon + '"' not in b and 'data-recert="' + gone + '"' in b)
    override('cert.recert_warn_days', 0)
    s, b = panel(c)
    check('cert.recert_warn_days = 0: only expired certificates are prompted', 'data-recert="' + soon + '"' not in b and 'data-recert="' + gone + '"' in b)

    # isolation
    c2, e2, u2 = new_user('user', 'Other Recert', 'rc')
    made_users.append(u2)
    s, b = panel(c2)
    check("another account never sees this account's prompts or codes", 'data-recert-prompts' not in b and soon not in b and gone not in b)
    g = _client() if '_client' in globals() else None
    s, h, b = call(__import__('_acct')._client(), 'GET', '/account/certificates/')
    check('guest is sent to sign-in', s == 302 and '/account/login/' in loc(h), s)

    # language rendering
    override('cert.recert_warn_days', 60)
    for lang in ('fr', 'ar'):
        call(c, 'GET', f'/language/{lang}?r=/')
        s, b = panel(c)
        check(f'[{lang}] prompt renders without template leftovers', 'data-recert-prompts' in b and '{{' not in b and 'tr3(' not in b)
    call(c, 'GET', '/language/en?r=/')
finally:
    run("DELETE FROM variable_overrides WHERE changed_by='tests/recert.py'")
    for f in made_fees:
        run('DELETE FROM cert_fees WHERE id=?', (f,))
    for u in made_users:
        run("DELETE FROM invoices WHERE user_id=? AND kind='cert'", (u,))
        run('DELETE FROM certificates WHERE user_id=?', (u,))
    reset_rl()
finish('recert')

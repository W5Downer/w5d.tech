"""Shared helpers for tests/qcm.py, tests/cert.py, tests/affiliate.py (stdlib only; dev/demo DB, MAIL_DRIVER=log).

Direct DB access is for test set-up and assertions only (sqlite3 module, WAL-safe); every behaviour under test goes through HTTP.
"""
import glob, json, os, re, shutil, sqlite3, sys, time, urllib.parse
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _acct import _client, _call, _csrf, verify_token

BASE = (next((a for a in sys.argv[1:] if a.startswith('http')), 'http://127.0.0.1:8080')).rstrip('/')
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DBPATH = os.path.join(ROOT, 'storage/w5d.sqlite')
env = dict(l.split('=', 1) for l in open(os.path.join(ROOT, '.env')).read().splitlines() if '=' in l and not l.startswith('#'))
M = '/' + env.get('ADMIN_URL_PATH', 'panel-x9z').strip('/')
_n = [0]
ok = bad = 0


def check(name, cond, extra=''):
    global ok, bad
    if cond:
        ok += 1
        print(f'  ok   {name}')
    else:
        bad += 1
        print(f'  FAIL {name} {extra}')


def finish(label):
    print(f'{label}: {ok} passed, {bad} failed')
    sys.exit(1 if bad else 0)


def section(t):
    print(t)


def db():
    c = sqlite3.connect(DBPATH, timeout=30)
    c.row_factory = sqlite3.Row
    return c


def q1(sql, args=()):
    c = db()
    try:
        r = c.execute(sql, args).fetchone()
        return None if r is None else (r[0] if len(r) == 1 else dict(r))
    finally:
        c.close()


def qall(sql, args=()):
    c = db()
    try:
        return [dict(r) for r in c.execute(sql, args).fetchall()]
    finally:
        c.close()


def run(sql, args=()):
    c = db()
    try:
        cur = c.execute(sql, args)
        c.commit()
        return cur.lastrowid
    finally:
        c.close()


def uniq(prefix=''):
    _n[0] += 1
    return prefix + str(int(time.time() * 1000))[-8:] + str(_n[0])


def reset_rl():
    shutil.rmtree(os.path.join(ROOT, 'storage/cache/rl'), ignore_errors=True)


def call(c, method, path, data=None):
    return _call(c, BASE, method, path, data)


def token(c, path='/account/qcm/'):
    return _csrf(c, BASE, path)


def post(c, path, data, token_from='/account/qcm/', csrf=True):
    pairs = list(data.items()) if isinstance(data, dict) else list(data)
    if csrf:
        pairs = [('_csrf', token(c, token_from))] + pairs
    return call(c, 'POST', path, pairs)


def register_login(c, role='user', name='Prog Tester', prefix='pg'):
    """Register + verify + sign in using client c (keeps cookies such as w5d_ref). Returns (email, uid)."""
    reset_rl()
    run_id = uniq()
    email = f'{prefix}{run_id}@mail.test'
    pw = 'Str0ngPass-' + run_id
    t = _csrf(c, BASE, '/account/register/')
    call(c, 'POST', '/account/register/', {'_csrf': t, 'name': name, 'email': email, 'password': pw, 'password2': pw, 'role': role, 'accept': '1'})
    tok = verify_token(ROOT, email)
    if not tok:
        raise RuntimeError('verification mail not found')
    call(c, 'GET', f'/account/verify/{tok}/')
    t = _csrf(c, BASE, '/account/login/')
    s, h, b = call(c, 'POST', '/account/login/', {'_csrf': t, 'identifier': email, 'password': pw})
    if s != 302:
        raise RuntimeError(f'login failed ({s})')
    return email, q1('SELECT id FROM users WHERE email = ?', (email,))


def new_user(role='user', name='Prog Tester', prefix='pg'):
    c = _client()
    email, uid = register_login(c, role, name, prefix)
    return c, email, uid


def staff(role):
    """Team member with `role`: created by SQL, signed in through the real e-mail code flow."""
    from _staff import staff_client
    return staff_client(BASE, role, ROOT, 'st', M)


def owner():
    from _staff import staff_login
    return staff_login(BASE, ROOT, None, M)


def loc(h):
    return h.get('Location') or h.get('location') or ''


def mail_log():
    t = ''
    for f in sorted(glob.glob(os.path.join(ROOT, 'storage/logs/mail-*.log'))):
        t += open(f, encoding='utf-8', errors='replace').read()
    return t


def mail_to(email, needle):
    return any(('to=' + email) in b and needle in b for b in mail_log().split('] ['))

#!/usr/bin/env python3
"""Break-glass code script + Users screen restriction. Usage: python3 tests/breakglass.py [base_url]
bin/staff-code.php: prints a working one-time code for an authorised team address without mail; refuses foreign/unknown/inactive addresses and web requests;
the code works once, a new code (script or mail) kills the old one; the audit log records it; the code itself is never logged.
Users screen (/users/): lists public accounts only, cannot open or save a team member, cannot promote a public account to a team role."""
import os, re, sqlite3, subprocess, sys, time
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _acct import _client, _call, _csrf, signed_in
from _staff import ROOT, MOUNT, OWNER_EMAIL, ENV, make_team, reset_rl, staff_login
BASE = next((a for a in sys.argv[1:] if a.startswith('http')), 'http://127.0.0.1:8080').rstrip('/')
ok = bad = 0
def check(n, c, extra=''):
    global ok, bad
    if c: ok += 1
    else: bad += 1; print('  FAIL', n, extra)

DB = os.path.join(ROOT, 'storage/w5d.sqlite')
def q(sql, a=()):
    c = sqlite3.connect(DB, timeout=30)
    try:
        r = c.execute(sql, a).fetchall(); c.commit(); return r
    finally: c.close()

def script(*args):
    r = subprocess.run(['php', 'bin/staff-code.php', *args], cwd=ROOT, capture_output=True, text=True, timeout=60)
    return r.returncode, r.stdout, r.stderr

def login_with(email, code, client=None):
    reset_rl()
    c = client or _client()
    t = _csrf(c, BASE, MOUNT + '/login/')
    _call(c, BASE, 'POST', MOUNT + '/login/', {'_csrf': t, 'do': 'request', 'email': email})
    return c, t

# ------------------------------------------------------------ the script
rc, out, err = script()
check('no argument: usage + exit 2', rc == 2 and 'usage' in err.lower())
rc, out, err = script('someone@gmail.com')
check('foreign domain refused (exit 1, no code printed)', rc == 1 and not re.search(r'\d{3} \d{3}', out))
rc, out, err = script('ghost%d@w5d.tech' % int(time.time()))
check('unknown team address refused: it cannot create access', rc == 1 and not re.search(r'\d{3} \d{3}', out) and 'not authorised' in err)

inactive = make_team('manager', 'bg')
q("UPDATE users SET active = 0 WHERE email = ?", (inactive,))
rc, out, err = script(inactive)
check('inactive team user refused', rc == 1 and not re.search(r'\d{3} \d{3}', out))

pub = f'pub{int(time.time())}@w5d.tech'
q("INSERT INTO users(country_id,email,name,role_slug,active,email_verified_at,lang) VALUES('tn',?,?,?,1,datetime('now'),'en')", (pub, 'Public on team domain', 'investor'))
rc, out, err = script(pub)
check('public-role account on the team domain refused', rc == 1 and not re.search(r'\d{3} \d{3}', out))

# owner and a team member get a code that works
for label, email in (('owner', OWNER_EMAIL), ('manager', make_team('manager', 'bg')), ('member', make_team('member', 'bg'))):
    rc, out, err = script(email)
    m = re.search(r'(\d{3}) (\d{3})', out)
    check(f'{label}: code printed on stdout, exit 0', rc == 0 and bool(m), out + err)
    check(f'{label}: hint goes to stderr, stdout stays one line', out.count('\n') == 1 and 'valid' in out)
    code = (m.group(1) + m.group(2)) if m else '000000'
    reset_rl()
    c = _client(); t = _csrf(c, BASE, MOUNT + '/login/')
    # no mail request first: the console code is accepted straight on the verify step after the address is entered
    _call(c, BASE, 'POST', MOUNT + '/login/', {'_csrf': t, 'do': 'request', 'email': email})  # this ALSO mails a new code and kills the console one...
    s, h, b = _call(c, BASE, 'POST', MOUNT + '/login/', {'_csrf': t, 'do': 'verify', 'code': code})
    check(f'{label}: asking for a mail code afterwards invalidates the console code (one live code at a time)', s != 302)
    rc, out, err = script(email)
    m = re.search(r'(\d{3}) (\d{3})', out); code = m.group(1) + m.group(2)
    # simulate "mail is down": the page is on the code step because the address was entered earlier in the session
    s, h, b = _call(c, BASE, 'POST', MOUNT + '/login/', {'_csrf': t, 'do': 'verify', 'code': code})
    check(f'{label}: the console code signs in', s == 302, str(s))
    s, h, b = _call(c, BASE, 'GET', MOUNT + '/')
    if label == 'member':
        check('member: signed in, but kept out of the portal by design (lands on the public account)', s in (301, 302, 403), str(s))
    else:
        check(f'{label}: portal opens', s == 200, str(s))
    # single use
    c2 = _client(); t2 = _csrf(c2, BASE, MOUNT + '/login/')
    _call(c2, BASE, 'POST', MOUNT + '/login/', {'_csrf': t2, 'do': 'verify', 'code': code})
    s, h, b = _call(c2, BASE, 'POST', MOUNT + '/login/', {'_csrf': t2, 'do': 'verify', 'code': code})
    check(f'{label}: the code works once only', s != 302)

# a second script run kills the first code
em = make_team('leader', 'bg')
rc, o1, _ = script(em); rc, o2, _ = script(em)
c1 = re.search(r'(\d{3}) (\d{3})', o1); c2_ = re.search(r'(\d{3}) (\d{3})', o2)
check('two runs give two codes (or at least both succeed)', bool(c1) and bool(c2_))
if c1 and c2_ and (c1.group(1) + c1.group(2)) != (c2_.group(1) + c2_.group(2)):
    reset_rl()
    c = _client(); t = _csrf(c, BASE, MOUNT + '/login/')
    s, h, b = _call(c, BASE, 'POST', MOUNT + '/login/', {'_csrf': t, 'do': 'verify', 'code': c1.group(1) + c1.group(2)})
    check('the older console code is dead after a newer one', s != 302)

# five wrong tries burn the code
em = make_team('member', 'bg')
rc, o, _ = script(em); code = ''.join(re.search(r'(\d{3}) (\d{3})', o).groups())
reset_rl()
c = _client(); t = _csrf(c, BASE, MOUNT + '/login/')
_call(c, BASE, 'POST', MOUNT + '/login/', {'_csrf': t, 'do': 'request', 'email': 'nobody@w5d.tech'})  # sets the pending address in the session? (unknown -> neutral)
# set the pending address through a real request for this user, then replace its code by the console one
_call(c, BASE, 'POST', MOUNT + '/login/', {'_csrf': t, 'do': 'request', 'email': em})
rc, o, _ = script(em); code = ''.join(re.search(r'(\d{3}) (\d{3})', o).groups())
wrong = '000000' if code != '000000' else '111111'
for _ in range(5):
    _call(c, BASE, 'POST', MOUNT + '/login/', {'_csrf': t, 'do': 'verify', 'code': wrong})
s, h, b = _call(c, BASE, 'POST', MOUNT + '/login/', {'_csrf': t, 'do': 'verify', 'code': code})
check('five wrong guesses burn the console code too', s != 302)

# audit + no code in logs
rows = q("SELECT actor, subject, detail FROM audit_log WHERE action = 'login.code.console' ORDER BY id DESC LIMIT 20")
check('audit log records console issuance with actor "console"', bool(rows) and all(r[0] == 'console' for r in rows))
check('audit log never contains a code', not any(re.search(r'\b\d{6}\b', r[2] or '') for r in rows))
import glob
logs = ''.join(open(f, encoding='utf-8', errors='replace').read() for f in glob.glob(os.path.join(ROOT, 'storage/logs/staff-code-*.log')))
check('console log has address + time, no code digits after the address', 'code issued for' in logs and not re.search(r'issued for \S+ by \S+ \d', logs) and not re.search(r'\b\d{3} \d{3}\b', logs))
src = open(os.path.join(ROOT, 'bin/staff-code.php'), encoding='utf-8').read()
check('script refuses to run outside the CLI', "PHP_SAPI !== 'cli'" in src)
s, h, b = _call(_client(), BASE, 'GET', '/bin/staff-code.php')
check('script is not reachable over HTTP', s == 404 or 'Code for' not in b)
check('script is outside the web root', not os.path.exists(os.path.join(ROOT, 'htdocs/bin/staff-code.php')))

# ------------------------------------------------------------ Users screen
reset_rl()
o = staff_login(BASE, ROOT)
team = make_team('manager', 'bgu')
tid = q("SELECT id FROM users WHERE email = ?", (team,))[0][0]
acct, pubmail = signed_in(BASE, ROOT, role='investor', prefix='bgu')
pid = q("SELECT id FROM users WHERE email = ?", (pubmail,))[0][0]
s, h, b = _call(o, BASE, 'GET', MOUNT + '/users/')
check('users list opens for the owner', s == 200)
check('users list shows the public account', pubmail in b)
check('users list does NOT show the team member', team not in b and OWNER_EMAIL not in b)
check('users list shows no team-role rows', not re.search(r'<td[^>]*>\s*(admin|leader|manager|member)\s*</td>', b))
s, h, b = _call(o, BASE, 'GET', MOUNT + f'/users/{tid}/edit/')
check('team member cannot be opened in the Users screen', s in (302, 403, 404), str(s))
s, h, b = _call(o, BASE, 'GET', MOUNT + f'/users/{pid}/edit/')
check('public account opens for edit', s == 200, str(s))
opts = re.findall(r'<option value="([^"]*)"', b)
check('role options are public roles only', set(opts) >= {'investor', 'customer', 'user'} and not set(opts) & {'admin', 'leader', 'manager', 'member'}, str(opts))
t = re.search(r'name="_csrf" value="([0-9a-f]+)"', b).group(1)
for role in ('admin', 'leader', 'manager', 'member'):
    s2, h2, b2 = _call(o, BASE, 'POST', MOUNT + '/users/save', {'_csrf': t, 'id': str(pid), 'name': 'Promoted', 'role_slug': role, 'active': '1'})
    now = q("SELECT role_slug FROM users WHERE id = ?", (pid,))[0][0]
    check(f'promotion of a public account to {role} through the Users screen is refused', now not in ('admin', 'leader', 'manager', 'member'), now)
s2, h2, b2 = _call(o, BASE, 'POST', MOUNT + '/users/save', {'_csrf': t, 'id': str(tid), 'name': 'Hijack', 'role_slug': 'user', 'active': '1'})
row = q("SELECT name, role_slug FROM users WHERE id = ?", (tid,))[0]
check('a team member cannot be changed or demoted through the Users screen', row[1] == 'manager' and row[0] != 'Hijack', str(row))
s2, h2, b2 = _call(o, BASE, 'POST', MOUNT + '/users/save', {'_csrf': t, 'id': str(pid), 'name': 'Renamed Public', 'role_slug': 'customer', 'active': '1'})
row = q("SELECT name, role_slug FROM users WHERE id = ?", (pid,))[0]
check('a normal edit of a public account still works', row == ('Renamed Public', 'customer'), str(row))
# the Team menu still handles team roles
s, h, b = _call(o, BASE, 'GET', MOUNT + '/team/')
check('Team menu still lists team members', s == 200 and team in b)

print(f'\nbreakglass: {ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

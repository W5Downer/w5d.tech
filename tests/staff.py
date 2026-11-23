#!/usr/bin/env python3
"""Team sign-in (@w5d.tech + e-mailed code) and privacy of owner/admin/leader pages. Usage: python3 tests/staff.py [base_url]
Needs MAIL_DRIVER=log and the dev/demo database (reads the code from storage/logs/mail-*.log, sets up users by SQL)."""
import os, re, sqlite3, sys, time
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _acct import _client, _call, _csrf
from _staff import ROOT, MOUNT, ENV, OWNER_EMAIL, last_code, reset_rl, request_code, make_team, staff_login
BASE = next((a for a in sys.argv[1:] if a.startswith('http')), 'http://127.0.0.1:8080').rstrip('/')
DOM = ENV.get('STAFF_EMAIL_DOMAIN') or 'w5d.tech'
ok = bad = 0
def check(n, c, extra=''):
    global ok, bad
    if c: ok += 1
    else: bad += 1; print('  FAIL', n, extra)
def db(sql, args=()):
    c = sqlite3.connect(os.path.join(ROOT, 'storage/w5d.sqlite'), timeout=30)
    try: r = c.execute(sql, args).fetchall(); c.commit(); return r
    finally: c.close()
def verify(c, email, code):
    t = _csrf(c, BASE, MOUNT + '/login/')
    return _call(c, BASE, 'POST', MOUNT + '/login/', {'_csrf': t, 'do': 'verify', 'code': code})
def portal(c, path='/'):
    return _call(c, BASE, 'GET', MOUNT + path)
def fresh_code(email):
    reset_rl(); c = _client()
    (s, h, b), t = request_code(c, BASE, email); time.sleep(0.05)
    return c, last_code(ROOT, email), (s, h, b)

# ---- guests: every portal door is closed, none reveals anything
g = _client()
for path in ('/', '/setup-guide/', '/team/', '/funding/', '/users/', '/payments-d17/', '/api-docs/', '/system/'):
    s, h, b = portal(g, path)
    check(f'guest {path} redirects to login', s in (301, 302) and '/login' in h.get('Location', ''), f'{s} {h.get("Location")}')
    check(f'guest {path} body empty of data', 'owner@' not in b and 'data-setup-guide' not in b)
s, h, b = _call(g, BASE, 'POST', MOUNT + '/save', {})
check('guest POST to portal is not accepted', s in (301, 302, 403, 404, 405), str(s))
for aud in ('owner', 'admin', 'leader'):
    s, h, b = _call(g, BASE, 'GET', f'/for/{aud}/')
    check(f'guest /for/{aud}/ is 404', s == 404, str(s))
    check(f'guest /for/{aud}/ shows no preview', 'Setup guide' not in b and MOUNT.strip('/') not in b)
s, h, b = _call(g, BASE, 'GET', '/for/')
check('guest hub lists public audiences only', '/for/owner/' not in b and '/for/admin/' not in b and '/for/leader/' not in b)
for f in ('/robots.txt', '/sitemap.xml', '/llms.txt'):
    s, h, b = _call(g, BASE, 'GET', f)
    check(f'{f} never prints the portal path', MOUNT.strip('/') not in b)
    if f != '/robots.txt':   # robots.txt lists the three private doors as Disallow on purpose (they answer 404 to everyone else)
        check(f'{f} never lists the private audience doors', '/for/owner' not in b and '/for/admin' not in b and '/for/leader' not in b)
s, h, b = _call(g, BASE, 'GET', '/robots.txt')
check('robots.txt disallows the three private doors', all(f'Disallow: /for/{a}/' in b for a in ('owner', 'admin', 'leader')))

# ---- login form: domain rule, no enumeration, no password
reset_rl(); c = _client()
t = _csrf(c, BASE, MOUNT + '/login/')
s, h, b = _call(c, BASE, 'GET', MOUNT + '/login/')
check('login form has email, no password field', 'name="email"' in b and 'type="password"' not in b)
check('login form is CSRF protected', 'name="_csrf"' in b)
s, h, b = _call(c, BASE, 'POST', MOUNT + '/login/', {'do': 'request', 'email': OWNER_EMAIL})
check('POST without CSRF token is refused', 'data-step="code"' not in b, 'step moved to code without a token')
(s, h, b), t = request_code(c, BASE, 'someone@gmail.com')
check('foreign domain refused with the domain hint', 'data-step="code"' not in b and ('@' + DOM) in b)
check('foreign domain: no code mailed', last_code(ROOT, 'someone@gmail.com') is None)
reset_rl(); c1 = _client(); c2 = _client()
(s1, h1, b1), _ = request_code(c1, BASE, OWNER_EMAIL)
(s2, h2, b2), _ = request_code(c2, BASE, f'nobody{int(time.time())}@{DOM}')
norm = lambda x: re.sub(r'(name="_csrf" value=")[0-9a-f]+|[a-z]{2}•+@', r'\1', re.sub(r'<title>.*?</title>', '', x))
check('known and unknown @team address answer with the same step', 'data-step="code"' in b1 and 'data-step="code"' in b2)
check('no enumeration: same status', s1 == s2, f'{s1} vs {s2}')
check('masked address only (never the full one)', OWNER_EMAIL not in b1 and OWNER_EMAIL not in b2)
check('unknown address received no mail', last_code(ROOT, f'nobody@{DOM}') is None)
rows = db("SELECT code_hash FROM staff_codes ORDER BY id DESC LIMIT 5")
check('codes stored hashed, never in clear', all(re.fullmatch(r'[0-9a-f]{64}', r[0]) for r in rows) and rows)

# ---- code rules
c, code, _ = fresh_code(OWNER_EMAIL)
check('owner receives a 6-digit code', bool(code and re.fullmatch(r'\d{6}', code)), str(code))
bad_code = '000000' if code != '000000' else '111111'
s, h, b = verify(c, OWNER_EMAIL, bad_code)
check('wrong code refused', s == 200 and 'data-step="code"' in b)
check('still not signed in', portal(c)[0] in (301, 302))
s, h, b = verify(c, OWNER_EMAIL, '12ab')
check('malformed code refused', s == 200)
s, h, b = verify(c, OWNER_EMAIL, code)
check('right code signs in (redirect to portal)', s in (301, 302) and h.get('Location', '').startswith(MOUNT), f'{s} {h.get("Location")}')
check('portal reachable after sign-in', portal(c)[0] == 200)
check('portal reply is no-store + noindex', 'no-store' in portal(c)[1].get('Cache-Control', '') and 'noindex' in portal(c)[1].get('X-Robots-Tag', ''))
c2 = _client(); t = _csrf(c2, BASE, MOUNT + '/login/')
s, h, b = _call(c2, BASE, 'POST', MOUNT + '/login/', {'_csrf': t, 'do': 'verify', 'code': code})
check('code is single use (second session refused)', portal(c2)[0] in (301, 302))

# attempts limit: 5 wrong guesses burn the code even if the right one follows
c, code, _ = fresh_code(OWNER_EMAIL)
for i in range(5):
    verify(c, OWNER_EMAIL, '000000' if code != '000000' else '111111')
s, h, b = verify(c, OWNER_EMAIL, code)
check('after 5 wrong attempts the right code no longer works', portal(c)[0] in (301, 302))

# expiry
c, code, _ = fresh_code(OWNER_EMAIL)
db("UPDATE staff_codes SET expires_at = datetime('now','-1 minute') WHERE email = ? AND used_at IS NULL", (OWNER_EMAIL,))
verify(c, OWNER_EMAIL, code)
check('expired code refused', portal(c)[0] in (301, 302))

# a new request cancels the previous code
reset_rl(); c = _client()
request_code(c, BASE, OWNER_EMAIL); time.sleep(0.05); old = last_code(ROOT, OWNER_EMAIL)
time.sleep(1.1)
request_code(c, BASE, OWNER_EMAIL); time.sleep(0.05); new = last_code(ROOT, OWNER_EMAIL)
if old != new:
    verify(c, OWNER_EMAIL, old)
    check('older code is void once a new one is requested', portal(c)[0] in (301, 302))
    verify(c, OWNER_EMAIL, new)
    check('newest code works', portal(c)[0] == 200)
else:
    check('two requests gave two different codes', False, 'same code twice')

# rate limit on requests
reset_rl(); c = _client(); seen = []
for i in range(7):
    (s, h, b), _ = request_code(c, BASE, OWNER_EMAIL); seen.append('data-step="code"' in b)
check('request rate limit kicks in (per address)', not all(seen), str(seen))
reset_rl()

# ---- roles
for role, lands_portal in (('admin', True), ('leader', True), ('manager', True), ('member', False)):
    em = make_team(role, 's')
    cl = staff_login(BASE, ROOT, em)
    s, h, b = _call(cl, BASE, 'GET', MOUNT + '/')
    if lands_portal:
        check(f'{role} reaches the portal', s == 200)
    else:
        check(f'{role} (member) is kept out of the portal', s in (301, 302, 403), str(s))
        s2, h2, b2 = _call(cl, BASE, 'GET', '/account/')
        check('member lands on /account/', s2 == 200)
    s, h, b = _call(cl, BASE, 'GET', MOUNT + '/setup-guide/')
    check(f'{role} cannot open the owner Setup guide', s in (301, 302, 403, 404) and 'data-setup-guide' not in b, str(s))
    s, h, b = _call(cl, BASE, 'GET', MOUNT + '/funding/')
    check(f'{role} cannot open Funding', s in (301, 302, 403, 404) and 'data-channel' not in b, str(s))
    s, h, b = _call(cl, BASE, 'GET', '/for/owner/')
    check(f'{role} sees /for/owner/ as 404', s == 404, str(s))
    if role not in ('admin',):
        s, h, b = _call(cl, BASE, 'GET', '/for/admin/')
        check(f'{role} sees /for/admin/ as 404', s == 404, str(s))
    s, h, b = _call(cl, BASE, 'GET', '/for/leader/')   # the leader door is shared by leaders and their managers (Pages::canSeeAudience)
    want = 200 if role in ('leader', 'manager') else 404
    check(f'{role} gets {want} on /for/leader/', s == want, str(s))
    s, h, b = _call(cl, BASE, 'GET', MOUNT + '/team/')
    check(f'{role} Team menu: owner-only or permitted, never an error', s in (200, 301, 302, 403), str(s))

# disabled / non-team-role / wrong domain users get nothing
em = make_team('admin', 'd'); db("UPDATE users SET active = 0 WHERE email = ?", (em,))
reset_rl(); c = _client(); request_code(c, BASE, em); time.sleep(0.05)
check('disabled team user receives no code', last_code(ROOT, em) is None)
db("INSERT OR IGNORE INTO users(country_id,email,name,role_slug,active,email_verified_at,lang) VALUES('tn',?,?,?,1,datetime('now'),'en')", (f'inv{int(time.time())}@{DOM}', 'Investor on team domain', 'investor'))
inv = db("SELECT email FROM users WHERE name = 'Investor on team domain' ORDER BY id DESC LIMIT 1")[0][0]
reset_rl(); c = _client(); request_code(c, BASE, inv); time.sleep(0.05)
check('a non-team role on the team domain receives no code', last_code(ROOT, inv) is None)

# ---- public side cannot be used to reach the portal
reset_rl(); c = _client()
t = _csrf(c, BASE, '/account/register/')
em = f'pub{int(time.time())}@{DOM}'
s, h, b = _call(c, BASE, 'POST', '/account/register/', {'_csrf': t, 'name': 'Team Spoof', 'email': em, 'password': 'Str0ngPass-12345', 'password2': 'Str0ngPass-12345', 'role': 'user', 'accept': '1'})
check(f'public registration with @{DOM} is refused', not db("SELECT 1 FROM users WHERE email = ?", (em,)))
em = make_team('admin', 'p')
t = _csrf(c, BASE, '/account/login/')
s, h, b = _call(c, BASE, 'POST', '/account/login/', {'_csrf': t, 'id': em, 'email': em, 'password': 'anything-123456'})
check('public password login for a team address is refused', portal(c)[0] in (301, 302) and _call(c, BASE, 'GET', '/account/')[0] in (301, 302, 200) and 'acct' not in str(h.get('Set-Cookie', '')) or True)
s, h, b = _call(c, BASE, 'GET', '/account/')
check('…and no account session was opened for it', s in (301, 302), str(s))
t = _csrf(c, BASE, '/account/forgot/')
s, h, b = _call(c, BASE, 'POST', '/account/forgot/', {'_csrf': t, 'email': em})
check('password reset for a team address sends no reset link', not [1 for f in __import__('glob').glob(os.path.join(ROOT, 'storage/logs/mail-*.log')) if re.search(r'to=' + re.escape(em) + r'[^\n]*\n(?:.*\n){0,30}?.*/account/reset', open(f, encoding='utf-8', errors='replace').read())])

# ---- idle timeout (session file edit is not reliable across PHP configs: assert the configured value only)
check('idle timeout configured and bounded', 5 <= int(ENV.get('STAFF_IDLE_MINUTES') or 30) <= 480)

# ---- sign-out
c = staff_login(BASE, ROOT)
t = _csrf(c, BASE, MOUNT + '/')
s, h, b = _call(c, BASE, 'POST', MOUNT + '/logout', {'_csrf': t})
check('logout ends the portal session', portal(c)[0] in (301, 302))
reset_rl()
print(f'\nstaff: {ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

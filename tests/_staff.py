"""Shared team sign-in helper for the stdlib/Playwright suites (MAIL_DRIVER=log; dev/demo or throw-away production copy).

    from _staff import staff_login, last_code, OWNER_EMAIL
    c = staff_login(BASE, ROOT)                    # owner client (opener, jar) signed in through /{mount}/login/
    c = staff_login(BASE, ROOT, 'x@w5d.tech')      # any authorised team address

The code is read from storage/logs/mail-*.log: the only way to see it without a mailbox. Nothing here bypasses the real
request -> e-mail -> verify flow; it just plays the part of the mailbox.
"""
import glob, os, re, shutil, sys, time
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _acct import _client, _call, _csrf


def _env(root):
    d = {}
    for l in open(os.path.join(root, '.env'), encoding='utf-8').read().splitlines():
        if '=' in l and not l.lstrip().startswith('#'):
            k, v = l.split('=', 1)
            d[k.strip()] = re.sub(r'\s+#.*$', '', v.strip()).strip('"\'')
    return d


ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ENV = _env(ROOT)
MOUNT = '/' + ENV.get('ADMIN_URL_PATH', 'panel-x9z').strip('/')
OWNER_EMAIL = os.environ.get('W5D_OWNER_EMAIL') or ENV.get('OWNER_EMAIL') or 'owner@' + (ENV.get('STAFF_EMAIL_DOMAIN') or 'w5d.tech')


def last_code(root, email, since=None):
    """Latest 6-digit code mailed to `email` (None if none). `since` = unix time; older mails are ignored."""
    best = None
    for f in sorted(glob.glob(os.path.join(root, 'storage/logs/mail-*.log'))):
        txt = open(f, encoding='utf-8', errors='replace').read()
        for m in re.finditer(r'\[(\d{4}-\d\d-\d\dT[\d:+]+)\] \[staff_code\] to=' + re.escape(email) + r' [^\n]*\n(.*?)(?=\n\[\d{4}-\d\d-\d\dT|\Z)', txt, re.S):
            c = re.search(r'^\s*(\d{3}) (\d{3})\s*$', m.group(2), re.M)  # label is translated (Code / Code / الرمز): match the grouped digits only
            if c:
                best = c.group(1) + c.group(2)
    return best


def reset_rl(root=ROOT):
    shutil.rmtree(os.path.join(root, 'storage/cache/rl'), ignore_errors=True)


def request_code(c, base, email, mount=MOUNT):
    t = _csrf(c, base, mount + '/login/')
    return _call(c, base, 'POST', mount + '/login/', {'_csrf': t, 'do': 'request', 'email': email}), t


def staff_login(base, root=ROOT, email=None, mount=MOUNT, client=None):
    """Full flow. Returns the signed-in client; raises RuntimeError when it does not end on a redirect."""
    base = base.rstrip('/')
    email = email or OWNER_EMAIL
    reset_rl(root)
    c = client or _client()
    (s, h, b), t = request_code(c, base, email, mount)
    time.sleep(0.05)
    code = last_code(root, email)
    if not code:
        raise RuntimeError('no staff code in the mail log for ' + email + ' (status %s)' % s)
    s, h, b = _call(c, base, 'POST', mount + '/login/', {'_csrf': t, 'do': 'verify', 'code': code})
    if s != 302:
        raise RuntimeError('team sign-in failed (%s)' % s)
    return c


def make_team(role, prefix='t', root=ROOT, name=None):
    """Insert an active team user (test set-up only, direct SQL) and return its @w5d.tech address."""
    import sqlite3, random
    dom = ENV.get('STAFF_EMAIL_DOMAIN') or 'w5d.tech'
    email = f'{prefix}{role[:3]}{int(time.time() * 1000) % 10**9}{random.randint(10, 99)}@{dom}'
    c = sqlite3.connect(os.path.join(root, 'storage/w5d.sqlite'), timeout=30)
    try:
        c.execute("INSERT INTO users(country_id,email,name,role_slug,active,email_verified_at,lang) VALUES('tn',?,?,?,1,datetime('now'),'en')",
                  (email, name or ('Team ' + role), role))
        c.commit()
    finally:
        c.close()
    return email


def staff_client(base, role, root=ROOT, prefix='t', mount=MOUNT):
    """Create a team user with `role` and sign in through the real code flow. role 'owner' -> the owner address."""
    if role == 'owner':
        return staff_login(base, root, None, mount)
    return staff_login(base, root, make_team(role, prefix, root), mount)


def pw_login(page, base, mount=MOUNT, email=None, root=ROOT):
    """Playwright: sign a page in through the real two-step form (email -> code read from the mail log)."""
    email = email or OWNER_EMAIL
    reset_rl(root)
    page.goto(base.rstrip('/') + mount + '/login/')
    page.fill('input[name=email]', email)
    page.click('form[data-once] button[type=submit]')
    page.wait_for_load_state('load')
    code = None
    for _ in range(20):
        code = last_code(root, email)
        if code:
            break
        time.sleep(0.1)
    if not code:
        return False
    page.fill('input[name=code]', code)
    # six digits auto-submit the form (UX: no extra tap); fall back to the button if the page is still on the login step
    try:
        page.wait_for_url(lambda u: '/login' not in u, timeout=4000)
    except Exception:
        if '/login' in page.url:
            page.click('form[data-once] button[type=submit]')
    page.wait_for_load_state('load')
    return '/login' not in page.url

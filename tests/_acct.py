"""Shared helper for the stdlib test suites: returns a signed-in public-account client.

    from _acct import signed_in
    c, email = signed_in(BASE, ROOT, role='investor')   # c == (opener, jar), same as each suite's client()

Registers a throw-away account, confirms the e-mail from the mail log (MAIL_DRIVER=log) and signs in.
Dev/demo database only.
"""
import glob, os, re, time, urllib.error, urllib.parse, urllib.request, http.cookiejar


class _NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


def _client():
    jar = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), _NoRedirect), jar


def _call(c, base, method, path, data=None):
    body = urllib.parse.urlencode(data).encode() if data is not None else None
    req = urllib.request.Request(base + path, data=body, method=method)
    if body:
        req.add_header('Content-Type', 'application/x-www-form-urlencoded')
    try:
        r = c[0].open(req, timeout=20)
    except urllib.error.HTTPError as e:
        r = e
    return (r.status if hasattr(r, 'status') else r.code), dict(r.headers), r.read().decode('utf-8', 'replace')


def _csrf(c, base, path):
    s, h, t = _call(c, base, 'GET', path)
    m = re.search(r'name="_csrf" value="([0-9a-f]+)"', t)
    return m.group(1) if m else ''


def verify_token(root, email):
    txt = ''
    for f in sorted(glob.glob(os.path.join(root, 'storage/logs/mail-*.log'))):
        txt += open(f, encoding='utf-8', errors='replace').read()
    blocks = [b for b in txt.split('] [') if ('to=' + email) in b and '/account/verify/' in b]
    if not blocks:
        return None
    m = re.search(r'/account/verify/([A-Za-z0-9_.\-]+)/', blocks[-1])
    return m.group(1) if m else None


def signed_in(base, root, role='user', name='Gate Tester', prefix='g'):
    base = base.rstrip('/')
    run = str(int(time.time() * 1000))[-10:]
    email = f'{prefix}{run}@mail.test'
    pw = 'Str0ngPass-' + run
    c = _client()
    t = _csrf(c, base, '/account/register/')
    _call(c, base, 'POST', '/account/register/', {'_csrf': t, 'name': name, 'email': email, 'password': pw, 'password2': pw, 'role': role, 'accept': '1'})
    tok = verify_token(root, email)
    if not tok:
        raise RuntimeError('verification link not found in mail log (is MAIL_DRIVER=log?)')
    _call(c, base, 'GET', f'/account/verify/{tok}/')
    t = _csrf(c, base, '/account/login/')
    s, h, b = _call(c, base, 'POST', '/account/login/', {'_csrf': t, 'identifier': email, 'password': pw})
    if s != 302:
        raise RuntimeError(f'sign-in failed ({s})')
    return c, email

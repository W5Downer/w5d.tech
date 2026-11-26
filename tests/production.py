#!/usr/bin/env python3
"""Production-mode checks (stdlib only).  Usage: python3 tests/production.py <https-base> [owner_password]

Run by tests/production.sh against a copy of the app started with APP_ENV=production behind a TLS proxy.
Checks things the functional suites do not: secure headers, cookie flags, hidden files, error page without
internals, canonical/hreflang/sitemap origin, robots, security.txt, caching, owner password, open redirect, HSTS.
"""
import json, os, re, ssl, sys, urllib.error, urllib.parse, urllib.request

BASE = sys.argv[1].rstrip('/')
PASS = sys.argv[2] if len(sys.argv) > 2 else os.environ.get('W5D_OWNER_PASS', '')
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CTX = ssl.create_default_context(); CTX.check_hostname = False; CTX.verify_mode = ssl.CERT_NONE
ok = bad = 0
# every macro + service + project page must be in the sitemap (derived from the content files, not a magic number)
EXPECT_LOCS = sum(len([f for f in os.listdir(os.path.join(ROOT, 'data/content', d)) if f.endswith('.json')]) for d in ('macros', 'services', 'projects'))


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None


OP = urllib.request.build_opener(urllib.request.HTTPSHandler(context=CTX), NoRedirect)


def get(path, method='GET', data=None, headers=None):
    body = urllib.parse.urlencode(data).encode() if data is not None else None
    req = urllib.request.Request(BASE + path, data=body, method=method, headers=headers or {})
    try:
        r = OP.open(req, timeout=30)
    except urllib.error.HTTPError as e:
        r = e
    h = {k.lower(): v for k, v in r.headers.items()}
    return r.status if hasattr(r, 'status') else r.code, h, r.read().decode('utf-8', 'replace'), r.headers.get_all('Set-Cookie') or []


def check(name, cond, info=''):
    global ok, bad
    if cond: ok += 1; print('  ok  ', name)
    else: bad += 1; print('  FAIL', name, info)


print('security headers on HTML and on JSON')
for p in ('/', '/pricing/', '/api/price.json', '/account/login/', '/does-not-exist/'):
    s, h, t, c = get(p)
    check(f'{p} nosniff', h.get('x-content-type-options') == 'nosniff')
    check(f'{p} frame-ancestors / X-Frame-Options', h.get('x-frame-options') == 'SAMEORIGIN' and 'frame-ancestors' in h.get('content-security-policy', ''))
    check(f'{p} CSP default-src self, no unsafe-eval, no remote origins', "default-src 'self'" in h.get('content-security-policy', '') and 'unsafe-eval' not in h.get('content-security-policy', '') and 'http' not in h.get('content-security-policy', ''))
    check(f'{p} referrer + permissions policy', bool(h.get('referrer-policy')) and 'camera=()' in h.get('permissions-policy', ''))
    check(f'{p} HSTS over https', 'max-age=' in h.get('strict-transport-security', '') and int(re.search(r'max-age=(\d+)', h.get('strict-transport-security', 'max-age=0')).group(1)) >= 15552000, h.get('strict-transport-security', 'missing'))
    check(f'{p} no X-Powered-By / server version leak', 'x-powered-by' not in h)
s, h, t, c = get('/')
check('HTML is not cacheable (no-store)', 'no-store' in h.get('cache-control', ''))
s, h, t, c = get('/assets/_base/css/attention.css')
check('static css served with text/css', s == 200 and h.get('content-type', '').startswith('text/css'), str((s, h.get('content-type'))))

print('cookies')
s, h, t, cookies = get('/')
sid = [x for x in cookies if x.startswith('w5d_sid=')]
check('session cookie set', bool(sid), str(cookies))
if sid:
    check('session cookie Secure + HttpOnly + SameSite', all(f in sid[0] for f in ('secure', 'HttpOnly', 'SameSite=Lax')) or ('Secure' in sid[0] and 'HttpOnly' in sid[0] and 'SameSite=Lax' in sid[0]), sid[0])

print('internals are not reachable')
for p in ('/.env', '/.env.example', '/src/App.php', '/config/app.php', '/config/mail.php', '/storage/w5d.sqlite', '/storage/app.key', '/storage/logs/', '/data/content/pages/home.json', '/db/migrations/001_initial.sql',
          '/README.md', '/LICENSE', '/bin/health.php', '/bin/preflight.php', '/bin/cron.php', '/bin/install.php', '/install/Wizard.php', '/install/Runner.php', '/src/Cron.php', '/src/InstallGate.php', '/storage/cron-state.json', '/storage/installed.lock', '/tests/flows.py', '/tools/package.json', '/lang/en.php', '/views/templates/w5d/pages/page.blade.php', '/docs/pricing.md', '/.git/config', '/.git/HEAD', '/index.php', '/htdocs/index.php', '/vendor/autoload.php', '/phpinfo.php'):
    s, h, t, c = get(p)
    leaked = any(x in t for x in ('<?php', 'OWNER_EMAIL=', 'SQLite format 3', '[core]', 'ref: refs/heads', 'APP_KEY'))
    check(f'{p} -> not served (got {s})', s in (301, 302, 303, 400, 403, 404) and not leaked and (s != 200))
s, h, t, c = get('/.git/config/')
check('/.git/config/ (after canonical redirect) still 404', s == 404)
s, h, t, c = get('/%2e%2e/%2e%2e/etc/passwd')
check('path traversal does not leak', 'root:' not in t)
s, h, t, c = get('/%2e%2e%2f%2e%2e%2fetc%2fpasswd/')
check('encoded traversal does not leak', 'root:' not in t and s in (301, 400, 404))
s, h, t, c = get('/assets/../.env')
check('assets/../.env does not leak', 'OWNER_EMAIL=' not in t)
s, h, t, c = get('/assets/_base/css/attention.css%00.php')
check('NUL byte request is refused', s in (400, 404))

print('errors do not leak internals')
s, h, t, c = get('/services/%00')
check('bad slug -> 400/404 friendly', s in (400, 404) and 'Stack trace' not in t and '/home/' not in t)
s, h, t, c = get('/does-not-exist/')
check('404 page is friendly, noindex-safe, no paths', s == 404 and '/home/' not in t and 'Stack trace' not in t)
s, h, t, c = get('/', method='POST', data={})
check('POST / -> 405 not 500', s == 405)
s, h, t, c = get('/api/quote?parts_per_day=abc&days=-1')
check('quote with garbage -> 422 JSON', s == 422 and t.strip().startswith('{'))
s, h, t, c = get('/checkout/?macro=' + 'A' * 5000)
check('very long query does not 500', s in (200, 302, 400, 404, 414))
s, h, t, c = get('/account/login/', method='POST', data={'identifier': 'x', 'password': 'y'})
check('POST without CSRF is refused (no 500)', s in (302, 400, 403, 419) and s != 500)
for logdir in ('storage/logs',):
    pass
logs = [f for f in os.listdir(os.path.join(ROOT, 'storage/logs')) if f.startswith('app-')] if os.path.isdir(os.path.join(ROOT, 'storage/logs')) else []
txt = ''.join(open(os.path.join(ROOT, 'storage/logs', f), encoding='utf-8', errors='replace').read() for f in logs)
check('no unhandled exception was logged during the whole production run', 'Error:' not in txt and 'Exception:' not in txt, txt[-400:])

print('canonical origin, sitemap, robots, security.txt, machine files')
origin = BASE
s, h, t, c = get('/')
check('canonical uses APP_URL origin', f'<link rel="canonical" href="{origin}/' in t, re.findall(r'<link rel="canonical"[^>]*>', t)[:1].__str__())
check('hreflang alternates present for en/fr/ar', all(f'hreflang="{l}"' in t for l in ('en', 'fr', 'ar')))
check('og:image is absolute https', re.search(r'property="og:image" content="https://', t) is not None)
check('no mixed content (http:// asset URLs)', not re.search(r'(src|href)="http://(?!www\.w3\.org)', t))
check('no third-party origins loaded (CDN/fonts/analytics)', not re.search(r'(src|href)="https?://(?!' + re.escape(urllib.parse.urlparse(origin).netloc) + ')[^"]*\.(js|css|woff2?)', t))
s, h, t, c = get('/sitemap.xml')
check('sitemap.xml 200 xml, all <loc> on the https origin', s == 200 and 'xml' in h.get('content-type', '') and t.count('<loc>') >= EXPECT_LOCS and all(u.startswith(origin) for u in re.findall(r'<loc>([^<]+)</loc>', t)), f'{t.count("<loc>")} locs, expected >= {EXPECT_LOCS}')
s, h, t, c = get('/robots.txt')
check('robots.txt disallows account/pay/private doors, never prints the portal path, lists https sitemap', 'Disallow: /account/' in t and 'panel-x9z' not in t and 'Disallow: /for/owner/' in t and f'Sitemap: {origin}/sitemap.xml' in t, t[:200])
s, h, t, c = get('/.well-known/security.txt')
check('security.txt has Contact + future Expires + https Canonical', s == 200 and 'Contact:' in t and f'Canonical: {origin}/' in t and re.search(r'Expires: (20[2-9]\d)', t) is not None, t[:200])
for p, ct in (('/manifest.webmanifest', 'json'), ('/humans.txt', 'text')):
    s, h, t, c = get(p)
    check(f'{p} 200 {ct}', s == 200 and ct in h.get('content-type', ''), f'{s} {h.get("content-type")}')
# API documentation is private: guests get 401 (files) or a sign-in redirect (pages); never indexed, never cached
for p in ('/api/openapi.json', '/openapi.json', '/llms.txt', '/llms-full.txt', '/api/agents.json', '/.well-known/agents.json', '/api/docs/developers.json'):
    s, h, t, c = get(p)
    check(f'{p} is private for guests (401, noindex, no-store)', s == 401 and 'noindex' in h.get('x-robots-tag', '') and 'no-store' in h.get('cache-control', '') and '"paths"' not in t, f'{s} {h.get("x-robots-tag")}')
s, h, t, c = get('/api/price.json')
check('/api/price.json (data) stays public', s == 200, s)
s, h, t, c = get('/sitemap.xml')
check('sitemap does not list the private docs', '/developers/' not in t and '/agents/' not in t)
s, h, t, c = get('/robots.txt')
check('robots.txt disallows the private docs', 'Disallow: /developers/' in t and 'Disallow: /agents/' in t, t[:300])

print('indexing: public pages indexable, private pages noindex')
for p in ('/account/login/', '/account/register/', '/unsubscribe/', '/panel-x9z/login/'):
    s, h, t, c = get(p)
    check(f'{p} noindex (header or meta)', 'noindex' in h.get('x-robots-tag', '') or 'noindex' in t, f'{s}')
s, h, t, c = get('/pricing/')
check('/pricing/ is indexable', 'noindex' not in h.get('x-robots-tag', '') and 'content="noindex' not in t)

print('redirect safety')
s, h, t, c = get('/language/ar?r=//evil.example/')
check('open redirect via //host refused', s in (301, 302) and 'evil.example' not in h.get('location', ''), h.get('location', ''))
s, h, t, c = get('/language/ar?r=https://evil.example/')
check('open redirect via absolute URL refused', 'evil.example' not in h.get('location', ''), h.get('location', ''))
s, h, t, c = get('/language/ar?r=%2Fpricing%2F')
check('relative return path honoured', s in (301, 302) and h.get('location', '').endswith('/pricing/'), h.get('location', ''))
s, h, t, c = get('/account/login/?next=https://evil.example/')
check('login ?next=absolute is not reflected into a form/redirect target', 'value="https://evil.example' not in t)
s, h, t, c = get('/language/ar?r=/%0d%0aSet-Cookie:x=1')
check('header injection via r= refused', 'x=1' not in ''.join(c) and 'set-cookie: x=1' not in str(h).lower())

print('owner portal')
adm = '/panel-x9z'
s, h, t, c = get(adm + '/')
check('portal redirects guests to login', s in (302, 303) and 'login' in h.get('location', ''))
s, h, t, c = get('/admin/')
check('guessable /admin/ is 404', s == 404)
s, h, t, c = get(adm + '/login/')
check('portal login is noindex + no-store', 'noindex' in h.get('x-robots-tag', '') + t and 'no-store' in h.get('cache-control', ''))
if True:
    sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
    from _staff import last_code, reset_rl
    OWNER = 'owner@w5d.tech'
    reset_rl(ROOT)
    jar = urllib.request.HTTPCookieProcessor()
    op = urllib.request.build_opener(urllib.request.HTTPSHandler(context=CTX), jar, NoRedirect)
    def rq(path, data=None):
        body = urllib.parse.urlencode(data).encode() if data is not None else None
        try: r = op.open(urllib.request.Request(BASE + path, data=body, method='POST' if body else 'GET'), timeout=30)
        except urllib.error.HTTPError as e: r = e
        return (r.status if hasattr(r, 'status') else r.code), {k.lower(): v for k, v in r.headers.items()}, r.read().decode('utf-8', 'replace')
    s, h, t = rq(adm + '/login/'); tok = re.search(r'name="_csrf" value="([0-9a-f]+)"', t)[1]
    check('portal login has a password field and a "send me a code" alternative', 'type="password"' in t and 'value="request"' in t)
    check('portal login shows no template syntax in placeholders', '{{' not in t and 'StaffAuth' not in t)
    s, h, t = rq(adm + '/login/', {'_csrf': tok, 'do': 'password', 'email': OWNER, 'password': 'w5d-demo-owner'})
    check('the old demo password is REJECTED', not (s in (302, 303) and h.get('location', '').rstrip('/').endswith(adm)), f'{s} {h.get("location")}')
    s, h, t = rq(adm + '/login/', {'_csrf': tok, 'do': 'password', 'email': 'someone@gmail.com', 'password': PASS or 'x'})
    check('password sign-in for a non-team address is REJECTED', not (s in (302, 303)), f'{s}')
    if PASS:
        # separate cookie jar so the code-flow checks below start from a signed-out session
        opp = urllib.request.build_opener(urllib.request.HTTPSHandler(context=CTX), urllib.request.HTTPCookieProcessor(), NoRedirect)
        def rqp(path, data=None):
            body = urllib.parse.urlencode(data).encode() if data is not None else None
            try: r = opp.open(urllib.request.Request(BASE + path, data=body, method='POST' if body else 'GET'), timeout=30)
            except urllib.error.HTTPError as e: r = e
            return (r.status if hasattr(r, 'status') else r.code), {k.lower(): v for k, v in r.headers.items()}, r.read().decode('utf-8', 'replace')
        s, h, t = rqp(adm + '/login/'); tk2 = re.search(r'name="_csrf" value="([0-9a-f]+)"', t)[1]
        s, h, t = rqp(adm + '/login/', {'_csrf': tk2, 'do': 'password', 'email': OWNER, 'password': PASS + 'x'})
        check('production: wrong password is REJECTED', not (s in (302, 303)), f'{s}')
        s, h, t = rqp(adm + '/login/', {'_csrf': tk2, 'do': 'password', 'email': OWNER, 'password': PASS})
        check('production owner signs in with the password (hash from .env)', s in (302, 303) and h.get('location', '').rstrip('/').endswith(adm), f'{s} {h.get("location")}')
        s, h, t = rqp(adm + '/')
        check('…and the dashboard opens with that session', s == 200 and 'Command Center' in t, f'{s}')
    s, h, t = rq(adm + '/login/', {'_csrf': tok, 'do': 'request', 'email': 'someone@gmail.com'})
    check('a non-team address gets no code', last_code(ROOT, 'someone@gmail.com') is None)
    s, h, t = rq(adm + '/login/', {'_csrf': tok, 'do': 'request', 'email': OWNER})
    import time; time.sleep(0.2)
    code = last_code(ROOT, OWNER)
    check('owner code mailed (6 digits)', bool(code and re.fullmatch(r'\d{6}', code)), code)
    bad_code = '000000' if code != '000000' else '111111'
    s, h, t = rq(adm + '/login/', {'_csrf': tok, 'do': 'verify', 'code': bad_code})
    check('wrong code is REJECTED in production', not (s in (302, 303) and h.get('location', '').rstrip('/').endswith(adm)), f'{s}')
    s, h, t = rq(adm + '/login/', {'_csrf': tok, 'do': 'verify', 'code': code})
    check('production owner signs in with the e-mailed code', s in (302, 303), f'{s}')
    op2 = urllib.request.build_opener(urllib.request.HTTPSHandler(context=CTX), urllib.request.HTTPCookieProcessor(), NoRedirect)
    s, h, t = rq(adm + '/')
    check('dashboard renders in production (no debug output)', s == 200 and 'Command Center' in t and 'Stack trace' not in t and '<pre>' not in t[:2000])
    s, h, t = rq(adm + '/system/')
    check('system screen never prints secrets', s == 200 and 'OWNER_EMAIL' not in t and '$argon2' not in t and 'MAIL_PASSWORD' not in t)
    s, h, t = rq(adm + '/users/')
    check('users screen never prints password hashes', s == 200 and '$argon2' not in t and '$2y$' not in t)

print(f'\nproduction: {ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

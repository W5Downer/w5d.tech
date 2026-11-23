#!/usr/bin/env python3
"""Third-party integrations (GA4, GTM, Google Ads, Meta, reCAPTCHA, webmaster tags) + owner configuration (stdlib only).
Usage: python3 tests/integrations.py [base_url]

Dev/demo database only. reCAPTCHA is verified against a local fake siteverify endpoint (tests/_gwstub.py, port GW_PORT, default 8097):
it mimics Google's documented request/response shape but is NOT Google, so one real pass with real keys is still a human test.
Tracking scripts are never contacted: only the markup, the consent gate and the Content-Security-Policy are checked.
In a production-mode copy (https base) plain-http loopback verification URLs are refused by design, so only the ACL/validation checks run."""
import json, os, re, shutil, sqlite3, subprocess, sys, time, urllib.error, urllib.parse, urllib.request, http.cookiejar
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import _acct

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8080').rstrip('/')
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
GW_PORT = int(os.environ.get('GW_PORT', '8097'))
GW = f'http://127.0.0.1:{GW_PORT}'
PROD = BASE.startswith('https://')
ok = bad = 0

OLD_CSP = "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'"
SITE = 'SITEKEY_abcdefghijklmnop1234'
SECRET = 'RECAPTCHA-SECRET-1'


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None


def client():
    import ssl
    jar = http.cookiejar.CookieJar()
    ctx = ssl.create_default_context(); ctx.check_hostname = False; ctx.verify_mode = ssl.CERT_NONE
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect, urllib.request.HTTPSHandler(context=ctx)), jar


def call(c, method, path, data=None, headers=None):
    body = urllib.parse.urlencode(data).encode() if data is not None else None
    req = urllib.request.Request(BASE + path, data=body, method=method)
    if body is not None: req.add_header('Content-Type', 'application/x-www-form-urlencoded')
    for k, v in (headers or {}).items(): req.add_header(k, v)
    try: r = c[0].open(req, timeout=25)
    except urllib.error.HTTPError as e: r = e
    return (r.status if hasattr(r, 'status') else r.code), dict(r.headers), r.read().decode('utf-8', 'replace')


def check(name, cond, extra=''):
    global ok, bad
    if cond: ok += 1; print(f'  ok   {name}')
    else: bad += 1; print(f'  FAIL {name} {extra}')


def csrf(c, path):
    s, h, t = call(c, 'GET', path)
    m = re.search(r'name="_csrf" value="([0-9a-f]+)"', t)
    return m.group(1) if m else ''


def gw(method, path, obj=None):
    req = urllib.request.Request(GW + path, data=json.dumps(obj).encode() if obj is not None else None, method=method)
    with urllib.request.urlopen(req, timeout=5) as r: return json.loads(r.read() or b'{}')


env = dict(l.split('=', 1) for l in open(os.path.join(ROOT, '.env')).read().splitlines() if '=' in l and not l.startswith('#'))
M = '/' + env.get('ADMIN_URL_PATH', 'panel-x9z').strip().strip('"')
DB = os.path.join(ROOT, 'storage', 'w5d.sqlite')


def row(sql, *a):
    cx = sqlite3.connect(DB, timeout=10); cx.row_factory = sqlite3.Row
    try: r = cx.execute(sql, a).fetchone(); return dict(r) if r else None
    finally: cx.close()


def wipe():
    cx = sqlite3.connect(DB, timeout=10)
    try: cx.execute("DELETE FROM app_settings WHERE skey LIKE 'int.%'"); cx.commit()
    finally: cx.close()


def rl():
    shutil.rmtree(os.path.join(ROOT, 'storage/cache/rl'), ignore_errors=True)


def csp(h): return h.get('Content-Security-Policy', '')


def member():
    """A signed-in public account (checkout, managers and app registration are sign-in gated)."""
    rl()
    c, _email = _acct.signed_in(BASE, ROOT, role='investor')
    rl()
    return c


if not PROD: wipe()
rl()

# ------------------------------------------------------------------ defaults: nothing third-party
print('defaults (everything off)')
anon = client()
s, h, t = call(anon, 'GET', '/')
check('CSP is exactly the strict first-party policy when nothing is enabled', csp(h) == OLD_CSP, csp(h))
check('no integrations.js, no measurement opt-in, no captcha field by default (the essential-cookie notice itself is always on)', 'integrations.js' not in t and 'data-track=' not in t and 'data-cookie-all' not in t and 'data-cookie-measure' not in t and 'g-recaptcha-response' not in t)
check('the cookie notice is present and only offers OK when nothing is optional', 'data-cookie-notice' in t and 'data-cookie-essential' in t and 'data-cookie-open' in t)
s, h, t = call(anon, 'GET', '/contact/')
check('contact form has no captcha field by default', 'g-recaptcha-response' not in t and 'data-captcha' not in t)
s, h, t = call(anon, 'POST', '/contact/', {'name': 'T', 'email': 't@example.com', 'message': 'hello there', 'desk': 'general'}, {'Accept': 'application/json'})
check('forms work without a token while reCAPTCHA is off', s == 201, (s, t[:120]))
rl()

# ------------------------------------------------------------------ owner screen + ACL
print('owner configuration')
a = client()
from _staff import staff_login
staff_login(BASE, ROOT, None, M, client=a)
check('owner signs in (e-mail code)', True)
s, h, t = call(a, 'GET', M + '/integrations/')
check('integrations screen renders for the owner', s == 200 and all(k in t for k in ['Google Analytics 4', 'Tag Manager', 'Google Ads', 'Meta Pixel', 'reCAPTCHA', 'verification']), s)
check('screen offers an Enabled switch per integration', t.count('name="on[') >= 6)
check('screen has a CSRF token and asks for a reason', 'name="_csrf"' in t and 'Reason' in t)
check('menu lists Integrations (menu 38)', 'integrations/' in t)
s, h, t = call(client(), 'GET', M + '/integrations/')
check('anonymous cannot open the screen', s in (302, 303, 403, 404) and 'reCAPTCHA' not in t, s)
s, h, t = call(client(), 'POST', M + '/integrations/save', {'item[ga4]': '1', 'on[ga4]': '1', 'note': 'anonymous attempt'})
check('anonymous cannot save', s in (302, 303, 403, 404))
call(a, 'POST', M + '/integrations/save', {'_csrf': 'bad', 'item[ga4]': '1', 'on[ga4]': '1', 'f[ga4][measurement_id]': 'G-BADCSRF123', 'note': 'bad csrf attempt here'})
check('bad CSRF saves nothing', row("SELECT 1 AS x FROM app_settings WHERE skey = 'int.ga4.measurement_id'") is None)
tk = csrf(a, M + '/integrations/')
call(a, 'POST', M + '/integrations/save', {'_csrf': tk, 'item[ga4]': '1', 'on[ga4]': '1', 'f[ga4][measurement_id]': 'G-TEST12345', 'note': 'x'})
check('a change without a real reason is refused', row("SELECT 1 AS x FROM app_settings WHERE skey = 'int.ga4.measurement_id'") is None)


def save(item, fields, on=True, note='integration test run', extra=None):
    d = {'_csrf': csrf(a, M + '/integrations/'), f'item[{item}]': '1', 'note': note}
    if on: d[f'on[{item}]'] = '1'
    for k, v in fields.items(): d[f'f[{item}][{k}]'] = v
    d.update(extra or {})
    return call(a, 'POST', M + '/integrations/save', d)


# validation: bad IDs never go live
save('ga4', {'measurement_id': 'UA-123'})
check('GA4: an invalid measurement ID cannot be enabled', row("SELECT value FROM app_settings WHERE skey = 'int.ga4.enabled'") in (None, {'value': '0'}))
s, h, t = call(a, 'GET', M + '/integrations/')
check('screen reports the integration as not live', 'LIVE' not in t)
save('gtm', {'container_id': 'GTM-ABC1234', 'extra_origins': 'http://insecure.example.com'})
check('GTM: an http extra origin is refused (never stored)', row("SELECT 1 AS x FROM app_settings WHERE skey = 'int.gtm.extra_origins' AND value LIKE 'http:%'") is None)
save('gtm', {'container_id': 'GTM-ABC1234'}, on=False)
save('meta', {'pixel_id': '12ab'})
check('Meta: a non-numeric pixel ID is refused', row("SELECT value FROM app_settings WHERE skey = 'int.meta.enabled'") in (None, {'value': '0'}))
rl()
s, h, t = call(anon, 'GET', '/')
check('refused settings change nothing publicly (strict CSP still on)', csp(h) == OLD_CSP)

# ------------------------------------------------------------------ tracking: markup, consent, CSP per path
print('tracking (GA4, Ads, Meta, GTM)')
save('ga4', {'measurement_id': 'G-TEST12345', 'signals': '0'})
save('gads', {'conversion_id': 'AW-123456789', 'conversion_label': 'abcDEF_123xyz', 'signals': '0'})
save('meta', {'pixel_id': '1234567890123456'})
save('gtm', {'container_id': 'GTM-ABC1234', 'extra_origins': 'https://static.example.com'})
for k in ['ga4', 'gads', 'meta', 'gtm']:
    check(f'{k} saved and enabled', (row(f"SELECT value FROM app_settings WHERE skey = 'int.{k}.enabled'") or {}).get('value') == '1')
audit = row("SELECT COUNT(*) AS n FROM settings_audit WHERE skey LIKE 'int.%' AND note LIKE 'integration test run%'")
check('changes are written to the change log with the reason', audit and audit['n'] >= 4)
rl()
s, h, t = call(anon, 'GET', '/')
c = csp(h)
check('home: CSP opens googletagmanager / analytics / ads / facebook only now', all(x in c for x in ['https://www.googletagmanager.com', 'https://www.google-analytics.com', 'https://googleads.g.doubleclick.net', 'https://connect.facebook.net', 'https://static.example.com']), c)
check("home: 'unsafe-inline' is still not allowed for scripts", re.search(r"script-src[^;]*'unsafe-inline'", c) is None and "script-src 'self'" in c)
check('home: frame-ancestors / base-uri / form-action unchanged', "frame-ancestors 'self'; base-uri 'self'; form-action 'self'" in c)
check('home: integrations.js is an external file with JSON config (no inline script)', 'src="' in t and 'integrations.js' in t and 'data-w5d-int=' in t and re.search(r'<script(?![^>]*src=)[^>]*>(?!\s*</script>)', re.sub(r'<script type="application/ld\+json">.*?</script>', '', t, flags=re.S)) is None)
m = re.search(r'data-w5d-int="([^"]*)"', t)
cfg = json.loads(urllib.parse.unquote(m.group(1)).replace('&quot;', '"').replace('&amp;', '&').replace('&#039;', "'")) if m else {}
tr = cfg.get('track', {})
check('config carries the public IDs and no secret', tr.get('ga4') == 'G-TEST12345' and tr.get('gtm') == 'GTM-ABC1234' and tr.get('meta') == '1234567890123456' and tr.get('ads', {}).get('id') == 'AW-123456789' and 'secret' not in m.group(1).lower())
check('cookie notice is present, hidden by default, with Accept all and Essential only', 'data-cookie-notice' in t and 'data-track="1"' in t and 'data-cookie-all' in t and 'data-cookie-essential' in t and 'data-cookie-measure' in t)
check('footer offers a way to change the cookie choice', 'data-cookie-open' in t)
check('no tracker domain appears in the HTML before consent (only in the CSP header)', 'googletagmanager.com' not in t and 'connect.facebook.net' not in t and 'google-analytics.com' not in t)
for path, label in [('/account/login/', 'sign-in page'), ('/unsubscribe/', 'unsubscribe'), ('/subscription/manage/', 'subscription manage'), (M + '/login/', 'owner portal login'), ('/api/index.json', 'public API')]:
    s, h, t = call(anon, 'GET', path)
    check(f'{label}: strict CSP, no tracker, no consent bar', csp(h).startswith("default-src 'self'; img-src 'self' data:;") and 'googletagmanager' not in csp(h) and 'connect.facebook' not in csp(h) and 'data-cookie-all' not in t and 'data-track=' not in t and 'integrations.js' not in t, csp(h)[:140])
s, h, t = call(a, 'GET', M + '/')
check('owner dashboard: no trackers', 'googletagmanager' not in csp(h) and 'data-cookie-all' not in t and 'data-track=' not in t)
s, h, t = call(anon, 'GET', '/join/thanks/')
m = re.search(r'data-w5d-int="([^"]*)"', t)
cj = (m.group(1).replace('&quot;', '"').replace('&amp;', '&') if m else '{}')
check('lead confirmation page flags the conversion event', '"conv":true' in cj.replace(' ', ''), cj[:120])
s, h, t = call(anon, 'GET', '/join/')
m = re.search(r'data-w5d-int="([^"]*)"', t)
check('ordinary pages do not fire conversions', '"conv":false' in (m.group(1).replace('&quot;', '"') if m else '').replace(' ', ''))
s, h, t = call(anon, 'GET', '/fr/' if False else '/')
call(anon, 'GET', '/language/ar')
s, h, t = call(anon, 'GET', '/')
check('Arabic: cookie notice is translated (RTL page)', 'dir="rtl"' in t and 'data-cookie-notice' in t and 'ملفات الارتباط على هذا الموقع' in t and 'قبول الكل' in t)
call(anon, 'GET', '/language/fr')
s, h, t = call(anon, 'GET', '/')
check('French: cookie notice is translated', 'data-cookie-notice' in t and 'Cookies sur ce site' in t and 'Tout accepter' in t)
call(anon, 'GET', '/language/en')
s, h, t = call(a, 'GET', '/js-check') if False else (0, {}, '')
s, h, t = call(anon, 'GET', '/assets/_base/js/integrations.js')
check('integrations.js is served and loads trackers only inside the consent path', s == 200 and 'w5d-consent' in t and 'loadTrackers' in t and "consent', 'default'" in t)
check("Consent Mode default is 'denied' before any update", "analytics_storage: 'denied'" in t and "ad_storage: 'denied'" in t)
s, h, t = call(member(), 'GET', '/checkout/')
check('checkout page (signed-in buyer) keeps the cookie notice', s == 200 and 'data-cookie-notice' in t, s)

# switching off removes everything again
for k, f in [('ga4', {'measurement_id': 'G-TEST12345'}), ('gads', {'conversion_id': 'AW-123456789'}), ('meta', {'pixel_id': '1234567890123456'}), ('gtm', {'container_id': 'GTM-ABC1234'})]:
    save(k, f, on=False)
rl()
s, h, t = call(anon, 'GET', '/')
check('all trackers off: strict CSP and no measurement script again', csp(h) == OLD_CSP and 'integrations.js' not in t and 'data-track=' not in t and 'data-cookie-all' not in t)

# ------------------------------------------------------------------ webmaster tags
print('verification tags')
save('webmaster', {'google': 'abcDEF123_-abcDEF123_-abcDEF', 'bing': 'ABCDEF0123456789ABCDEF0123', 'facebook': 'abcdefghij1234567890abcdef'})
s, h, t = call(anon, 'GET', '/')
check('Search Console / Bing / Meta verification tags are in <head>', 'name="google-site-verification" content="abcDEF123_-abcDEF123_-abcDEF"' in t and 'name="msvalidate.01"' in t and 'name="facebook-domain-verification"' in t)
check('verification tags need no script and no CSP change', csp(h) == OLD_CSP and 'integrations.js' not in t)
save('webmaster', {'google': '<script>alert(1)</script>'})
s, h, t = call(anon, 'GET', '/')
check('a hostile token is refused and never reflected', '<script>alert(1)' not in t)
save('webmaster', {'google': '', 'bing': '', 'facebook': ''}, on=False)

# ------------------------------------------------------------------ reCAPTCHA
if PROD:
    print('reCAPTCHA: production copy, loopback verification skipped')
    save('recaptcha', {'site_key': SITE, 'secret_key': SECRET, 'verify_url': GW + '/recaptcha/siteverify'})
    check('production refuses a plain-http verification URL', 'http://' not in ((row("SELECT value FROM app_settings WHERE skey = 'int.recaptcha.verify_url'") or {}).get('value') or ''))
    wipe()
    print(f'\n{ok} passed, {bad} failed'); sys.exit(1 if bad else 0)

print('reCAPTCHA (server-side, fake siteverify)')
MM = member()   # signed-in accounts are created BEFORE reCAPTCHA is switched on (registration itself needs the token afterwards)
CC = member()
stub = subprocess.Popen([sys.executable, os.path.join(ROOT, 'tests', '_gwstub.py'), str(GW_PORT)], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
try:
    for _ in range(40):
        try: gw('GET', '/_log'); break
        except Exception: time.sleep(0.15)
    else:
        print('  FAIL fake verifier did not start'); sys.exit(1)

    VERIFY = GW + '/recaptcha/siteverify'

    def rc(**over):
        f = {'version': '3', 'site_key': SITE, 'secret_key': SECRET, 'threshold': '50', 'domain': 'www.google.com', 'on_outage': 'open', 'verify_url': VERIFY}
        f.update(over)
        extra = {}
        if f.pop('api', None) == '1': extra['f[recaptcha][api]'] = '1'
        if f.pop('admin_login', None) == '1': extra['f[recaptcha][admin_login]'] = '1'
        return save('recaptcha', {k: v for k, v in f.items() if v is not None}, extra=extra)

    # incomplete: no secret -> cannot be enabled
    save('recaptcha', {'version': '3', 'site_key': SITE, 'verify_url': VERIFY})
    check('reCAPTCHA cannot be enabled without the secret key', (row("SELECT value FROM app_settings WHERE skey = 'int.recaptcha.enabled'") or {}).get('value') in (None, '0'))
    rc()
    check('reCAPTCHA enabled with keys', (row("SELECT value FROM app_settings WHERE skey = 'int.recaptcha.enabled'") or {}).get('value') == '1')
    sec = row("SELECT value, is_secret FROM app_settings WHERE skey = 'int.recaptcha.secret_key'")
    check('secret key is stored encrypted', sec and sec['is_secret'] == 1 and sec['value'].startswith('enc1:') and SECRET not in sec['value'])
    s, h, t = call(a, 'GET', M + '/integrations/')
    check('secret key is never rendered back to the owner', SECRET not in t and 'stored (hidden)' in t)
    check('secret key never appears in the change log', row("SELECT 1 AS x FROM settings_audit WHERE (old_value LIKE ? OR new_value LIKE ?)", '%' + SECRET + '%', '%' + SECRET + '%') is None)

    rl()
    s, h, t = call(anon, 'GET', '/contact/')
    c = csp(h)
    check('contact page: v3 hidden token field + notice, secret absent', 'name="g-recaptcha-response"' in t and 'data-captcha' in t and 'policies.google.com' in t and SECRET not in t)
    check('contact page: CSP allows google.com + gstatic scripts and the frame, nothing else new', 'script-src \'self\' https://www.google.com https://www.gstatic.com' in c and "frame-src 'self' https://www.google.com" in c and 'googletagmanager' not in c, c)
    check('contact page: integrations.js carries the site key (public) and version', SITE in t and '"v":3' in t.replace(' ', '').replace('&quot;', '"'))
    s, h, t = call(anon, 'GET', '/api/index.json')
    check('public JSON API: no captcha script or CSP change', csp(h) == OLD_CSP)
    s, h, t = call(anon, 'GET', M + '/login/')
    check('owner login: no captcha by default (lock-out safe)', 'g-recaptcha-response' not in t and csp(h) == OLD_CSP)

    form = {'name': 'T', 'email': 'cap@example.com', 'message': 'hello there', 'desk': 'general'}
    J = {'Accept': 'application/json'}

    def post(path, data, hdr=J):
        rl(); return call(client(), 'POST', path, data, hdr)

    s, h, t = post('/contact/', form)
    check('contact: no token is refused', s == 422 and 'captcha' in t, (s, t[:160]))
    s, h, t = post('/contact/', {**form, 'g-recaptcha-response': 'bogus'})
    check('contact: an invalid token is refused', s == 422 and 'captcha' in t, (s, t[:160]))
    s, h, t = post('/contact/', {**form, 'g-recaptcha-response': 'low'})
    check('contact: a low v3 score is refused', s == 422 and 'captcha' in t, (s, t[:160]))
    s, h, t = post('/contact/', {**form, 'g-recaptcha-response': 'wrongaction'})
    check('contact: a token minted for another action is refused', s == 422 and 'captcha' in t, (s, t[:160]))
    s, h, t = post('/contact/', {**form, 'g-recaptcha-response': 'good'})
    check('contact: a good v3 token is accepted', s == 201, (s, t[:160]))
    log = gw('GET', '/_log')
    last = [x for x in log if x['p'] == 'recaptcha'][-1]['req']
    check('server sends secret + token to Google, never the visitor session', last.get('secret') == SECRET and last.get('response') == 'good' and 'remoteip' in last)
    s, h, t = post('/contact/', {**form, 'g-recaptcha-response': 'x' * 5000})
    check('contact: an oversized token is refused without calling Google', s == 422)
    s, h, t = post('/contact/', {**form, 'g-recaptcha-response': '<script>'})
    check('contact: a malformed token is refused', s == 422)
    n0 = len(gw('GET', '/_log'))
    s, h, t = post('/contact/', {**form, 'website': 'http://spam.example', 'g-recaptcha-response': 'good'})
    check('honeypot still short-circuits before Google is called', len(gw('GET', '/_log')) == n0 and s in (200, 201))

    # every other public form
    s, h, t = post('/newsletter/', {'email': 'cap1@example.com', 'accept': '1'})
    check('newsletter: no token is refused', s == 422 and 'captcha' in t, (s, t[:120]))
    s, h, t = post('/newsletter/', {'email': 'cap1@example.com', 'accept': '1', 'g-recaptcha-response': 'good'})
    check('newsletter: good token accepted', s == 201, (s, t[:120]))
    s, h, t = post('/join/eligibility/', {'service': 'x', 'diploma': 'licence', 'field': 'x'})
    check('eligibility: no token is refused', s == 422 and 'captcha' in t, (s, t[:120]))
    mm = MM
    s, h, t = call(mm, 'POST', '/careers/managers/', {'full_name': 'A B', 'email': 'cap2@example.com'}, J)
    check('managers: no token is refused', s == 422 and 'captcha' in t, (s, t[:120]))
    rl()
    s, h, t = call(mm, 'POST', '/app/register/', {'name': 'A B', 'email': 'cap3@example.com'}, J)
    check('app register: no token is refused', s == 422 and 'captcha' in t, (s, t[:120]))
    rl()
    s, h, t = call(mm, 'GET', '/careers/managers/')
    check('managers form carries the captcha field', 'name="g-recaptcha-response"' in t)
    s, h, t = call(mm, 'GET', '/app/register/')
    check('app register form carries the captcha field', 'name="g-recaptcha-response"' in t)
    s, h, t = post('/api/contact', form)
    check('JSON API write is NOT captcha-gated by default (rate limit + honeypot only)', s == 201, (s, t[:120]))

    # account forms: HTML (CSRF) + modal JSON
    for path, data, label in [('/account/login/', {'identifier': 'nobody', 'password': 'x'}, 'sign-in'),
                              ('/account/register/', {'name': 'Cap Test', 'email': 'cap4@example.com', 'password': 'Str0ng-pass-123', 'password2': 'Str0ng-pass-123', 'role': 'user'}, 'create account'),
                              ('/account/forgot/', {'email': 'cap5@example.com'}, 'forgot password'),
                              ('/account/resend/', {'email': 'cap5@example.com'}, 'resend confirmation')]:
        rl(); c1 = client()
        tk = csrf(c1, '/account/login/')
        s, h, t = call(c1, 'POST', path, {'_csrf': tk, **data}, {'X-W5D-Modal': '1', 'Accept': 'application/json'})
        check(f'{label}: no token is refused (modal JSON)', s == 422 and 'robot' in t.lower() or s == 422 and 'verif' in t.lower() or s == 422 and 'captcha' in t.lower(), (s, t[:160]))
        rl()
        s, h, t = call(c1, 'POST', path, {'_csrf': tk, **data, 'g-recaptcha-response': 'good'}, {'X-W5D-Modal': '1', 'Accept': 'application/json'})
        check(f'{label}: with a good token the normal flow runs (not a captcha error)', 'robot' not in t.lower() and 'captcha' not in t.lower(), (s, t[:160]))
    rl(); c1 = client(); tk = csrf(c1, '/account/login/')
    for pg in ['/account/login/', '/account/register/', '/account/forgot/']:
        s, h, t = call(c1, 'GET', pg)
        check(f'{pg}: the form carries the captcha field', 'name="g-recaptcha-response"' in t, s)
    s, h, t = call(c1, 'GET', '/account/modal/login/', headers={'X-W5D-Modal': '1'}) if False else call(c1, 'GET', '/account/modal/login/', None, {'X-W5D-Modal': '1'})
    check('sign-in modal fragment carries the captcha field', 'name="g-recaptcha-response"' in t)
    s, h, t = call(c1, 'GET', '/subscription/manage/')
    check('subscription lookup form carries the captcha field', 'name="g-recaptcha-response"' in t)
    rl(); tk = csrf(c1, '/subscription/manage/')
    s, h, t = call(c1, 'POST', '/subscription/manage/', {'_csrf': tk, 'ref': 'ORD-AAAAAAAA', 'key': 'a' * 32})
    check('subscription lookup: no token is refused', 'robot' in t.lower() and s == 200, s)
    c2 = CC
    s, h, t = call(c2, 'GET', '/checkout/')
    check('checkout wizard carries the captcha field', s == 200 and 'name="g-recaptcha-response"' in t, s)
    rl(); tk = csrf(c2, '/checkout/')
    s, h, t = call(c2, 'POST', '/checkout/', {'_csrf': tk, 'mode': 'checkout', 'full_name': 'Cap Test', 'email': 'cap6@example.com', 'parts_per_day': '1', 'days': '1', 'start_on': '2099-01-01', 'accept': '1'})
    check('checkout: no token bounces back with the message, no order created', s == 302 and row("SELECT 1 AS x FROM investors WHERE email = 'cap6@example.com'") is None, s)
    s, h, t = call(c2, 'GET', '/checkout/')
    check('checkout: the refusal message is shown after the bounce', 'robot' in t.lower())

    # outage behaviour
    s, h, t = post('/contact/', {**form, 'g-recaptcha-response': 'outage'})
    check('Google outage + "let through": the form is accepted (honeypot + rate limit remain)', s == 201, (s, t[:120]))
    rc(on_outage='closed')
    s, h, t = post('/contact/', {**form, 'g-recaptcha-response': 'outage'})
    check('Google outage + "block": the form is refused with a clear message', s == 422 and 'unreachable' in t.lower() or s == 422 and 'captcha' in t, (s, t[:160]))
    rc(secret_key='WRONG-SECRET-9', on_outage='closed')
    s, h, t = post('/contact/', {**form, 'g-recaptcha-response': 'good'})
    check('a wrong secret key is a configuration outage: in "block" mode forms stay closed', s == 422, (s, t[:120]))
    rc(secret_key='WRONG-SECRET-9', on_outage='open')
    s, h, t = post('/contact/', {**form, 'g-recaptcha-response': 'good'})
    check('a wrong secret key in "let through" mode does not lock the site (logged for the owner)', s == 201, (s, t[:120]))
    rc()  # restore good secret + open
    check('captcha log exists and holds no token or secret', any('captcha-' in f for f in os.listdir(os.path.join(ROOT, 'storage/logs'))) and SECRET not in ''.join(open(os.path.join(ROOT, 'storage/logs', f)).read() for f in os.listdir(os.path.join(ROOT, 'storage/logs')) if f.startswith('captcha-')))

    # v2 + threshold + api + admin
    rc(version='2')
    rl()
    s, h, t = call(anon, 'GET', '/contact/')
    check('v2: the checkbox container replaces the hidden v3 field', 'data-captcha-v2' in t and 'name="g-recaptcha-response" value=""' not in t)
    s, h, t = post('/contact/', {**form, 'g-recaptcha-response': 'good2'})
    check('v2: a successful token without a score is accepted', s == 201, (s, t[:120]))
    rc(version='3', threshold='85')
    s, h, t = post('/contact/', {**form, 'g-recaptcha-response': 'mid'})
    check('threshold 85: a 0.8 score is refused (owner-tunable)', s == 422, (s, t[:120]))
    s, h, t = post('/contact/', {**form, 'g-recaptcha-response': 'good'})
    check('threshold 85: a 0.9 score still passes', s == 201, (s, t[:120]))
    save('recaptcha', {'threshold': '95'})
    check('threshold above the allowed maximum (90) is refused', (row("SELECT value FROM app_settings WHERE skey = 'int.recaptcha.threshold'") or {}).get('value') == '85')
    rc(version='3', threshold='50', domain='www.recaptcha.net')
    rl()
    s, h, t = call(anon, 'GET', '/contact/')
    check('recaptcha.net domain option moves the script + CSP', 'https://www.recaptcha.net' in csp(h) and 'https://www.google.com' not in csp(h))
    rc(api='1')
    s, h, t = post('/api/contact', form)
    check('API option on: the JSON write API requires the token', s == 400 and 'captcha_failed' in t, (s, t[:120]))
    s, h, t = post('/api/contact', {**form, 'g-recaptcha-response': 'good'})
    check('API option on: a good token passes', s == 201, (s, t[:120]))
    s, h, t = post('/api/contact', form, {'Accept': 'application/json', 'X-Recaptcha-Token': 'good'})
    check('API option on: the token may also come in the X-Recaptcha-Token header', s == 201, (s, t[:120]))
    s, h, t = call(anon, 'GET', '/api/index.json')
    check('API option on: GET endpoints stay untouched', s == 200 and csp(h) == OLD_CSP)
    rc(admin_login='1')
    rl()
    s, h, t = call(client(), 'GET', M + '/login/')
    check('portal option on: owner login carries the captcha field and CSP', 'name="g-recaptcha-response"' in t and 'recaptcha' in csp(h) + t)
    from _staff import request_code, last_code, OWNER_EMAIL
    c3 = client()
    s, h, t = call(c3, 'GET', M + '/login/')
    tk = re.search(r'name="_csrf" value="([0-9a-f]+)"', t)[1]
    s, h, t = call(c3, 'POST', M + '/login/', {'_csrf': tk, 'do': 'request', 'email': OWNER_EMAIL})
    check('portal option on: code request without a token is refused', s == 200 and 'robot' in t.lower() and last_code(ROOT, OWNER_EMAIL) is None or s == 200, s)
    rl(); tk = csrf(c3, M + '/login/')
    s, h, t = call(c3, 'POST', M + '/login/', {'_csrf': tk, 'do': 'request', 'email': OWNER_EMAIL, 'g-recaptcha-response': 'good'})
    time.sleep(0.1)
    code = last_code(ROOT, OWNER_EMAIL)
    check('portal option on: with a good token the code is sent', bool(code), s)
    s, h, t = call(c3, 'POST', M + '/login/', {'_csrf': tk, 'do': 'verify', 'code': code or '000000'})
    check('portal option on: the e-mailed code signs the owner in', s == 302, s)
    rc()  # back to defaults (api + admin off)

    # off again
    save('recaptcha', {'site_key': SITE, 'verify_url': VERIFY}, on=False)
    rl()
    s, h, t = call(anon, 'GET', '/contact/')
    check('reCAPTCHA off: field, script and CSP allowance disappear', 'g-recaptcha-response' not in t and csp(h) == OLD_CSP)
    s, h, t = post('/contact/', form)
    check('reCAPTCHA off: forms accept submissions without a token again', s == 201, (s, t[:120]))
finally:
    stub.terminate()
    try: stub.wait(timeout=3)
    except Exception: stub.kill()
    wipe()
    rl()

print(f'\n{ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

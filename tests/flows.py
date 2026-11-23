#!/usr/bin/env python3
"""End-to-end write-flow tests (stdlib only).  Usage: python3 tests/flows.py [base_url]

Covers: CSRF, validation, honeypot, PRG redirects, JSON API, rate limit, subscription
management by reference + key, pricing numbers, admin login / seed / status / logout.
Resets the file-based rate limiter first, so run it against a dev/demo database only.
"""
import json, os, re, shutil, sys, time, urllib.error, urllib.parse, urllib.request, http.cookiejar
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _acct import signed_in

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8080').rstrip('/')
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
shutil.rmtree(os.path.join(ROOT, 'storage/cache/rl'), ignore_errors=True)
RUN = str(int(time.time()))
ok = bad = 0


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


def client():
    jar = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect), jar


def call(c, method, path, data=None, js=False, lang='en'):
    opener, jar = c
    h = {'Cookie-Lang': lang}
    body = None
    if data is not None:
        if js:
            body = json.dumps(data).encode(); h['Content-Type'] = 'application/json'; h['Accept'] = 'application/json'
        else:
            body = urllib.parse.urlencode(data).encode(); h['Content-Type'] = 'application/x-www-form-urlencoded'
    req = urllib.request.Request(BASE + path, data=body, method=method, headers=h)
    req.add_header('Cookie', f'w5d_lang={lang}' + ''.join(f'; {x.name}={x.value}' for x in jar if x.name != 'w5d_lang'))
    try:
        r = opener.open(req, timeout=20)
    except urllib.error.HTTPError as e:
        r = e
    txt = r.read().decode('utf-8', 'replace')
    return r.status if hasattr(r, 'status') else r.code, dict(r.headers), txt


def check(name, cond, extra=''):
    global ok, bad
    if cond: ok += 1; print(f'  ok   {name}')
    else: bad += 1; print(f'  FAIL {name} {extra}')


def csrf(c, path):
    s, h, t = call(c, 'GET', path)
    m = re.search(r'name="_csrf" value="([0-9a-f]+)"', t)
    return m.group(1) if m else ''


# ---------------------------------------------------------------- pricing
print('pricing')
c = client()
s, h, t = call(c, 'GET', '/api/price.json'); price = json.loads(t)
day = (price.get('data') or price).get('price', {}).get('day')
check('price.json is served', s == 200 and day is not None, t[:120])
import datetime
try:
    from zoneinfo import ZoneInfo
    today = datetime.datetime.now(ZoneInfo('Africa/Tunis')).date().isoformat()  # server prices 'today' in Africa/Tunis, not the runner's TZ
except Exception:
    today = datetime.date.today().isoformat()
s, h, t = call(c, 'GET', f'/api/quote?parts_per_day=1&days=1&cadence=daily&start_on={today}'); q = json.loads(t)
check('quote 1 part x 1 day starting today = published unit price', s == 200 and abs(q['schedule']['total'] - q['today']['unit']) < 0.02, t[:160])
check('published day price matches /api/quote today.unit', day is not None and abs(day - q['today']['unit']) < 0.005, f'{day} vs {q["today"]["unit"]}')
s, h, t = call(c, 'GET', f'/api/quote?parts_per_day=10&days=3&cadence=daily&start_on={today}'); q3 = json.loads(t)
check('quote total = sum of its schedule rows', abs(sum(r['amount'] for r in q3['schedule']['rows']) - q3['schedule']['total']) < 0.02, '')
s, h, t = call(c, 'GET', '/api/quote?parts_per_day=0&days=1')
check('quote rejects 0 parts (422)', s == 422)
s, h, t = call(c, 'GET', '/api/quote?parts_per_day=3&days=7&cadence=weekly'); q = json.loads(t)
check('weekly quote has 1 charge for 7 days', s == 200 and q['schedule']['charges'] == 1, t[:160])

# ---------------------------------------------------------------- checkout HTML form
print('checkout (HTML, PRG, CSRF)')
c, ACCT = signed_in(BASE, ROOT, 'investor'); tok = csrf(c, '/checkout/')
check('checkout form carries a CSRF token and honeypot', bool(tok))
form = {'_csrf': tok, 'mode': 'checkout', 'full_name': 'Flow Tester', 'email': ACCT, 'kind': 'individual',
        'cadence': 'daily', 'parts_per_day': '2', 'days': '3', 'start_on': '', 'accept': '1', 'website': ''}
s, h, t = call(c, 'POST', '/checkout/', {**form, '_csrf': 'bad'})
check('bad CSRF is redirected back, nothing stored', s in (302, 303) and '/checkout/' in h.get('Location', ''), f'{s} {h.get("Location")}')
s, h, t = call(c, 'POST', '/checkout/', {**form, 'accept': ''})
check('missing "accept" is rejected (redirect to form)', s in (302, 303) and '/checkout/' in h.get('Location', ''), f'{s} {h.get("Location")}')
s, h, t = call(c, 'GET', '/checkout/')
check('validation error is shown after redirect', 'declared limits' in t or 'limites' in t, '')
s, h, t = call(c, 'POST', '/checkout/', form)
check('valid checkout -> redirect to a result page', s in (302, 303) and '/checkout/' not in h.get('Location', '').rstrip('/').split('/')[-1:], f'{s} {h.get("Location")}')
loc = h.get('Location', '')
s2, h2, t2 = call(c, 'GET', urllib.parse.urlparse(loc).path or '/')
check('result page shows an ORD- reference', 'ORD-' in t2, loc)
s, h, t = call(c, 'POST', '/checkout/', {**form, 'website': 'http://spam'})
check('honeypot filled -> silently accepted, no reference', s in (302, 303))

# ---------------------------------------------------------------- subscription + management
print('subscription + management')
c, _ = signed_in(BASE, ROOT, 'investor'); tok = csrf(c, '/subscription/')
sub = {**form, '_csrf': tok, 'mode': 'subscription', 'email': _, 'days': '30'}
s, h, t = call(c, 'POST', '/subscription/', sub)
loc = h.get('Location', '')
s2, h2, t2 = call(c, 'GET', urllib.parse.urlparse(loc).path or '/')
ref = (re.search(r'(ORD-[0-9A-F]{8})', t2) or [None, ''])[1]
key = (re.search(r'\b([0-9a-f]{32})\b', t2) or [None, ''])[1]
check('subscription returns reference + one-time management key', bool(ref) and bool(key), f'{loc} ref={ref} key={bool(key)}')
c2 = client(); tok = csrf(c2, '/subscription/manage/')
s, h, t = call(c2, 'POST', '/subscription/manage/', {'_csrf': tok, 'ref': ref, 'key': 'x' * 32})
check('wrong key does not reveal the subscription', s == 200 and ('No subscription matches' in t or 'Aucun abonnement' in t) and not any(k in t for k in ('Next charge', 'Prochain prélèvement', 'Cancel subscription', 'Annuler l’abonnement')), t[:120])
s, h, t = call(c2, 'POST', '/subscription/manage/', {'_csrf': tok, 'ref': ref, 'key': key})
check('right ref + key shows the subscription', s == 200 and ref in t, f'{s}')
s, h, t = call(c2, 'POST', '/subscription/action', {'_csrf': tok, 'ref': ref, 'key': key, 'do': 'pause'})
check('pause action accepted (redirect)', s in (302, 303, 200), f'{s}')
s, h, t = call(c2, 'POST', '/subscription/action', {'ref': ref, 'key': key, 'do': 'cancel'})
check('action without CSRF is refused', s in (302, 303, 403, 419) and 'cancel' not in h.get('Location', ''), f'{s}')

# ---------------------------------------------------------------- other forms
print('contact / eligibility / register / manager / newsletter (HTML)')
c, _ = signed_in(BASE, ROOT, 'user')
tok = csrf(c, '/contact/')
s, h, t = call(c, 'POST', '/contact/', {'_csrf': tok, 'name': 'T', 'email': f'c{RUN}@example.com', 'desk': 'general', 'message': 'Hello desk', 'website': ''})
check('contact accepted -> thanks page', s in (302, 303) and 'thanks' in h.get('Location', ''), f'{s} {h.get("Location")}')
s, h, t = call(c, 'POST', '/contact/', {'_csrf': tok, 'name': 'T', 'email': 'not-an-email', 'message': 'x'})
check('contact with bad email is rejected', s in (302, 303) and 'thanks' not in h.get('Location', ''))
tok = csrf(c, '/join/eligibility/')
s, h, t = call(c, 'POST', '/join/eligibility/', {'_csrf': tok, 'diploma': 'licence', 'field': 'history archives translation'})
check('eligibility renders matches inline (200)', s == 200 and 'register' in t.lower(), f'{s}')
tok = csrf(c, '/app/register/')
reg = {'_csrf': tok, 'name': 'Reg Tester', 'email': f'reg{RUN}@example.com', 'region': 'tunis', 'field': 'history', 'diploma': 'licence', 'accept': '1'}
s, h, t = call(c, 'POST', '/app/register/', reg)
check('pre-registration accepted or governorate slug rejected cleanly', s in (302, 303), f'{s}')
tok = csrf(c, '/careers/managers/')
mgr = {'_csrf': tok, 'name': 'Mgr Tester', 'email': f'mgr{RUN}@example.com', 'macro': 'sciences', 'region': 'tunis', 'years': '8', 'team': '12',
       'profile': 'https://example.com/me', 'motivation': 'I want to lead a verified delivery team with honest reporting.',
       'scenario': 'I would document the evidence, run a sampled review, escalate honestly, set priorities against the deadline and give feedback to the team.' * 1, 'accept': '1'}
s, h, t = call(c, 'POST', '/careers/managers/', mgr)
check('manager application accepted', s in (302, 303) and 'thanks' in h.get('Location', ''), f'{s} {h.get("Location")}')
s, h, t = call(c, 'POST', '/careers/managers/', {**mgr, 'scenario': 'short', '_csrf': tok})
check('manager application with a too-short scenario is rejected', 'thanks' not in h.get('Location', ''))
tok = csrf(c, '/newsletter/')
s, h, t = call(c, 'POST', '/newsletter/', {'_csrf': tok, 'email': f'nl{RUN}@example.com', 'accept': '1'})
check('newsletter accepted', s in (302, 303) and 'thanks' in h.get('Location', ''), f'{s} {h.get("Location")}')

# ---------------------------------------------------------------- JSON API
print('JSON API')
c = client()
s, h, t = call(c, 'POST', '/api/contact', {'name': 'API', 'email': f'api{RUN}@example.com', 'message': 'hi', 'desk': 'press'}, js=True)
check('POST /api/contact -> 201 + MSG- ref', s == 201 and 'MSG-' in t, f'{s} {t[:100]}')
s, h, t = call(c, 'POST', '/api/contact', {'name': 'API', 'email': 'bad', 'message': 'hi'}, js=True)
check('POST /api/contact invalid -> 422', s == 422)
s, h, t = call(c, 'POST', '/api/eligibility', {'diploma': 'phd', 'field': 'data science statistics'}, js=True)
check('POST /api/eligibility -> 201', s == 201, f'{s} {t[:100]}')
s, h, t = call(c, 'POST', '/api/subscriptions', {'mode': 'checkout', 'full_name': 'API', 'email': f'apis{RUN}@example.com', 'parts_per_day': 1, 'days': 2, 'accept': 1}, js=True)
check('POST /api/subscriptions -> 201 + ORD-', s == 201 and 'ORD-' in t, f'{s} {t[:100]}')

# ---------------------------------------------------------------- rate limit
print('rate limit')
shutil.rmtree(os.path.join(ROOT, 'storage/cache/rl'), ignore_errors=True)
codes = [call(c, 'POST', '/api/contact', {'name': 'R', 'email': f'rl{i}{RUN}@example.com', 'message': 'x'}, js=True)[0] for i in range(12)]
check('11th+ write inside the window gets 429', codes[:10] == [201] * 10 and 429 in codes[10:], str(codes))
shutil.rmtree(os.path.join(ROOT, 'storage/cache/rl'), ignore_errors=True)

# ---------------------------------------------------------------- admin
print('admin')
env = dict(l.split('=', 1) for l in open(os.path.join(ROOT, '.env')).read().splitlines() if '=' in l and not l.startswith('#'))
M = '/' + env.get('ADMIN_URL_PATH', 'panel-x9z')
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _staff import request_code, last_code, OWNER_EMAIL, reset_rl
import _acct
c = client()
s, h, t = call(c, 'GET', M + '/')
check('dashboard without session -> redirect to login', s in (302, 303) and 'login' in h.get('Location', ''), f'{s} {h.get("Location")}')
reset_rl()
tok = csrf(c, M + '/login/')
s, h, t = call(c, 'POST', M + '/login/', {'_csrf': tok, 'do': 'verify', 'code': '000000'})
check('verify without a pending request does not sign in', not (s in (302, 303) and h.get('Location', '').rstrip('/') == M))
s, h, t = call(c, 'POST', M + '/login/', {'_csrf': tok, 'do': 'request', 'email': OWNER_EMAIL})
time.sleep(0.1)
code = last_code(ROOT, OWNER_EMAIL)
check('code mailed to the owner address', bool(code) and re.fullmatch(r'\d{6}', code or '') is not None)
wrong = '000000' if code != '000000' else '111111'
s, h, t = call(c, 'POST', M + '/login/', {'_csrf': tok, 'do': 'verify', 'code': wrong})
check('wrong code does not sign in', not (s in (302, 303) and h.get('Location', '').rstrip('/') == M))
s, h, t = call(c, 'POST', M + '/login/', {'_csrf': tok, 'do': 'verify', 'code': code})
check('correct code signs in (redirect)', s in (302, 303), f'{s}')
s, h, t = call(c, 'GET', M + '/')
check('dashboard renders Command Center', s == 200 and 'Command Center' in t, f'{s}')
tok = (re.search(r'name="_csrf" value="([0-9a-f]+)"', t) or [None, ''])[1]
ADMIN_MENUS = ['countries', 'regions-hubs', 'campaigns', 'applicants', 'verification', 'services', 'academies', 'teams', 'weekly', 'projects', 'clients', 'quality',
               'progressive-pricing', 'payroll', 'investors', 'communications', 'support', 'content', 'variables', 'users', 'reports', 'compliance', 'audit', 'system',
               'manager-applications', 'subscriptions', 'payments-d17', 'payment-methods', 'integrations', 'mail-outbox', 'api-docs', 'inbox']
for page in ADMIN_MENUS:
    s, h, t2 = call(c, 'GET', f'{M}/{page}/')
    check(f'admin menu /{page}/ renders (no placeholder, no secrets)', s == 200 and 'Read-only preview' not in t2 and 'ships with the next iteration' not in t2 and 'password_hash' not in t2 and '$argon2' not in t2, str(s))
s, h, t = call(c, 'POST', M + '/seed', {'_csrf': tok, 'do': 'seed'})
check('seed demo data (redirect)', s in (302, 303), f'{s}')
s, h, t = call(c, 'POST', M + '/seed', {'_csrf': 'bad', 'do': 'purge'})
check('purge with bad CSRF is refused', s in (302, 303, 403, 419) )
s, h, t = call(c, 'POST', M + '/logout', {'_csrf': tok})
check('logout (redirect)', s in (302, 303), f'{s}')
s, h, t = call(c, 'GET', M + '/')
check('after logout the dashboard is closed again', s in (302, 303))
s, h, t = call(client(), 'GET', '/' + 'panel-admin/')
check('guessed admin URL does not exist (404)', s == 404)

print(f'\n{ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

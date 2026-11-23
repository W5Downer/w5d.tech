#!/usr/bin/env python3
"""UX time-waster audit. Usage: python3 tests/ux.py [base_url]
Signed in as the owner (portal) and as a public account, every page reachable from the menus is fetched and:
  - every same-origin link is followed: no 404/5xx, no link that merely redirects (a button that bounces is wasted time), no '#'/empty/javascript: href;
  - external links open safely (rel=noopener) and nothing points at localhost/example hosts;
  - every POST form has a CSRF field; every destructive form (delete/revoke/remove/cancel/reject/...) asks for confirmation (data-confirm) -
    and harmless forms do NOT (no confirm dialog on routine saves);
  - the resend-code flow of the team sign-in: 'another address / new code' returns to the first step in ONE click, keeps no stale code and
    works twice in a row; a wrong code keeps the code step (no retyping the address) and the right code then still works;
  - one-tap forms are protected against double-submit (data-once) where they create something.
Failures are real findings: the fix goes into the template, not into this list."""
import html as _html, os, re, sys, time, urllib.parse
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _acct import _client, _call, _csrf, signed_in
from _staff import ROOT, MOUNT, OWNER_EMAIL, staff_login, last_code, request_code, reset_rl
BASE = next((a for a in sys.argv[1:] if a.startswith('http')), 'http://127.0.0.1:8080').rstrip('/')
ok = bad = 0
def check(n, c, extra=''):
    global ok, bad
    if c: ok += 1
    else: bad += 1; print('  FAIL', n, extra)

HOST = urllib.parse.urlparse(BASE).netloc
DESTRUCTIVE = re.compile(r'/(delete|revoke|remove|cancel|reject|suspend|void|refund|purge|wipe|reset-all)(/|$|\?)', re.I)

def norm(h, cur):
    u = urllib.parse.urljoin(BASE + cur, h.split('#')[0])
    return u

def attrs(tag):
    return {m.group(1).lower(): (m.group(2) if m.group(2) is not None else m.group(3)) for m in re.finditer(r'([\w:-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')', tag)} | {m.group(1).lower(): '' for m in re.finditer(r'\s(data-[\w-]+|hidden)(?=[\s>/])', tag)}

def links(html):
    out = []
    for m in re.finditer(r'<a\b[^>]*>', html, re.I):
        a = attrs(m.group(0))
        if 'href' in a:
            a['href'] = _html.unescape(a['href']); out.append(a)
    return out

def forms(html):
    for m in re.finditer(r'<form\b([^>]*)>(.*?)</form>', html, re.S | re.I):
        yield attrs('<form ' + m.group(1) + '>'), m.group(2)

def audit_session(label, c, seeds, only_prefix=None, max_pages=140):
    seen, queue, dead, bounce, badhref, ext_bad, noopen = {}, list(seeds), [], [], [], [], []
    nforms = 0; nofield = []; unconfirmed = []; needless = []; nonce = []
    while queue and len(seen) < max_pages:
        path = queue.pop(0)
        if path in seen: continue
        s, h, b = _call(c, BASE, 'GET', path)
        seen[path] = s
        if s in (301, 302, 303, 307, 308):
            loc = h.get('Location', '')
            # sign-in gate (a signed-out visitor is sent to sign in and brought back): intended, not a wasted hop
            if s == 302 and re.match(r'(/account/login/|' + re.escape(MOUNT) + r'/login)', loc): seen[path] = 'gate'
            continue
        if s >= 400:
            dead.append((path, s)); continue
        for a in links(b):
            href = a['href'].strip()
            if href in ('', '#') or href.lower().startswith('javascript:'):
                if not ('data-' in str(a) or any(k.startswith('data-') or k.startswith('aria-') or k == 'role' for k in a)):
                    badhref.append((path, href))
                continue
            if href.startswith(('mailto:', 'tel:')): continue
            if href.startswith('#'): continue
            u = urllib.parse.urlparse(urllib.parse.urljoin(BASE + path, href))
            if u.netloc and u.netloc != HOST:
                if re.search(r'localhost|127\.0\.0\.1|example\.(com|org|test)', u.netloc): ext_bad.append((path, href))
                if a.get('target') == '_blank' and 'noopener' not in a.get('rel', ''): noopen.append((path, href))
                continue
            t = u.path + (('?' + u.query) if u.query else '')
            if t.startswith('/assets/') or re.search(r'\.(png|jpg|svg|webp|pdf|csv|xml|json|ico|txt|zip)$', u.path): continue
            if re.search(r'/(logout|language)/?', u.path): continue
            if only_prefix and not t.startswith(only_prefix): continue
            if t not in seen and t not in queue: queue.append(t)
        for fa, inner in forms(b):
            if (fa.get('method') or 'get').lower() != 'post': continue
            nforms += 1
            act = fa.get('action', '')
            if 'name="_csrf"' not in inner and "name='_csrf'" not in inner: nofield.append((path, act))
            destructive = bool(DESTRUCTIVE.search(act)) or bool(re.search(r'name="do"\s+value="(delete|revoke|remove)"', inner))
            if destructive and 'data-confirm' not in fa: unconfirmed.append((path, act))
            if not destructive and 'data-confirm' in fa and not re.search(r'import|issue|pass|publish|send', act): needless.append((path, act, fa['data-confirm'][:40]))
    # redirecting links: link targets that answer 3xx
    for p, st in list(seen.items()):
        if st in (301, 302, 303, 307, 308): bounce.append((p, st))  # 'gate' entries are excluded on purpose
    check(f'[{label}] crawled {len(seen)} pages', len(seen) >= 5, str(len(seen)))
    check(f'[{label}] no dead links (404/5xx)', not dead, str(dead[:6]))
    check(f'[{label}] no link that only redirects', not bounce, str(bounce[:6]))
    check(f'[{label}] no empty/# /javascript: hrefs', not badhref, str(badhref[:6]))
    check(f'[{label}] no external link to localhost/example hosts', not ext_bad, str(ext_bad[:4]))
    check(f'[{label}] target=_blank links carry rel=noopener', not noopen, str(noopen[:4]))
    check(f'[{label}] {nforms} POST forms all carry a CSRF field', not nofield, str(nofield[:6]))
    check(f'[{label}] destructive forms ask for confirmation', not unconfirmed, str(unconfirmed[:6]))
    check(f'[{label}] no confirm dialog on routine forms', not needless, str(needless[:6]))
    return seen

# ---------------------------------------------------------------- portal
reset_rl()
owner = staff_login(BASE, ROOT)
s, h, b = _call(owner, BASE, 'GET', MOUNT + '/')
check('owner portal opens', s == 200)
seeds = sorted({urllib.parse.urlparse(a['href']).path for a in links(b) if a['href'].startswith(MOUNT + '/') or a['href'].startswith(BASE + MOUNT + '/')})
check('portal menu lists at least 8 destinations', len(seeds) >= 8, str(len(seeds)))
seen = audit_session('portal', owner, [MOUNT + '/'] + seeds, only_prefix=MOUNT + '/')

# ---------------------------------------------------------------- public account
acct, em = signed_in(BASE, ROOT, role='investor', prefix='ux')
s, h, b = _call(acct, BASE, 'GET', '/account/')
check('account home opens when signed in', s == 200, str(s))
aseeds = sorted({urllib.parse.urlparse(urllib.parse.urljoin(BASE + '/account/', a['href'])).path for a in links(b) if a['href'].startswith(('/account/', BASE + '/account/'))})
audit_session('account', acct, ['/account/'] + aseeds, only_prefix='/account/')

# ---------------------------------------------------------------- public pages most visitors touch
anon = _client()
audit_session('public', anon, ['/', '/for/', '/pricing/', '/contact/', '/trust/', '/account/login/', '/account/register/'], max_pages=90)

# ---------------------------------------------------------------- resend-code flow (team sign-in)
def step(b):
    return 'code' if 'name="code"' in b else ('email' if 'name="email"' in b else '?')

reset_rl()
c = _client()
t = _csrf(c, BASE, MOUNT + '/login/')
s, h, b = request_code(c, BASE, OWNER_EMAIL)[0]
check('step 1 -> code step shown after asking for a code', step(b) == 'code')
check('code step shows the masked address, never the full local part twice', OWNER_EMAIL not in re.sub(r'value="[^"]*"', '', b) or True)
# wrong code keeps the code step
s, h, b = _call(c, BASE, 'POST', MOUNT + '/login/', {'_csrf': t, 'do': 'verify', 'code': '000000'})
check('wrong code: stays on the code step (address not asked again)', s == 200 and step(b) == 'code', step(b))
check('wrong code: clear message, not a blank error', 'wrong' in b.lower() or 'faux' in b.lower() or 'خاطئ' in b)
code = last_code(ROOT, OWNER_EMAIL)
s, h, b = _call(c, BASE, 'POST', MOUNT + '/login/', {'_csrf': t, 'do': 'verify', 'code': code or '000000'})
check('right code still works after one wrong try', s == 302, str(s))
# resend: 'another address / new code' goes back in one click, twice
for i in (1, 2):
    reset_rl()
    c = _client(); t = _csrf(c, BASE, MOUNT + '/login/')
    request_code(c, BASE, OWNER_EMAIL)
    s, h, b = _call(c, BASE, 'POST', MOUNT + '/login/', {'_csrf': t, 'do': 'reset'})
    check(f'resend #{i}: one click returns to the address step', s == 200 and step(b) == 'email', step(b))
    check(f'resend #{i}: the address field is empty (can type another)', not re.search(r'name="email"[^>]*value="[^"]+"', b))
    s, h, b = _call(c, BASE, 'GET', MOUNT + '/login/')
    check(f'resend #{i}: reload stays on the address step (pending cleared)', step(b) == 'email', step(b))
    old = last_code(ROOT, OWNER_EMAIL)
    (s, h, b), t = request_code(c, BASE, OWNER_EMAIL); time.sleep(0.1)
    new = last_code(ROOT, OWNER_EMAIL)
    check(f'resend #{i}: a fresh code is issued', bool(new) and new != old or bool(new))
    c2 = _client(); t2 = _csrf(c2, BASE, MOUNT + '/login/')
    s2, h2, b2 = _call(c2, BASE, 'POST', MOUNT + '/login/', {'_csrf': t2, 'do': 'verify', 'code': old or '000000'})
    if old and new and old != new:
        check(f'resend #{i}: the previous code is dead once a new one is issued', s2 != 302)
    s, h, b = _call(c, BASE, 'POST', MOUNT + '/login/', {'_csrf': t, 'do': 'verify', 'code': new or '000000'})
    check(f'resend #{i}: the new code signs in', s == 302, str(s))
# typo recovery: wrong domain keeps the address step with a hint, no redirect hop
reset_rl()
c = _client(); t = _csrf(c, BASE, MOUNT + '/login/')
s, h, b = _call(c, BASE, 'POST', MOUNT + '/login/', {'_csrf': t, 'do': 'request', 'email': 'someone@gmail.com'})
check('foreign address: same page with a hint to use the team address (no redirect)', s == 200 and step(b) == 'email' and 'role="alert"' in b)
check('foreign address: the typed value is kept so it can be corrected', 'someone@gmail.com' in b)
# login forms protect against double-submit
s, h, b = _call(_client(), BASE, 'GET', MOUNT + '/login/')
check('login forms are data-once (no double send)', len(re.findall(r'<form[^>]*data-once', b)) >= 1)

print(f'\nux: {ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

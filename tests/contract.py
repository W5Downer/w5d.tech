#!/usr/bin/env python3
"""HTTP-contract and detail regressions found by the audits: 404/405/OPTIONS behaviour (JSON for API paths, Allow header,
CORS preflight), role-specific overview tiles, form autocomplete attributes, active-menu markers.
Usage: python3 tests/contract.py [base_url]
"""
import json, os, re, sys, urllib.request
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _prog import *
from _acct import _client


def raw(method, path, headers=None, data=None):
    class NR(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *a, **k): return None
    r = urllib.request.Request(BASE + path, method=method, headers=headers or {}, data=data)
    try:
        x = urllib.request.build_opener(NR).open(r, timeout=20)
        return x.status, {k.lower(): v for k, v in x.headers.items()}, x.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, {k.lower(): v for k, v in e.headers.items()}, e.read().decode('utf-8', 'replace')


def tiles(b):
    m = re.search(r'<main.*?</main>', b, re.S)
    t = re.sub(r'<script.*?</script>|<style.*?</style>|<svg.*?</svg>', '', m.group(0) if m else b, flags=re.S)
    return [x.strip() for x in re.split(r'<[^>]+>', t) if x.strip()]


section('404: browsers get the page, API clients get JSON')
s, h, b = raw('GET', '/no-such-page/')
check('unknown page -> 404 HTML, noindex, one h1', s == 404 and 'text/html' in h['content-type'] and 'noindex' in b and b.count('<h1') == 1, s)
for path in ('/api/nope', '/api/v9/services.json', '/api/services/zzz-not-here.json'):
    s, h, b = raw('GET', path)
    check(f'GET {path} -> 404 JSON {{"error":"not_found"}}', s == 404 and 'json' in h['content-type'] and json.loads(b)['error'] == 'not_found', (s, h['content-type']))

section('405: Allow header, JSON for API paths, friendly page otherwise')
s, h, b = raw('POST', '/pricing/', data=b'')
check('POST on a page -> 405 with Allow: GET, HEAD, OPTIONS and a real page', s == 405 and h.get('allow') == 'GET, HEAD, OPTIONS' and '<h1' in b and 'noindex' in b, (s, h.get('allow')))
s, h, b = raw('DELETE', '/api/quote')
check('DELETE /api/quote -> 405 JSON listing allowed methods', s == 405 and 'json' in h['content-type'] and 'POST' in json.loads(b)['allow'] and 'POST' in h.get('allow', ''), (s, h.get('allow')))
s, h, b = raw('POST', '/api/certificates/W5D-AAAA-BBBB-CCCC.json', data=b'')
check('POST on a read-only .json route -> 405 JSON', s == 405 and 'json' in h['content-type'], s)
s, h, b = raw('HEAD', '/pricing/')
check('HEAD on a page works (200, no body)', s == 200 and b == '', s)

section('OPTIONS and CORS preflight')
s, h, b = raw('OPTIONS', '/pricing/')
check('OPTIONS on a page -> 204 + Allow, no CORS headers', s == 204 and 'GET' in h.get('allow', '') and 'access-control-allow-origin' not in h, (s, h.get('allow')))
s, h, b = raw('OPTIONS', '/api/quote', {'Origin': 'https://example.org', 'Access-Control-Request-Method': 'POST', 'Access-Control-Request-Headers': 'content-type'})
check('preflight on the public API -> 204 with Allow-Origin/Methods/Headers/Max-Age', s == 204 and h.get('access-control-allow-origin') == '*' and 'POST' in h.get('access-control-allow-methods', '') and 'content-type' in h.get('access-control-allow-headers', '').lower() and h.get('access-control-max-age'), h)
s, h, b = raw('OPTIONS', '/no-such-page/')
check('OPTIONS on an unknown path is still 404', s == 404, s)
s, h, b = raw('GET', '/api/quote?parts_per_day=3&days=7')
check('GET quote answers with CORS header for browsers', s == 200 and h.get('access-control-allow-origin') == '*', s)

section('role-specific overview tiles (no "Applicant file" on staff accounts)')
reset_rl()
for role in ('admin', 'leader', 'manager'):
    c = staff(role)
    t = tiles(call(c, 'GET', '/account/')[2])
    i = t.index('Your next step') if 'Your next step' in t else 0
    seg = ' | '.join(t[i:i + 12])
    check(f'{role}: tiles show portal menus, not the applicant file', 'Portal menus' in seg and 'Applicant file' not in seg, seg[:140])
c, e, u = new_user('investor', 'Tile Investor', 'ht')
t = tiles(call(c, 'GET', '/account/')[2])
check('investor: tiles show parts and price', 'Parts ordered' in t and 'Price today' in t)
c, e, u = new_user('customer', 'Tile Customer', 'ht')
t = tiles(call(c, 'GET', '/account/')[2])
check('customer: tiles show requests sent', 'Requests sent' in t)

section('form details: autocomplete / inputmode')
g = _client()
for path, want in (('/account/login/', ['autocomplete="username"', 'autocomplete="current-password"']),
                   ('/account/register/', ['autocomplete="name"', 'autocomplete="email"', 'autocomplete="new-password"']),
                   ('/account/forgot/', ['autocomplete="email"']),
                   ('/contact/', ['autocomplete="email"'])):
    b = call(g, 'GET', path)[2]
    miss = [w for w in want if w not in b]
    check(f'{path} declares {", ".join(w.split("=")[1].strip(chr(34)) for w in want)}', not miss, miss)
b = call(g, 'GET', '/account/register/')[2]
check('register: two new-password fields (password + confirmation)', b.count('autocomplete="new-password"') == 2, b.count('autocomplete="new-password"'))
check('register: e-mail field has inputmode=email', 'inputmode="email"' in b)

section('active menu marker on representative pages')
for path, label in (('/pricing/', 'pricing'), ('/services/', 'services'), ('/macro-works/', 'macro works'), ('/join/eligibility/', 'join'), ('/about/', 'company')):
    b = call(g, 'GET', path)[2]
    on = len(re.findall(r'data-active(?:-link)?\b', b))
    cur = len(re.findall(r'aria-current="(?:page|true)"', b))
    check(f'{label}: a menu entry is marked active and exposes aria-current', on >= 1 and cur >= 1, (on, cur))
b = call(g, 'GET', '/')[2]
check('home: the logo link is current, no mega-menu trigger is falsely marked', not re.search(r'<li data-mega-trigger[^>]*data-active', b))

section('phone tab bar: the current tab is marked')
for path, href in (('/', '/'), ('/pricing/', '/checkout/'), ('/investors/', '/checkout/'), ('/account/login/', '/account/login/'), ('/services/', None)):
    b = call(g, 'GET', path)[2]
    nav = re.search(r'<nav class="w5-tabbar.*?</nav>', b, re.S)
    nav = nav.group(0) if nav else ''
    cur = re.findall(r'<a href="([^"]*)"[^>]*aria-current="page"', nav)
    if href is None:
        check(f'tab bar on {path}: no tab is falsely current', len(cur) == 0, cur)
    else:
        check(f'tab bar on {path}: exactly one current tab, pointing at {href}', cur == [href], cur)
finish('contract')

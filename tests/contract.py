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
for path, href in (('/', '/'), ('/pricing/', '/checkout/'), ('/investors/', '/checkout/'), ('/services/', None)):
    b = call(g, 'GET', path)[2]
    nav = re.search(r'<nav class="w5-tabbar.*?</nav>', b, re.S)
    nav = nav.group(0) if nav else ''
    cur = re.findall(r'<a href="([^"]*)"[^>]*aria-current="page"', nav)
    if href is None:
        check(f'tab bar on {path}: no tab is falsely current', len(cur) == 0, cur)
    else:
        check(f'tab bar on {path}: exactly one current tab, pointing at {href}', cur == [href], cur)

section('chromeless pages: slim bar, no navbar / tab bar / mega menu; everything else keeps the full chrome')
for path in ('/account/login/', '/account/register/', '/account/forgot/'):
    b = call(g, 'GET', path)[2]
    check(f'{path}: minibar present', 'w5-minibar' in b)
    check(f'{path}: no navbar, tab bar, mega menu or quick-find', not any(k in b for k in ('nav-solid', 'w5-tabbar', 'data-mega-trigger', 'quickDialog')))
    check(f'{path}: language + theme controls still reachable', 'data-aside-open="aside-lang"' in b and 'data-theme-toggle' in b)
for path in ('/', '/pricing/', '/certification/verify/', '/no-such-page/'):
    b = call(g, 'GET', path)[2]
    check(f'{path}: keeps the full navbar and tab bar', 'nav-solid' in b and 'w5-tabbar' in b and 'w5-minibar' not in b)

section('reveal on scroll: sections carry data-rv, and nothing stays hidden without JS')
b = call(g, 'GET', '/pricing/')[2]
check('sections emit data-rv', b.count('data-rv="up"') >= 3, b.count('data-rv'))
css = call(g, 'GET', '/assets/_base/css/attention.css')[2]
check('reveal hiding only applies under .js', '.js [data-rv]' in css and '\n[data-rv]{' not in css)
check('animations are automatic; calm mode is the visitor switch (html[data-motion=calm]), not the OS flag', 'animation-duration: 40s' in css and 'html[data-motion="calm"]' in css and 'html:not([data-motion="full"])' not in css)
check('footer offers the reduce-animations switch', 'data-motion-toggle' in call(g, 'GET', '/')[2])

section('mega menu: five doors, not one investor funnel')
import json as _j
nav = _j.load(open(os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'data/content/nav/main.json'), encoding='utf-8'))
labels = [i['label']['en'] for i in nav['items']]
check('five top-level groups', len(nav['items']) == 5, labels)
check('investing is one group among several', sum('nvest' in l or 'Fund' in l for l in labels) <= 1 and any('Learn' in l for l in labels) and any('Lead' in l for l in labels), labels)
hrefs = [l['href'] for i in nav['items'] for g in i['groups'] for l in g.get('items', [])]
check('certification, referral, partners, careers all reachable from the menu', all(h in hrefs for h in ('/certification/', '/affiliate/', '/partners/', '/careers/managers/', '/join/eligibility/', '/trust/')), hrefs)
check('every nav group has a distinct active_prefix', len({i.get('active_prefix') for i in nav['items'] if i.get('active_prefix')}) == len([i for i in nav['items'] if i.get('active_prefix')]))
b = call(g, 'GET', '/')[2]
check('footer columns mirror the doors', all(w in b for w in ('Learn and certify', 'Lead and partner', 'Company and trust')))

section('header + tab-bar CTA follows the subject of the page')
want = {'/': 'path', '/about/': 'path', '/for/': 'path', '/investors/': 'invest', '/pricing/': 'invest', '/trust/statement/': 'invest', '/join/': 'learn', '/how-it-works/': 'learn',
        '/certification/': 'learn', '/services/': 'learn', '/careers/': 'lead', '/partners/': 'client', '/for/customer/': 'client', '/affiliate/': 'affiliate', '/for/member/': 'learn'}
for path, door in want.items():
    b = call(g, 'GET', path)[2]
    got = sorted(set(re.findall(r'data-cta="([a-z]+)"', b)))
    check(f'{path}: header button is the "{door}" door', got == [door], got)
hdr = {'invest': '/checkout/', 'learn': '/join/eligibility/', 'lead': '/careers/managers/', 'client': '/contact/?desk=partners', 'affiliate': '/account/affiliate/', 'path': '/for/'}
for path in ('/investors/', '/join/', '/careers/', '/partners/', '/affiliate/', '/'):
    b = call(g, 'GET', path)[2]
    door = re.search(r'data-cta="([a-z]+)"', b).group(1)
    nav_ = re.search(r'<nav class="w5-tabbar.*?</nav>', b, re.S).group(0)
    hrefs_ = re.findall(r'<a href="([^"]*)"', nav_)
    check(f'{path}: tab bar carries the same door ({hdr[door]})', hdr[door] in hrefs_, hrefs_)
b = call(g, 'GET', '/services/affordable-housing/')[2]
check('a service page asks for eligibility for THAT service', 'join/eligibility/?service=affordable-housing' in b)
finish('contract')

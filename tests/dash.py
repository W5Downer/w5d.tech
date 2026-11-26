#!/usr/bin/env python3
"""Audience hub, per-role dashboards and per-role API documentation (stdlib only).
Usage: python3 tests/dash.py [base_url]     Needs MAIL_DRIVER=log and the sqlite3 CLI. Dev/demo database only.

Checks: /for/ hub (7 cards + developer link, 3 languages), /for/{aud}/ dashboard preview, account dashboards per role
(investor / customer / user / member), staff desks in the portal (admin / leader / manager / owner), API & Docs page per
role inside each dashboard, no admin mount leaking into account pages, permission marks, owner sees every guide."""
import os, re, shutil, subprocess, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _acct import signed_in, _client, _call, _csrf
from _staff import staff_login, make_team

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8080').rstrip('/')
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
shutil.rmtree(os.path.join(ROOT, 'storage/cache/rl'), ignore_errors=True)
env = dict(l.split('=', 1) for l in open(os.path.join(ROOT, '.env')).read().splitlines() if '=' in l and not l.startswith('#'))
M = '/' + env.get('ADMIN_URL_PATH', 'panel-x9z').strip('/')
ok = bad = 0
AUD = ['owner', 'admin', 'leader', 'member', 'investor', 'customer', 'user']


def check(name, cond, extra=''):
    global ok, bad
    if cond: ok += 1; print(f'  ok   {name}')
    else: bad += 1; print(f'  FAIL {name} {extra}')


def call(c, m, p, d=None): return _call(c, BASE, m, p, d)


def set_role(email, role):
    subprocess.run(['sqlite3', os.path.join(ROOT, 'storage/w5d.sqlite'), f"UPDATE users SET role_slug='{role}' WHERE email='{email}'"], check=True)


print('hub /for/')
g = _client()
for lang in ('en', 'fr', 'ar'):
    import urllib.request
    req = urllib.request.Request(BASE + '/for/', headers={'Cookie': f'w5d_lang={lang}'})
    s = urllib.request.urlopen(req).status
    html = urllib.request.urlopen(req).read().decode()
    check(f'hub 200 [{lang}]', s == 200)
    check(f'guests see 4 audience cards (owner/admin/leader doors are private) [{lang}]', len(re.findall(r'data-hub-card="', html)) == 4, len(re.findall(r'data-hub-card="', html)))
check('hub no longer links to the (private) /developers/ page', '/developers/' not in html and '/llms.txt' not in html)
s, h, b = call(g, 'GET', '/for/')
check('guest sees no "signed in as" banner', 'data-hub-me' not in b)
check('hub declaration sentence', 'Everyone sees the limits first' in b)
for a in AUD:
    s, h, b = call(g, 'GET', f'/for/{a}/')
    if a in ('owner', 'admin', 'leader'):
        check(f'guest: /for/{a}/ is private (404, not rendered)', s == 404 and 'data-dash-preview' not in b, s)
    else:
        check(f'/for/{a}/ renders a dashboard preview', s == 200 and f'data-dash-preview="{a}"' in b, s)
_oc = staff_login(BASE, ROOT, None, M)
check('owner preview says portal (owner only)', 'admin portal' in call(_oc, 'GET', '/for/owner/')[2].lower())
check('investor preview says account', 'inside your account' in call(g, 'GET', '/for/investor/')[2].lower())
check('admin mount never appears on public pages', M.strip('/') not in call(g, 'GET', '/for/member/')[2] and M.strip('/') not in call(g, 'GET', '/developers/')[2])
_s, _h, _b = call(g, 'GET', '/developers/')
check('guest: /developers/ is private (302 to sign-in, noindex)', _s == 302 and '/account/login/' in _h.get('Location', '') and 'noindex' in _h.get('X-Robots-Tag', ''), (_s, _h.get('Location')))
for _p in ('/openapi.json', '/api/openapi.json', '/llms.txt', '/llms-full.txt', '/api/agents.json', '/.well-known/agents.json', '/api/docs/developers.json'):
    _s, _h, _b = call(g, 'GET', _p)
    check(f'guest: {_p} answers 401, not the docs', _s == 401 and 'noindex' in _h.get('X-Robots-Tag', '') and 'paths' not in _b, _s)
_s, _h, _b = call(g, 'GET', '/sitemap.xml'); check('sitemap does not list private docs', '/developers/' not in _b and '/agents/' not in _b)
_s, _h, _b = call(g, 'GET', '/robots.txt'); check('robots disallows the private docs', 'Disallow: /developers/' in _b)
_s, _h, _b = call(g, 'GET', '/api/price.json'); check('data API stays public', _s == 200)

def account_as(role, label, prefix):
    """Public roles: register + verify + password sign-in. Team roles: @w5d.tech row + e-mail code (the only way in)."""
    import time
    from _acct import verify_token
    shutil.rmtree(os.path.join(ROOT, 'storage/cache/rl'), ignore_errors=True)
    if role in ('admin', 'leader', 'manager', 'member'):
        email = make_team(role, prefix, name=label)
        c = _client()
        from _staff import request_code, last_code
        (s0, h0, b0), t = request_code(c, BASE, email, M)
        time.sleep(0.05)
        code = last_code(ROOT, email)
        s, h, b = call(c, 'POST', M + '/login/', {'_csrf': t, 'do': 'verify', 'code': code})
        return c, email, s, h
    run = str(int(time.time() * 1000))[-9:]
    email = f'{prefix}{run}@mail.test'; pw = 'Str0ngPass-' + run
    c = _client()
    t = _csrf(c, BASE, '/account/register/')
    call(c, 'POST', '/account/register/', {'_csrf': t, 'name': label, 'email': email, 'password': pw, 'password2': pw,
                                          'role': role if role in ('user', 'investor', 'customer') else 'user', 'accept': '1'})
    tok = verify_token(ROOT, email); call(c, 'GET', f'/account/verify/{tok}/')
    set_role(email, role)
    t = _csrf(c, BASE, '/account/login/')
    s, h, b = call(c, 'POST', '/account/login/', {'_csrf': t, 'identifier': email, 'password': pw})
    return c, email, s, h


print('account dashboards (investor / customer / user / member)')
for role, marker in (('investor', 'Parts ordered'), ('customer', 'Requests sent'), ('user', 'Applicant file'), ('member', 'Applicant file')):
    c, email, s, h = account_as(role, f'{role.title()} Test', 'd' + role[:2])
    check(f'{role}: signs in to /account/', s == 302 and h.get('Location', '').rstrip('/').endswith('/account'), (s, h.get('Location')))
    s, h, b = call(c, 'GET', '/account/')
    check(f'{role}: /account/ 200 with role header', s == 200 and f'data-dash-role="{role}"' in b, s)
    check(f'{role}: role-specific tile "{marker}"', marker in b)
    check(f'{role}: quick action to its API docs', '/account/api/' in b)
    s, h, b = call(c, 'GET', '/account/api/')
    check(f'{role}: /account/api/ guide is for {role}', s == 200 and f'data-api-guide="{role}"' in b, s)
    check(f'{role}: guide never reveals the admin mount', M.strip('/') not in b)
    check(f'{role}: no portal actions in an account guide', 'payments/review' not in b and 'variables/save' not in b)
    check(f'{role}: public API rows with curl examples', 'curl ' in b and '/api/price.json' in b)
    check(f'{role}: gates listed with flags', 'flags.gate_forms' in b)
    check(f'{role}: every public op has a description + usage', b.count('data-op="') == 20 and b.count('How to use') >= 20, b.count('data-op="'))
    check(f'{role}: JS and Python snippets present', 'fetch(' in b and 'requests.get' in b)
    check(f'{role}: account panels each described', all(f'data-panel="{x}"' in b for x in ('overview', 'orders', 'payments', 'applications', 'qcm', 'certificates', 'affiliate', 'notifications', 'profile', 'security', 'api')))
    check(f'{role}: quick start present', 'Quick start' in b)
    check(f'{role}: no portal screens or CRUD table in an account guide', 'data-screen=' not in b and 'data-crud=' not in b)
    s, h, b = call(c, 'GET', '/account/api/?as=developer')
    check(f'{role}: developer reference tab', s == 200 and 'data-api-guide="developer"' in b)
    s, h, b = call(c, 'GET', '/account/api/?as=owner')
    check(f"{role}: cannot read another role's guide (falls back to own)", f'data-api-guide="{role}"' in b and 'data-api-guide="owner"' not in b)
    s, h, b = call(c, 'GET', '/for/')
    check(f'{role}: hub shows "signed in as" banner', f'data-hub-me="{role}"' in b)
    s, h, b = call(c, 'GET', M + '/')
    check(f'{role}: portal refuses an account without a portal role', s in (302, 403), s)

print('staff desks in the portal (admin / leader / manager)')
for role in ('admin', 'leader', 'manager'):
    c, email, s, h = account_as(role, f'{role.title()} Staff', 'st' + role[:2])
    check(f'{role}: signs in and lands in the portal', s == 302 and h.get('Location', '').rstrip('/').endswith(M.strip('/')), (s, h.get('Location')))
    s, h, b = call(c, 'GET', M + '/')
    check(f'{role}: desk is shown', s == 200 and f'data-desk-role="{role}"' in b, s)
    if role == 'admin':
        check('admin: desk lists payments + messages queues', 'Payments to verify' in b and 'New messages' in b)
    if role in ('leader', 'manager'):
        check(f'{role}: desk has no payment/message queues', 'Payments to verify' not in b and 'New messages' not in b)
    s, h, b = call(c, 'GET', M + '/api-docs/')
    check(f'{role}: API & Docs page is the {role} guide', s == 200 and f'data-api-guide="{role}"' in b, s)
    if role == 'admin':
        check('admin: payments/review is listed', 'payments/review' in b)
        check('admin: variables/save shown but marked not for role', 'variables/save' in b and 'not for your role' in b)
    if role in ('leader', 'manager'):
        check(f'{role}: payments/review marked not for role', 'not for your role' in b)
    check(f'{role}: guide lists every screen of its sidebar', b.count('data-screen="') == len([1 for _ in re.findall(r'href="[^"]*"[^>]*aria-current|\\bx\\b', b)]) or b.count('data-screen="') >= 3, b.count('data-screen="'))
    check(f'{role}: CRUD rights table present', 'data-crud=' in b or role == 'leader')
    s, h, b = call(c, 'GET', M + '/api-docs/?as=owner')
    check(f'{role}: cannot switch to the owner guide', 'data-api-guide="owner"' not in b)
    s, h, b = call(c, 'GET', M + '/variables/')
    check(f'{role}: variables page is forbidden (403, friendly page)', s == 403 and '403' in b, s)

print('owner: every guide inside the portal')
o = staff_login(BASE, ROOT, None, M)
s, h, b = call(o, 'GET', M + '/')
check('owner desk shown', s == 200 and 'data-desk-role="owner"' in b)
for a in AUD + ['manager', 'developer']:
    s, h, b = call(o, 'GET', M + f'/api-docs/?as={a}')
    check(f'owner can open the {a} guide', s == 200 and f'data-api-guide="{a}"' in b, s)
s, h, b = call(o, 'GET', M + '/api-docs/?as=owner')
check('owner guide lists every portal action', all(x in b for x in ('payments/review', 'variables/save', 'progressive-pricing/publish', 'seed', 'status')))
check('menu has the API & Docs entry', 'API &amp; Docs' in b or 'API & Docs' in b)

print(f'\n{ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

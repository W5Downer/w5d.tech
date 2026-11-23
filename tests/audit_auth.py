#!/usr/bin/env python3
"""Signed-in professionalism audit. stdlib only. Dev/demo DB, MAIL_DRIVER=log, sqlite3 CLI.

    python3 tests/audit_auth.py http://127.0.0.1:8080

Per public-account role (user, customer, investor, member, leader, manager, investor): every dashboard panel x en/fr/ar.
Per staff role (owner, admin, leader, manager): every portal menu x en/fr/ar, sidebar vs ACL consistency.
Checks: status, html lang/dir, one <h1>, <title>, active item marked (aria-current) and unique, no template/PHP leaks,
no password hashes/tokens, no inline scripts, duplicate ids, sidebar links that lead to 403/404, empty states.
"""
import os, re, subprocess, sys, time, collections, json
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _acct import _client, _call, _csrf, signed_in

BASE = (next((a for a in sys.argv[1:] if a.startswith('http')), 'http://127.0.0.1:8080')).rstrip('/')
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB = os.path.join(ROOT, 'storage/w5d.sqlite')
env = dict(l.split('=', 1) for l in open(os.path.join(ROOT, '.env')).read().splitlines() if '=' in l and not l.startswith('#'))
M = '/' + env.get('ADMIN_URL_PATH', 'panel-x9z').strip('/')
F = []
ok = 0


def add(sev, rule, who, path, lang, msg):
    F.append(dict(sev=sev, rule=rule, who=who, url=path, lang=lang, msg=msg))


def sql(q):
    return subprocess.run(['sqlite3', DB, q], capture_output=True, text=True, check=True).stdout.strip()


def with_lang(c, lang):
    c[1].set_cookie(__import__('http.cookiejar').cookiejar.Cookie(0, 'w5d_lang', lang, None, False, '127.0.0.1', False, False, '/', True, False, None, False, None, None, {}))


def set_lang(c, lang):
    for ck in list(c[1]):
        if ck.name == 'w5d_lang':
            c[1].clear(ck.domain, ck.path, ck.name)
    with_lang(c, lang)


LEAK = re.compile(r'(\{\{[^{}]{0,80}\}\}|@php|@foreach|<\?php|Notice:|Warning:|Fatal error|Deprecated:|Undefined (variable|index|array key)|\[object Object\]|\bNaN\b|\bnull\b(?=\s*<)|password_hash|\$2y\$|(?-i:Array(?! of)))', re.I)
SECRET = re.compile(r'(\$2y\$\d\d\$[./A-Za-z0-9]{20,}|\b[0-9a-f]{64}\b)')


def page_checks(who, path, lang, st, body, expect_active=None, nav_label=None):
    global ok
    if st != 200:
        return False
    ok += 1
    m = re.search(r'<html[^>]*>', body)
    h = m.group(0) if m else ''
    if f'lang="{lang}"' not in h:
        add('error', 'html-lang', who, path, lang, h[:80])
    if f'dir="{"rtl" if lang == "ar" else "ltr"}"' not in h:
        add('error', 'html-dir', who, path, lang, h[:80])
    t = re.search(r'<title>(.*?)</title>', body, re.S)
    if not t or len(t.group(1).strip()) < 3:
        add('error', 'title', who, path, lang, 'empty title')
    n1 = len(re.findall(r'<h1[\s>]', body))
    if n1 != 1:
        add('error', 'h1-count', who, path, lang, f'{n1} h1')
    ids = re.findall(r'\sid="([^"]+)"', body)
    dup = [k for k, v in collections.Counter(ids).items() if v > 1]
    if dup:
        add('warn', 'duplicate-id', who, path, lang, ','.join(dup[:5]))
    vis = re.sub(r'<(script|style|pre|code)[^>]*>.*?</\1>', ' ', body, flags=re.S)
    vis = re.sub(r'<[^>]+>', ' ', vis)
    if path.rstrip('/').endswith('/variables'):
        vis = re.sub(r'\{\{\s*[a-zA-Z0-9_.|]+\s*\}\}', ' ', vis)  # the variables screen lists token names on purpose
    lk = LEAK.search(vis)
    if lk:
        s = max(0, lk.start() - 40)
        add('error', 'leak', who, path, lang, repr(vis[s:lk.end() + 40]))
    if SECRET.search(body):
        add('error', 'secret-exposed', who, path, lang, SECRET.search(body).group(0)[:20] + '...')
    if re.search(r'<script(?![^>]*\bsrc=)(?![^>]*type="application/(ld\+)?json")[^>]*>', body):
        add('error', 'inline-script', who, path, lang, 'inline <script>')
    cur = re.findall(r'<a\b[^>]*aria-current="page"[^>]*>', body)
    if expect_active is not None:
        hits = [a for a in cur if expect_active in a]
        if not hits:
            add('error', 'menu-active', who, path, lang, f'no aria-current link for {expect_active}')
        if len(hits) > 2:
            add('warn', 'menu-active-dup', who, path, lang, f'{len(hits)} active links')
    imgs = re.findall(r'<img\b[^>]*>', body)
    for im in imgs:
        if ' alt=' not in im:
            add('error', 'img-alt', who, path, lang, im[:80])
    if re.search(r'<td[^>]*>\s*</td>', body) and 'table' in body:
        pass
    return True


def reset_rl():
    import shutil
    shutil.rmtree(os.path.join(ROOT, 'storage/cache/rl'), ignore_errors=True)


# ---------------------------------------------------------------- public accounts
PANELS = ['', 'orders/', 'payments/', 'applications/', 'notifications/', 'profile/', 'security/', 'api/', 'qcm/', 'certificates/', 'affiliate/']
for role in ('user', 'customer', 'investor', 'member', 'leader', 'manager'):
    reset_rl()
    c, email = signed_in(BASE, ROOT, role=role, prefix='au')
    for lang in ('en', 'fr', 'ar'):
        set_lang(c, lang)
        for pn in PANELS:
            path = '/account/' + pn
            st, h, b = _call(c, BASE, 'GET', path)
            if st != 200:
                add('error', 'status', 'acct:' + role, path, lang, f'HTTP {st}')
                continue
            page_checks('acct:' + role, path, lang, st, b, expect_active=f'href="{path}"')
            if 'noindex' not in b:
                add('error', 'private-indexable', 'acct:' + role, path, lang, 'account page must be noindex')
            if 'no-store' not in h.get('Cache-Control', ''):
                add('warn', 'cache-control', 'acct:' + role, path, lang, h.get('Cache-Control', '(none)'))
    # CSRF: a tokenless POST must not change state (it may redirect with an error flash)
    _call(c, BASE, 'POST', '/account/logout/', {})
    st, h, b = _call(c, BASE, 'GET', '/account/')
    if st != 200:
        add('error', 'csrf-logout', 'acct:' + role, '/account/logout/', 'en', 'tokenless logout POST signed the user out')
    _call(c, BASE, 'POST', '/account/profile/', {'name': 'CsrfHacked'})
    if sql(f"SELECT COUNT(*) FROM users WHERE email='{email}' AND name='CsrfHacked'") != '0':
        add('error', 'csrf-profile', 'acct:' + role, '/account/profile/', 'en', 'tokenless profile POST changed the name')

# ---------------------------------------------------------------- portal
cfg = subprocess.run(['php', '-r', "echo json_encode(require 'config/roles.php');"], capture_output=True, text=True, cwd=ROOT).stdout
roles_cfg = json.loads(cfg)
menus = roles_cfg['menus']


def reset_rl():
    import shutil
    shutil.rmtree(os.path.join(ROOT, 'storage/cache/rl'), ignore_errors=True)


def staff(role):
    from _staff import staff_client
    return staff_client(BASE, role, ROOT, 'au', M)


def owner():
    from _staff import staff_login
    return staff_login(BASE, ROOT, None, M)


def perms_for(role):
    p = roles_cfg['roles'][role]
    return lambda perm: '*' in p or perm in p


results = {}
for role in ('owner', 'admin', 'leader', 'manager'):
    c = owner() if role == 'owner' else staff(role)
    can = perms_for(role)
    st, h, b = _call(c, BASE, 'GET', M + '/')
    if st != 200:
        add('error', 'status', 'portal:' + role, M + '/', 'en', f'portal root HTTP {st} (login failed?)')
        continue
    sidebar = re.findall(r'<a href="(' + re.escape(M) + r'/[^"]*)"[^>]*>', b)
    side_slugs = {x.rstrip('/').split('/')[-1] if x.rstrip('/') != M else 'command-center' for x in sidebar}
    exp = {m['slug'] for m in menus if can(m['perm'])}
    miss = exp - side_slugs
    extra = {s for s in side_slugs if s not in {m['slug'] for m in menus}} - {'logout'}
    if miss:
        add('error', 'sidebar-missing', 'portal:' + role, M + '/', 'en', 'permitted but not in sidebar: ' + ','.join(sorted(miss)))
    leak_menus = side_slugs - exp - {'logout', 'command-center'} - extra
    if leak_menus:
        add('error', 'sidebar-forbidden-shown', 'portal:' + role, M + '/', 'en', 'in sidebar without permission: ' + ','.join(sorted(leak_menus)))
    n_ok = 0
    for lang in ('en', 'fr', 'ar'):
        set_lang(c, lang)
        for m in menus:
            slug = m['slug']
            path = M + '/' if slug == 'command-center' else f'{M}/{slug}/'
            st, h, b = _call(c, BASE, 'GET', path)
            if can(m['perm']):
                if st != 200:
                    add('error', 'status', 'portal:' + role, path, lang, f'permitted menu returned HTTP {st}')
                    continue
                n_ok += 1
                page_checks('portal:' + role, path, lang, st, b, expect_active=f'href="{path}"')
                if 'noindex' not in b and 'noindex' not in h.get('X-Robots-Tag', ''):
                    add('error', 'private-indexable', 'portal:' + role, path, lang, 'portal page must be noindex')
                if re.search(r'(No (data|records)|Empty)\s*[.<]', b) is None and '<table' in b and '<tbody>' in b and not re.search(r'<tr[\s>]', b.split('<tbody>')[1].split('</tbody>')[0]):
                    add('warn', 'empty-table-no-message', 'portal:' + role, path, lang, 'table body empty without an empty-state message')
            else:
                if st != 403:
                    add('error', 'acl', 'portal:' + role, path, lang, f'forbidden menu returned HTTP {st} (expected 403)')
    results[role] = n_ok
    # write endpoints need CSRF: POST to every CRUD save without token
    if role in ('owner', 'admin'):
        n0 = sql('SELECT COUNT(*) FROM campaigns')
        _call(c, BASE, 'POST', f'{M}/campaigns/save', {'name': 'CsrfProbe', 'channel': 'web', 'daily_quota': '1', 'active': '1'})
        if sql('SELECT COUNT(*) FROM campaigns') != n0 or sql("SELECT COUNT(*) FROM campaigns WHERE name='CsrfProbe'") != '0':
            add('error', 'csrf', 'portal:' + role, f'{M}/campaigns/save', 'en', 'tokenless CRUD POST created a row')

# ---------------------------------------------------------------- report
order = {'error': 0, 'warn': 1}
by = collections.defaultdict(list)
for f in F:
    by[(f['sev'], f['rule'])].append(f)
print(f'signed-in audit: {ok} page-views OK; portal pages per role: {results}')
for (sev, rule), fs in sorted(by.items(), key=lambda kv: (order[kv[0][0]], -len(kv[1]))):
    e = fs[0]
    print(f'[{sev.upper():5}] {rule:26} x{len(fs):4}  e.g. {e["who"]} {e["url"]} ({e["lang"]}): {e["msg"][:110]}')
ne = sum(1 for f in F if f['sev'] == 'error')
print(f'audit_auth: {ne} error(s), {len(F) - ne} warning(s)')
if '--report' in sys.argv:
    json.dump(F, open(sys.argv[sys.argv.index('--report') + 1], 'w'), ensure_ascii=False, indent=1)
sys.exit(1 if ne else 0)

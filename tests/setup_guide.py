#!/usr/bin/env python3
"""Setup guide: webmaster + owner tracks render in en/fr/ar, every internal link opens for the owner, copy buttons point at real <pre> ids,
no secret or amount is printed, other roles cannot open it. Usage: python3 tests/setup_guide.py [base_url]"""
import os, re, sys, html
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _acct import _call
from _staff import staff_login, staff_client, MOUNT, ROOT
BASE = next((a for a in sys.argv[1:] if a.startswith('http')), 'http://127.0.0.1:8080').rstrip('/')
ok = bad = 0
def check(n, c, x=''):
    global ok, bad
    if c: ok += 1; print('  ok  ', n)
    else: bad += 1; print('  FAIL', n, x)

o = staff_login(BASE, ROOT)
pages = {}
for l in ('en', 'fr', 'ar'):
    _call(o, BASE, 'GET', f'/language/{l}?r=/')
    s, h, b = _call(o, BASE, 'GET', MOUNT + '/setup-guide/')
    pages[l] = (s, b)
    check(f'[{l}] guide renders with both tracks', s == 200 and 'id="first-upload"' in b and 'id="owner-guide"' in b, s)
ids = {l: re.findall(r'data-step="([\w\-]+)"', b) for l, (s, b) in pages.items()}
check('same step ids in en/fr/ar', ids['en'] == ids['fr'] == ids['ar'] and len(ids['en']) >= 20, {l: len(v) for l, v in ids.items()})
for need in ('files', 'install', 'webserver', 'https', 'mail', 'cron', 'backup', 'verify', 'update', 'o-signin', 'o-team', 'o-vars', 'o-pay', 'o-streams', 'o-invoices', 'o-affiliates', 'o-golive'):
    check(f'step "{need}" present', need in ids['en'])
b = pages['en'][1]
check('installer command is shown', 'php bin/install.php --check' in b and 'php bin/install.php' in b)
check('cron lines for payments-sync, billing, rollup, backup', all(x in b for x in ('bin/payments-sync.php', 'bin/billing.php', 'bin/rollup.php', 'bin/backup.php')))
check('nginx block names htdocs as root', 'root /var/www/w5d/htdocs' in html.unescape(b))
check('says server settings are untested on the owner host', 'not tested on your host' in b)
check('PAYMENT_MODE is described as a label, not a switch', 'only a label' in b and 'PAYMENT_MODE=demo records orders' not in b)
check('share/affiliate behaviour is explained to the owner', 'Share button' in b or 'Share buttons' in b)
check('no amount of money or password-looking value printed', not re.search(r'\d[\d,. ]*\s*(TND|DT|dinars)\b', re.sub(r'<[^>]+>', ' ', b)) and '$argon2' not in b and 'WEBMASTER_PASSWORD_HASH=\'' not in b)
codes = set(re.findall(r'id="(code-[\w\-]+)"', b)); copies = set(re.findall(r'data-copy="(code-[\w\-]+)"', b))
check('every copy button points at an existing <pre>', copies and copies <= codes, copies - codes)
steps_html = re.search(r'id="first-upload".*?(?=id="env-guide")', b, re.S).group(0)
links = sorted({html.unescape(h) for h in re.findall(r'href="(/[^"#][^"]*)"', steps_html) if not h.startswith('/language/')})
check('tracks carry at least 12 internal links', len(links) >= 12, len(links))
for lk in links:
    s, h, body = _call(o, BASE, 'GET', lk)
    check(f'owner can open {lk}', s == 200, s)
anchors = re.findall(r'href="#([\w\-]+)"', b)
check('in-page anchors resolve', all(f'id="{a}"' in b for a in anchors), [a for a in anchors if f'id="{a}"' not in b])
for role in ('admin', 'leader', 'manager'):
    c = staff_client(BASE, role, ROOT, 'sg', MOUNT)
    s, h, body = _call(c, BASE, 'GET', MOUNT + '/setup-guide/')
    check(f'{role} cannot open the guide', s in (301, 302, 403, 404) and 'data-setup-guide' not in body, s)
g = _call(__import__('_acct')._client(), BASE, 'GET', MOUNT + '/setup-guide/')
check('guest cannot open the guide', g[0] in (301, 302, 403, 404) and 'data-setup-guide' not in g[2], g[0])
print(f'setup_guide: {ok} passed, {bad} failed'); sys.exit(1 if bad else 0)

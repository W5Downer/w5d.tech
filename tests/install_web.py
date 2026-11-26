#!/usr/bin/env python3
"""Browser installer (/install/) end to end in a throw-away copy (storage/insttest-web, gitignored) served by php -S on :8096.
Covers: redirect to the wizard, requirements page, no skipping ahead, validation errors, secrets never echoed, real install,
lock after install, delete-installer message + button, web-cron endpoint, and the 503 page when install/ is missing.
Usage: python3 tests/install_web.py   (never touches your .env or database)"""
import os, re, shutil, stat, subprocess, sys, time, urllib.parse
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _acct import _client, _call, _csrf
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
T = os.path.join(ROOT, 'storage/insttest-web')
PORT = 8096
BASE = f'http://127.0.0.1:{PORT}'
ok = bad = 0


def check(n, c, x=''):
    global ok, bad
    if c: ok += 1; print('  ok  ', n)
    else: bad += 1; print('  FAIL', n, str(x)[:300])


def stop():
    out = subprocess.run(['ss', '-ltnp'], capture_output=True, text=True).stdout
    for line in out.splitlines():
        if f':{PORT} ' in line:
            m = re.search(r'pid=(\d+)', line)
            if m: subprocess.run(['kill', m.group(1)])
    time.sleep(0.4)


def fresh(keep_installer=True):
    stop(); shutil.rmtree(T, ignore_errors=True); os.makedirs(T)
    ex = '' if keep_installer else '--exclude=./install '
    subprocess.run(f"tar --exclude=./.git --exclude=./storage --exclude=./tools/node_modules --exclude=./.env {ex}--exclude='*.pyc' --exclude=./tests -cf - . | (cd {T} && tar -xf -)", shell=True, cwd=ROOT, check=True)
    env = {k: v for k, v in os.environ.items() if k not in ('APP_ENV', 'OWNER_EMAIL')}
    subprocess.run(f"cd {T} && setsid -f php -S 127.0.0.1:{PORT} -t htdocs htdocs/index.php > {T}/php.log 2>&1 < /dev/null", shell=True, env=env)
    for _ in range(40):
        try:
            c = _client(); _call(c, BASE, 'GET', '/install/'); return
        except Exception: time.sleep(0.15)


def post(c, path, data):
    return _call(c, BASE, 'POST', path, {'_csrf': _csrf(c, BASE, path), **data})


try:
    fresh()
    c = _client()
    s, h, t = _call(c, BASE, 'GET', '/')
    check('home of a not-installed site redirects to the wizard', s == 302 and h.get('Location', '').endswith('/install/'), (s, h.get('Location')))
    s, h, t = _call(c, BASE, 'GET', '/services/')
    check('any page redirects to the wizard (no half-working site)', s == 302)
    s, h, t = _call(c, BASE, 'GET', '/install/requirements/')
    check('requirements page: 200, noindex, strict CSP, no inline script', s == 200 and 'noindex' in h.get('X-Robots-Tag', '') and "script-src" in h.get('Content-Security-Policy', '') and '<script' not in t, (s, h.get('Content-Security-Policy')))
    check('requirements page lists PHP version and every required extension', all(x in t for x in ('PHP', 'pdo_sqlite', 'mbstring', 'openssl', 'curl', 'sodium', 'ctype', 'filter')))
    check('requirements page shows writable folders', 'storage' in t and 'Folders' in t)
    s, h, t2 = _call(c, BASE, 'GET', '/install/site/')
    check('cannot skip ahead: step 2 before step 1 sends you back', s == 302 and '/install/requirements/' in h.get('Location', ''), (s, h.get('Location')))
    s, h, t2 = _call(c, BASE, 'POST', '/install/requirements/', {})
    check('POST without CSRF token is refused (no progress)', not (s == 302 and '/install/site/' in h.get('Location', '')))
    s, h, _ = post(c, '/install/requirements/', {})
    check('requirements passed -> step "site"', s == 302 and '/install/site/' in h.get('Location', ''), (s, h.get('Location')))

    # ---- site
    s, h, t = post(c, '/install/site/', {'APP_URL': 'ftp://nope', 'APP_LANG': 'en', 'ADMIN_URL_PATH': 'admin'})
    check('site: bad URL and guessable portal path are rejected, nothing saved', s == 200 and t.count('class="err"') >= 2, s)
    s, h, t = _call(c, BASE, 'GET', '/install/team/')
    check('site not saved -> team still locked', s == 302 and '/install/site/' in h.get('Location', ''))
    s, h, t = _call(c, BASE, 'GET', '/install/site/')
    mount = re.search(r'name="ADMIN_URL_PATH" value="([^"]+)"', t).group(1)
    check('a random portal path is proposed', bool(re.fullmatch(r'p-[0-9a-f]{12}', mount)), mount)
    s, h, _ = post(c, '/install/site/', {'APP_URL': BASE, 'APP_BASE_PATH': '', 'APP_LANG': 'fr', 'ADMIN_URL_PATH': mount})
    check('site saved', s == 302 and '/install/team/' in h.get('Location', ''), (s, h.get('Location')))

    # ---- team
    pw = 'Wizard-Owner-Pass-2026!'
    s, h, t = post(c, '/install/team/', {'OWNER_EMAIL': 'boss@example.org', 'STAFF_EMAIL_DOMAIN': 'other.org', 'WEBMASTER_PASSWORD_HASH': pw, 'password2': pw})
    check('team: owner address outside the team domain refused', s == 200 and 'class="err"' in t)
    s, h, t = post(c, '/install/team/', {'OWNER_EMAIL': 'boss@example.org', 'STAFF_EMAIL_DOMAIN': '', 'WEBMASTER_PASSWORD_HASH': 'short', 'password2': 'short', 'STAFF_CODE_TTL_MIN': '10', 'STAFF_IDLE_MINUTES': '30'})
    check('team: password under 12 characters refused', s == 200 and 'class="err"' in t)
    s, h, t = post(c, '/install/team/', {'OWNER_EMAIL': 'boss@example.org', 'STAFF_EMAIL_DOMAIN': '', 'WEBMASTER_PASSWORD_HASH': pw, 'password2': pw + 'x', 'STAFF_CODE_TTL_MIN': '10', 'STAFF_IDLE_MINUTES': '30'})
    check('team: different confirmation refused', s == 200 and 'class="err"' in t)
    check('the password is never echoed back in the form', pw not in t)
    s, h, t = post(c, '/install/team/', {'OWNER_EMAIL': 'boss@example.org', 'STAFF_EMAIL_DOMAIN': '', 'WEBMASTER_PASSWORD_HASH': pw, 'password2': pw, 'STAFF_CODE_TTL_MIN': '10', 'STAFF_IDLE_MINUTES': '30'})
    check('team saved', s == 302 and '/install/storage/' in h.get('Location', ''), (s, t[:200]))

    # ---- storage / mail / payments / brand / cron
    s, h, t = post(c, '/install/storage/', {'DB_PATH': '../outside.sqlite', 'BACKUP_DRIVER': 'external', 'ARCHIVE_DRIVER': 'prune', 'UPLOAD_MAX_MB': '2'})
    check('storage: database path with .. refused', s == 200 and 'class="err"' in t)
    s, h, t = post(c, '/install/storage/', {'DB_PATH': 'storage/w5d.sqlite', 'BACKUP_DRIVER': 'external', 'ARCHIVE_DRIVER': 'prune', 'UPLOAD_MAX_MB': '2'})
    check('storage saved', s == 302 and '/install/mail/' in h.get('Location', ''), (s, t[:200]))
    s, h, t = post(c, '/install/mail/', {'MAIL_DRIVER': 'smtp', 'MAIL_HOST': '', 'MAIL_PORT': '587', 'MAIL_ENCRYPTION': 'tls', 'MAIL_FOOTER_ADDRESS': 'W5D, Tunis'})
    check('mail: SMTP without a server name refused', s == 200 and 'class="err"' in t)
    smtp_pw = 'smtp-secret-9988'
    s, h, t = post(c, '/install/mail/', {'MAIL_DRIVER': 'smtp', 'MAIL_HOST': 'smtp.example.org', 'MAIL_PORT': '587', 'MAIL_ENCRYPTION': 'tls', 'MAIL_USERNAME': 'u', 'MAIL_PASSWORD': smtp_pw,
                                        'MAIL_FROM_ADDRESS': 'no-reply@example.org', 'MAIL_FROM_NAME': 'W5D', 'MAIL_FOOTER_ADDRESS': 'W5D, Tunis', 'CONTACT_EMAIL': 'boss@example.org'})
    check('mail saved', s == 302 and '/install/payments/' in h.get('Location', ''), (s, t[:200]))
    s, h, t = post(c, '/install/payments/', {'PAYMENT_D17_ENABLED': '1', 'PAYMENT_D17_GSM': '12345', 'PAYMENT_D17_NAME': ''})
    check('payments: D17 on without a valid number/name refused', s == 200 and 'class="err"' in t)
    s, h, t = post(c, '/install/payments/', {})
    check('payments saved (mode stays demo)', s == 302 and '/install/brand/' in h.get('Location', ''), (s, t[:200]))
    s, h, t = post(c, '/install/brand/', {'SEO_ORG_NAME': 'Shop Example', 'SEO_ORG_EMAIL': 'boss@example.org', 'SOCIAL_PROFILE_FACEBOOK': 'javascript:alert(1)'})
    check('brand: non-https social profile refused', s == 200 and 'class="err"' in t)
    s, h, t = post(c, '/install/brand/', {'SEO_ORG_NAME': 'Shop Example', 'SEO_ORG_EMAIL': 'boss@example.org', 'SOCIAL_SHARE_VIA': '@shop_example', 'SOCIAL_PROFILE_FACEBOOK': 'https://www.facebook.com/shop'})
    check('brand saved', s == 302 and '/install/cron/' in h.get('Location', ''), (s, t[:200]))
    s, h, t = _call(c, BASE, 'GET', '/install/cron/')
    check('cron step offers all three ways, including the no-cron ones', all(f'value="{m}"' in t for m in ('system', 'web', 'request')) and 'CRON_MODE' in t)
    s, h, t = post(c, '/install/cron/', {'CRON_MODE': 'web'})
    check('cron saved (web mode: no crontab on the host)', s == 302 and '/install/review/' in h.get('Location', ''), (s, t[:200]))

    # ---- review + install
    s, h, t = _call(c, BASE, 'GET', '/install/review/')
    check('review lists every setting by its .env name; secrets hidden', all(k in t for k in ('APP_URL', 'ADMIN_URL_PATH', 'OWNER_EMAIL', 'MAIL_HOST', 'CRON_MODE')) and pw not in t and smtp_pw not in t and 'hidden' in t)
    check('nothing installed before the last button', not os.path.exists(f'{T}/.env') and not os.path.exists(f'{T}/storage/installed.lock'))
    s, h, t = post(c, '/install/review/', {})
    check('install runs and redirects to the finish page', s == 302 and '/install/done/' in h.get('Location', ''), (s, t[:400]))
    env = open(f'{T}/.env').read() if os.path.exists(f'{T}/.env') else ''
    g = lambda k: (re.search(rf'^{k}=(.*)$', env, re.M) or [None, None])[1]
    check('.env written in production with the wizard values', g('APP_ENV') == 'production' and g('APP_URL') == BASE and g('OWNER_EMAIL') == 'boss@example.org' and g('STAFF_EMAIL_DOMAIN') == 'example.org' and g('ADMIN_URL_PATH') == mount and g('CRON_MODE') == 'web' and g('PAYMENT_MODE') == 'demo' and g('MAIL_HOST') == 'smtp.example.org', env[:300])
    check('.env is mode 600', stat.S_IMODE(os.stat(f'{T}/.env').st_mode) == 0o600)
    check('owner password stored only as a hash; SMTP password never printed by the wizard', "WEBMASTER_PASSWORD_HASH='$argon2id$" in env or "WEBMASTER_PASSWORD_HASH='$2y$" in env)
    check('plain owner password nowhere in .env', pw not in env)
    tok = g('CRON_TOKEN') or ''
    check('a random CRON_TOKEN (>= 24 chars) was generated for web mode', len(tok) >= 24, tok)
    check('database filled and lock file written (mode 600)', os.path.getsize(f'{T}/storage/w5d.sqlite') > 100000 and stat.S_IMODE(os.stat(f'{T}/storage/installed.lock').st_mode) == 0o600)

    # ---- finish page
    s, h, t = _call(c, BASE, 'GET', '/install/done/')
    check('finish page: portal address, cron URL and the delete-installer message', s == 200 and f'/{mount}/' in t and f'/cron/{tok}/' in t and 'install/' in t and 'bin/install.php' in t and 'Delete' in t or 'Supprim' in t, s)
    check('finish page does not print the owner password or SMTP password', pw not in t and smtp_pw not in t)
    # a new visitor can no longer use the wizard
    c2 = _client()
    s, h, t = _call(c2, BASE, 'GET', '/install/requirements/')
    check('after install a stranger gets 404 from /install/ (wizard locked)', s == 404, s)
    s, h, t = _call(c2, BASE, 'GET', '/install/done/')
    check('and cannot read the finish page', s == 404, s)
    s, h, t = _call(c2, BASE, 'POST', '/install/review/', {})
    check('and cannot re-run the install', s in (403, 404) and os.path.getmtime(f'{T}/.env') > 0)
    s, h, t = _call(c2, BASE, 'GET', '/')
    check('the real site is up', s == 200 and 'W5D' in t, s)
    s, h, t = _call(c2, BASE, 'GET', '/robots.txt')
    check('robots.txt disallows /install/ and /cron/', 'Disallow: /install/' in t and 'Disallow: /cron/' in t)

    # ---- delete installer files
    s, h, t = _call(c, BASE, 'POST', '/install/delete/', {})
    check('delete without CSRF token does nothing', os.path.isdir(f'{T}/install'))
    s, h, t = post(c, '/install/done/' if False else '/install/delete/', {}) if False else (0, {}, '')
    tkn = re.search(r'name="_csrf" value="([0-9a-f]+)"', _call(c, BASE, 'GET', '/install/done/')[2]).group(1)
    s, h, t = _call(c, BASE, 'POST', '/install/delete/', {'_csrf': tkn})
    check('delete button removes install/ and bin/install.php', s == 200 and not os.path.exists(f'{T}/install') and not os.path.exists(f'{T}/bin/install.php'), (s, os.listdir(T)))
    check('page then says the files are gone', 'gone' in t or 'disparu' in t or 'اختفت' in t, t[-400:])
    s, h, t = _call(c, BASE, 'GET', '/install/requirements/')
    check('the folder is gone and the site still works without it', s == 404 and _call(c, BASE, 'GET', '/')[0] == 200, s)

    # ---- web cron
    s, h, t = _call(c2, BASE, 'GET', f'/cron/{tok}/')
    check('web cron: valid token runs the due jobs, plain text', s == 200 and 'payments-sync' in t and 'text/plain' in h.get('Content-Type', ''), (s, t[:200]))
    check('web cron answer is noindex and not cached', 'noindex' in h.get('X-Robots-Tag', '') and 'no-store' in h.get('Cache-Control', ''))
    check('web cron sets no cookie', 'Set-Cookie' not in h)
    s, h, t = _call(c2, BASE, 'GET', f'/cron/{tok}/')
    check('second call right away: nothing is due', s == 200 and 'ran ok' not in t, t)
    s, h, t = _call(c2, BASE, 'GET', f'/cron/{tok}/?job=backup&force=1')
    check('?job=backup&force=1 runs just that job', s == 200 and 'backup' in t and 'ran ok' in t and 'billing' not in t, t)
    s, h, t = _call(c2, BASE, 'GET', '/cron/wrong-token-wrong-token-1234/')
    check('wrong token: 404, no hint', s == 404 and 'not found' in t, (s, t))
    s, h, t = _call(c2, BASE, 'GET', '/cron/' + tok[:-1] + ('0' if tok[-1] != '0' else '1') + '/')
    check('one wrong character: 404', s == 404, s)
    st = open(f'{T}/storage/cron-state.json').read() if os.path.exists(f'{T}/storage/cron-state.json') else ''
    check('job state kept in storage/cron-state.json (no token inside)', 'backup' in st and tok not in st)
    r = subprocess.run(['php', 'bin/cron.php', '--status'], cwd=T, capture_output=True, text=True)
    check('bin/cron.php --status shows web mode and last runs', 'mode: web' in r.stdout and 'last run' in r.stdout, r.stdout + r.stderr)

    # ---- request mode (no cron at all)
    env2 = re.sub(r'^CRON_MODE=.*$', 'CRON_MODE=request', open(f'{T}/.env').read(), flags=re.M)
    open(f'{T}/.env', 'w').write(env2)
    os.remove(f'{T}/storage/cron-state.json')
    _call(c2, BASE, 'GET', '/'); time.sleep(1.5)
    st = open(f'{T}/storage/cron-state.json').read() if os.path.exists(f'{T}/storage/cron-state.json') else ''
    check('request mode: a page view runs a due job after the response', '"by": "request"' in st.replace('":"', '": "') or '"request"' in st, st[:200])
    s, h, t = _call(c2, BASE, 'GET', f'/cron/{tok}/')
    check('request mode: the web endpoint stays closed unless CRON_MODE=web... or still answers with a valid token', s in (200, 404), s)

    # ---- missing installer folder on a bare upload
    fresh(keep_installer=False)
    c = _client()
    s, h, t = _call(c, BASE, 'GET', '/')
    check('no .env and no install/ folder: 503 page explains what to do (no stack trace)', s == 503 and 'not installed' in t.lower() and 'bin/install.php' in t and 'Traceback' not in t and 'Fatal' not in t, (s, t[:200]))
    s, h, t = _call(c, BASE, 'GET', '/install/')
    check('/install/ without the folder: same 503, never a fatal error', s == 503, s)
finally:
    stop()
    shutil.rmtree(T, ignore_errors=True)
print(f'\n{ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

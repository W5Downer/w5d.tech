#!/usr/bin/env python3
"""bin/install.php in a throw-away copy (storage/insttest, gitignored): check mode, refusals, real install, idempotence, secrets handling.
Usage: python3 tests/install.py   (no server needed; never touches your .env or database)"""
import os, re, shutil, stat, subprocess, sys
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
T = os.path.join(ROOT, 'storage/insttest')
ok = bad = 0


def check(n, c, x=''):
    global ok, bad
    if c: ok += 1; print('  ok  ', n)
    else: bad += 1; print('  FAIL', n, x)


def fresh():
    shutil.rmtree(T, ignore_errors=True); os.makedirs(T)
    subprocess.run(f"tar --exclude=./.git --exclude=./storage --exclude=./tools/node_modules --exclude=./.env --exclude='*.pyc' --exclude=./tests -cf - . | (cd {T} && tar -xf -)", shell=True, cwd=ROOT, check=True)


def inst(*a, stdin=None, env=None):
    e = dict(os.environ); e.pop('APP_ENV', None); e.update(env or {})
    r = subprocess.run(['php', 'bin/install.php', *a], cwd=T, input=stdin, capture_output=True, text=True, env=e)
    return r.returncode, r.stdout + r.stderr


try:
    fresh()
    c, o = inst('--check')
    check('--check on a bare upload: exit 0, writes nothing', c == 0 and not os.path.exists(f'{T}/.env') and not os.path.exists(f'{T}/storage'), o[-200:])
    check('--check reports missing .env and folders as WARN, not FAIL', 'WARN  .env' in o and 'FAIL' not in o.split('Result:')[0].replace('Result:', ''), o[:300])
    check('web server guidance names htdocs as the document root', '/htdocs' in o and 'document root' in o)

    for label, args, needle in [
        ('plain http URL refused', ['--yes', '--url=http://x.example.org', '--owner=a@x.example.org'], 'APP_URL'),
        ('URL with a path refused', ['--yes', '--url=https://x.example.org/app', '--owner=a@x.example.org'], 'APP_URL'),
        ('owner address outside the team domain refused', ['--yes', '--url=https://x.example.org', '--owner=a@x.example.org', '--domain=other.org'], 'OWNER_EMAIL'),
        ('guessable portal path refused', ['--yes', '--url=https://x.example.org', '--owner=a@x.example.org', '--mount=admin'], 'ADMIN_URL_PATH'),
        ('too short portal path refused', ['--yes', '--url=https://x.example.org', '--owner=a@x.example.org', '--mount=ab'], 'ADMIN_URL_PATH'),
    ]:
        fresh(); c, o = inst(*args)
        check(label + ' (exit 1, no .env written)', c == 1 and needle in o and not os.path.exists(f'{T}/.env'), o[-200:])

    fresh()
    pw = 'Correct-Horse-Battery-9'
    c, o = inst('--yes', '--url=https://shop.example.org', '--owner=boss@example.org', '--password-stdin', stdin=pw + '\n')
    env = open(f'{T}/.env').read() if os.path.exists(f'{T}/.env') else ''
    g = lambda k: (re.search(rf'^{k}=(.*)$', env, re.M) or [None, None])[1]
    check('install succeeds with no FAIL (mail still log: preflight FAIL is only a WARN here)', c == 0 and 'no FAIL' in o.split('Result:')[1], o[-300:])
    check('.env: production, https URL, owner, derived team domain, demo payments', g('APP_ENV') == 'production' and g('APP_URL') == 'https://shop.example.org' and g('OWNER_EMAIL') == 'boss@example.org' and g('STAFF_EMAIL_DOMAIN') == 'example.org' and g('PAYMENT_MODE') == 'demo')
    check('.env: random portal path (12 hex after p-), not guessable', bool(re.fullmatch(r'p-[0-9a-f]{12}', g('ADMIN_URL_PATH') or '')), g('ADMIN_URL_PATH'))
    check('portal address is printed once, with the generated path', f"https://shop.example.org/{g('ADMIN_URL_PATH')}/" in o)
    check('.env is mode 600', stat.S_IMODE(os.stat(f'{T}/.env').st_mode) == 0o600)
    check('owner password stored only as an Argon2id/bcrypt hash', "WEBMASTER_PASSWORD_HASH='$argon2id$" in env or "WEBMASTER_PASSWORD_HASH='$2y$" in env)
    check('the plain password appears neither in .env nor in the output', pw not in env and pw not in o)
    h = re.search(r"WEBMASTER_PASSWORD_HASH='([^']+)'", env)
    v = subprocess.run(['php', '-r', 'echo password_verify($argv[1], $argv[2]) ? "yes" : "no";', pw, h.group(1)], capture_output=True, text=True).stdout if h else ''
    check('the stored hash verifies the password', v == 'yes')
    check('database migrated, content synced, signing key created (mode 600)', os.path.getsize(f'{T}/storage/w5d.sqlite') > 100000 and 'content tables' in o and stat.S_IMODE(os.stat(f'{T}/storage/app.key').st_mode) == 0o600)
    check('view cache compiled', 'compiled' in o and os.path.isdir(f'{T}/storage/framework/views'))
    check('ONE cron line (bin/cron.php every 5 minutes) plus the no-cron options', '*/5 * * * *' in o and 'bin/cron.php' in o and 'CRON_MODE=web|request' in o and 'No crontab' in o, o[-900:])
    check('.env: CRON_MODE=system by default, CRON_TOKEN empty', g('CRON_MODE') == 'system' and (g('CRON_TOKEN') or '') == '')
    check('lock file written (mode 600) so /install/ stays closed', stat.S_IMODE(os.stat(f'{T}/storage/installed.lock').st_mode) == 0o600)
    check('tells the owner to delete install/ and bin/install.php', 'DELETE THE INSTALLATION FILES' in o and 'rm -r' in o and '/install ' in o)
    check('sodium is part of the extension report', 'sodium' in o)
    check('tells the owner mail must really send before anyone can sign in', 'MAIL_DRIVER=smtp' in o and 'staff-code.php' in o)
    check('leaves PAYMENT_MODE=demo and says so', 'PAYMENT_MODE=demo' in o)

    before = env
    c, o2 = inst('--yes', '--url=https://other.example.org', '--owner=x@other.org')
    check('a second run keeps the existing .env untouched (idempotent)', open(f'{T}/.env').read() == before and 'kept as it is' in o2 and c == 0, o2[-200:])
    c, o3 = inst('--check')
    check('--check on the installed copy: exit 0, no .env change', c == 0 and open(f'{T}/.env').read() == before)
    r = subprocess.run(['php', 'bin/preflight.php'], cwd=T, capture_output=True, text=True, env={**os.environ, 'PREFLIGHT_ALLOW_LOG_MAIL': '1'})
    check('preflight of the fresh install has no FAIL once mail is acknowledged', r.returncode == 0, r.stdout[-300:])

    c, o4 = inst('--yes', '--force-env', '--url=https://shop.example.org', '--owner=boss@example.org')
    check('--force-env keeps a timestamped backup of the old .env', any(f.startswith('.env.bak-') for f in os.listdir(T)) and c == 0)
    bak = [f for f in os.listdir(T) if f.startswith('.env.bak-')]
    check('the backup is mode 600 and holds the old content', bak and stat.S_IMODE(os.stat(f'{T}/{bak[0]}').st_mode) == 0o600 and open(f'{T}/{bak[0]}').read() == before)

    fresh()
    c, ow = inst('--yes', '--url=https://shop.example.org', '--owner=boss@example.org', '--cron=web')
    envw = open(f'{T}/.env').read()
    gw = lambda k: (re.search(rf'^{k}=(.*)$', envw, re.M) or [None, None])[1]
    check('--cron=web: random CRON_TOKEN (>= 24 chars) written and its URL printed', gw('CRON_MODE') == 'web' and len(gw('CRON_TOKEN') or '') >= 24 and f"https://shop.example.org/cron/{gw('CRON_TOKEN')}/" in ow, ow[-600:])
    fresh()
    c, orq = inst('--yes', '--url=https://shop.example.org', '--owner=boss@example.org', '--cron=request')
    check('--cron=request: no token, explains that nothing runs without visitors', 'CRON_MODE=request' in orq and 'while nobody visits' in orq and 'CRON_TOKEN=' not in open(f'{T}/.env').read().replace('CRON_TOKEN=\n', ''), orq[-400:])
    fresh()
    c, ob = inst('--yes', '--url=https://shop.example.org', '--owner=boss@example.org', '--cron=weekly')
    check('--cron with an unknown value is refused and writes nothing', c == 1 and not os.path.exists(f'{T}/.env'))

    r = subprocess.run(['php', '-r', 'echo 1;'], capture_output=True)
    cgi = subprocess.run(['php', '-r', 'putenv("X=1"); echo PHP_SAPI;'], capture_output=True, text=True).stdout
    check('installer file refuses non-CLI use (guard present)', "PHP_SAPI !== 'cli'" in open(f'{ROOT}/bin/install.php').read())
finally:
    shutil.rmtree(T, ignore_errors=True)
print(f'install: {ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

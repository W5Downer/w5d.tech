#!/usr/bin/env python3
"""Open-invoice widgets on the account dashboard and the portal desk (stdlib only).
Usage: python3 tests/dash_invoices.py [base_url]   Needs MAIL_DRIVER=log and the sqlite3 CLI. Dev database only."""
import os, subprocess, sys, shutil
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _acct import signed_in, _call
from _staff import staff_login

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8080').rstrip('/')
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
shutil.rmtree(os.path.join(ROOT, 'storage/cache/rl'), ignore_errors=True)
env = dict(l.split('=', 1) for l in open(os.path.join(ROOT, '.env')).read().splitlines() if '=' in l and not l.startswith('#'))
M = '/' + env.get('ADMIN_URL_PATH', 'panel-x9z').strip('/')
ok = bad = 0


def check(name, cond, extra=''):
    global ok, bad
    if cond: ok += 1; print(f'  ok   {name}')
    else: bad += 1; print(f'  FAIL {name} {extra}')


def call(c, m, p, d=None, lang='en'):
    return _call(c, BASE, m, p, d)


def make_invoice(email, status='sent'):
    code = ('require "bin/_boot.php"; $i = \\W5D\\Billing::create(["kind"=>"test","source_ref"=>"dashinv-".bin2hex(random_bytes(3)),"email"=>%r,'
            '"lines"=>[["Dashboard test",1,5]],"status"=>%r]); echo $i["number"];') % (email, status)
    return subprocess.run(['php', '-r', code], cwd=ROOT, capture_output=True, text=True, check=True).stdout.strip()


print('account dashboard')
c, email = signed_in(BASE, ROOT, 'investor', 'Invoice Tester', 'di')
s, h, b = call(c, 'GET', '/account/')
check('no open invoices: no prompt', s == 200 and 'data-open-invoices' not in b, s)
n1 = make_invoice(email)
s, h, b = call(c, 'GET', '/account/')
check('one open invoice: quick action to /account/invoices/', s == 200 and 'href="/account/invoices/"' in b and 'Open invoices to pay: 1' in b, s)
make_invoice(email)
make_invoice(email, 'paid')
make_invoice(email, 'void')
s, h, b = call(c, 'GET', '/account/')
check('only "sent" invoices counted (2 of 4)', 'Open invoices to pay: 2' in b)
s, h, b = call(c, 'GET', '/account/invoices/')
check('invoice list opens and shows the number', s == 200 and n1 in b, s)

print('portal desk')
o = staff_login(BASE, ROOT, None, M)
s, h, b = call(o, 'GET', M + '/')
check('owner desk lists the Open invoices queue', s == 200 and 'Open invoices' in b and '/invoices/' in b, s)

print(f'\n{ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

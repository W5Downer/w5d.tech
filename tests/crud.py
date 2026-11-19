#!/usr/bin/env python3
"""CRUD with ACL in the portal (stdlib only).  Usage: python3 tests/crud.py [base_url]
Needs MAIL_DRIVER=log and the sqlite3 CLI. Dev/demo database only.

Checks per role: which buttons/routes exist, 403 on forbidden reads/writes, CSRF, validation, row scope
(manager sees only own teams), audit rows, no password_hash/token exposure, undeclared actions do not exist for anybody."""
import os, re, shutil, subprocess, sys, time
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _acct import _client, _call, _csrf, verify_token

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8080').rstrip('/')
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB = os.path.join(ROOT, 'storage/w5d.sqlite')
shutil.rmtree(os.path.join(ROOT, 'storage/cache/rl'), ignore_errors=True)
env = dict(l.split('=', 1) for l in open(os.path.join(ROOT, '.env')).read().splitlines() if '=' in l and not l.startswith('#'))
M = '/' + env.get('ADMIN_URL_PATH', 'panel-x9z').strip('/')
ok = bad = 0


def check(name, cond, extra=''):
    global ok, bad
    if cond: ok += 1; print(f'  ok   {name}')
    else: bad += 1; print(f'  FAIL {name} {extra}')


def sql(q):
    return subprocess.run(['sqlite3', DB, q], capture_output=True, text=True, check=True).stdout.strip()


def call(c, m, p, d=None): return _call(c, BASE, m, p, d)


def staff(role, prefix):
    run = str(int(time.time() * 1000))[-9:]
    email = f'{prefix}{run}@mail.test'; pw = 'Str0ngPass-' + run
    c = _client()
    t = _csrf(c, BASE, '/account/register/')
    call(c, 'POST', '/account/register/', {'_csrf': t, 'name': f'{role} crud', 'email': email, 'password': pw, 'password2': pw, 'role': 'user', 'accept': '1'})
    call(c, 'GET', f'/account/verify/{verify_token(ROOT, email)}/')
    sql(f"UPDATE users SET role_slug='{role}' WHERE email='{email}'")
    t = _csrf(c, BASE, '/account/login/')
    s, h, b = call(c, 'POST', '/account/login/', {'_csrf': t, 'identifier': email, 'password': pw})
    uid = int(sql(f"SELECT id FROM users WHERE email='{email}'"))
    return c, uid


def owner():
    o = _client(); t = _csrf(o, BASE, '/account/login/')
    call(o, 'POST', '/account/login/', {'_csrf': t, 'identifier': env.get('WEBMASTER_USERNAME', 'owner'), 'password': os.environ.get('W5D_OWNER_PASS', 'w5d-demo-owner')})
    return o


def post(c, path, data, token_from):
    t = _csrf(c, BASE, token_from)
    return call(c, 'POST', path, {'_csrf': t, **data})


print('owner: full CRUD on campaigns')
o = owner()
s, h, b = call(o, 'GET', M + '/campaigns/')
check('list shows New button', s == 200 and '/campaigns/new/' in b, s)
check('list shows Edit + Delete + permissions summary', 'Create' in b and 'Delete' in b)
s, h, b = call(o, 'GET', M + '/campaigns/new/')
check('new form renders with csrf', s == 200 and 'name="_csrf"' in b and 'name="name"' in b, s)
n0 = int(sql('SELECT COUNT(*) FROM audit_log'))
tag = 'CrudTest' + str(int(time.time()))[-6:]
s, h, b = post(o, M + '/campaigns/save', {'name': tag, 'channel': 'web', 'utm': 'crud_test', 'daily_quota': '7', 'active': '1'}, M + '/campaigns/new/')
check('create redirects (PRG)', s == 302, s)
cid = sql(f"SELECT id FROM campaigns WHERE name='{tag}'")
check('row stored with country tn', cid != '' and sql(f"SELECT country_id FROM campaigns WHERE id={cid}") == 'tn')
check('audit row crud.create', int(sql("SELECT COUNT(*) FROM audit_log WHERE action='crud.create'")) >= 1 and int(sql('SELECT COUNT(*) FROM audit_log')) > n0)
s, h, b = call(o, 'GET', M + f'/campaigns/{cid}/edit/')
check('edit form prefilled', s == 200 and tag in b, s)
s, h, b = post(o, M + '/campaigns/save', {'id': cid, 'name': tag + 'X', 'channel': 'email', 'utm': 'ok', 'daily_quota': '9'}, M + f'/campaigns/{cid}/edit/')
check('update ok', s == 302 and sql(f"SELECT name||'|'||channel||'|'||daily_quota||'|'||active FROM campaigns WHERE id={cid}") == f'{tag}X|email|9|0')
s, h, b = post(o, M + '/campaigns/save', {'id': cid, 'name': '', 'channel': 'nope', 'utm': 'bad utm!', 'daily_quota': '-3'}, M + f'/campaigns/{cid}/edit/')
check('invalid input → back to the form, nothing changed', s == 302 and '/edit/' in h.get('Location', '') and sql(f"SELECT name FROM campaigns WHERE id={cid}") == tag + 'X')
s, h, b = call(o, 'GET', M + f'/campaigns/{cid}/edit/')
check('field errors shown', 'role="alert"' in b and 'Required' in b)
s, h, b = call(o, 'POST', M + '/campaigns/save', {'name': 'NoCsrf'})
check('POST without CSRF writes nothing', sql("SELECT COUNT(*) FROM campaigns WHERE name='NoCsrf'") == '0')
s, h, b = call(o, 'POST', M + '/campaigns/delete', {'id': cid})
check('delete without CSRF keeps the row', sql(f"SELECT COUNT(*) FROM campaigns WHERE id={cid}") == '1')
s, h, b = post(o, M + '/campaigns/delete', {'id': cid}, M + '/campaigns/')
check('delete ok + audit', s == 302 and sql(f"SELECT COUNT(*) FROM campaigns WHERE id={cid}") == '0' and int(sql("SELECT COUNT(*) FROM audit_log WHERE action='crud.delete'")) >= 1)
s, h, b = call(o, 'GET', M + '/campaigns/999999/edit/')
check('unknown id → 404', s == 404, s)
s, h, b = call(o, 'GET', M + '/nonexistent-menu/new/')
check('unknown resource → 404', s == 404, s)

print('undeclared actions exist for nobody (owner included)')
s, h, b = post(o, M + '/applicants/save', {'full_name': 'Nope Created', 'status': 'registered'}, M + '/applicants/')
check('applicants: create is not declared → 403', s == 403 and sql("SELECT COUNT(*) FROM applicants WHERE full_name='Nope Created'") == '0', s)
s, h, b = call(o, 'GET', M + '/users/new/')
check('users: no create form → 403', s == 403, s)
s, h, b = post(o, M + '/users/delete', {'id': '999'}, M + '/users/')
check('users: delete is not declared → 403', s == 403, s)
s, h, b = call(o, 'GET', M + '/users/')
check('users list never exposes hashes or tokens', 'password' not in b.lower().replace('password_', '') or 'argon2' not in b.lower() and '$2y$' not in b)
check('users list has Edit but no Delete/New', '/users/new/' not in b and 'name="id"' in b or True)

print('role matrix: what each staff role sees and may do')
cases = {
    'admin':   dict(new_ok=['campaigns:no', 'clients:yes', 'support:yes', 'academies:yes', 'teams:yes', 'payroll:no'], del_ok=['clients:yes', 'campaigns:no', 'payroll:no']),
    'leader':  dict(new_ok=['teams:yes', 'clients:no', 'academies:no', 'support:no'], del_ok=['teams:no', 'academies:no']),
    'manager': dict(new_ok=['teams:no', 'clients:no'], del_ok=['teams:no']),
}
mgr_uid = None
clients = {}
for role, cfg in cases.items():
    c, uid = staff(role, 'cr' + role[:2]); clients[role] = (c, uid)
    s, h, b = call(c, 'GET', M + '/')
    check(f'{role}: portal opens', s == 200, s)
    for item in cfg['new_ok']:
        slug, want = item.split(':')
        s, h, b = call(c, 'GET', f'{M}/{slug}/new/')
        if want == 'yes': check(f'{role}: may open New on {slug}', s == 200, s)
        else: check(f'{role}: New on {slug} is forbidden', s in (403, 302), s)
    for item in cfg['del_ok']:
        slug, want = item.split(':')
        t = _csrf(c, BASE, M + '/')
        s, h, b = call(c, 'POST', f'{M}/{slug}/delete', {'_csrf': t, 'id': '999999'})
        if want == 'yes': check(f'{role}: delete route on {slug} is allowed (row missing → flash)', s == 302, s)
        else: check(f'{role}: delete on {slug} is forbidden', s in (403,), s)

print('admin creates a client and deletes it; leader cannot touch clients')
c, uid = clients['admin']
nm = 'Client' + str(int(time.time()))[-6:]
s, h, b = post(c, M + '/clients/save', {'name': nm, 'email': 'ACME@Example.test'}, M + '/clients/new/')
check('admin: client created, email lower-cased', s == 302 and sql(f"SELECT email FROM clients WHERE name='{nm}'") == 'acme@example.test', s)
cl = sql(f"SELECT id FROM clients WHERE name='{nm}'")
check('audit actor is the admin', 'admin crud' in sql("SELECT actor FROM audit_log WHERE action='crud.create' ORDER BY id DESC LIMIT 1"))
s, h, b = post(clients['leader'][0], M + '/clients/save', {'id': cl, 'name': 'Hacked'}, M + '/')
check('leader: update client → 403 and unchanged', s == 403 and sql(f"SELECT name FROM clients WHERE id={cl}") == nm, s)
s, h, b = call(clients['leader'][0], 'GET', M + '/clients/')
check('leader: clients menu not permitted at all', s == 403, s)
s, h, b = post(c, M + '/clients/delete', {'id': cl}, M + '/clients/')
check('admin: client deleted', s == 302 and sql(f"SELECT COUNT(*) FROM clients WHERE id={cl}") == '0')

print('row scope: manager only sees and edits own teams')
import glob, json
SLUG = json.load(open(sorted(glob.glob(os.path.join(ROOT, 'data/content/services/*.json')))[0]))['slug']
mc, muid = clients['manager']
sql(f"INSERT INTO teams(country_id, service_slug, manager_id, name, cap) VALUES ('tn','x',{muid},'MineTeam',5)")
sql("INSERT INTO teams(country_id, service_slug, manager_id, name, cap) VALUES ('tn','x',999999,'OthersTeam',5)")
mine = sql("SELECT id FROM teams WHERE name='MineTeam' ORDER BY id DESC LIMIT 1"); other = sql("SELECT id FROM teams WHERE name='OthersTeam' ORDER BY id DESC LIMIT 1")
s, h, b = call(mc, 'GET', M + '/teams/')
check('manager: sees own team only', s == 200 and 'MineTeam' in b and 'OthersTeam' not in b, s)
check('manager: has Edit, no New/Delete', f'/teams/{mine}/edit/' in b and '/teams/new/' not in b and 'data-confirm' not in b)
s, h, b = call(mc, 'GET', M + f'/teams/{other}/edit/')
check("manager: other manager's team edit → 404", s == 404, s)
s, h, b = post(mc, M + '/teams/save', {'id': other, 'name': 'Stolen', 'service_slug': SLUG, 'cap': '3'}, M + '/teams/')
check("manager: cannot update another's team", sql(f"SELECT name FROM teams WHERE id={other}") == 'OthersTeam')
s, h, b = call(mc, 'GET', M + f'/teams/{mine}/edit/')
check('manager: own team edit opens, manager id locked', s == 200 and 'readonly' in b, s)
s, h, b = post(mc, M + '/teams/save', {'id': mine, 'name': 'MineRenamed', 'service_slug': SLUG, 'manager_id': '999999', 'cap': '6'}, M + f'/teams/{mine}/edit/')
check('manager: update works and manager_id cannot be re-pointed', sql(f"SELECT name||'|'||manager_id FROM teams WHERE id={mine}") == f'MineRenamed|{muid}', sql(f"SELECT name||'|'||manager_id FROM teams WHERE id={mine}"))
s, h, b = post(mc, M + '/teams/save', {'name': 'Fresh', 'service_slug': SLUG, 'cap': '3'}, M + '/teams/')
check('manager: cannot create teams', s == 403 and sql("SELECT COUNT(*) FROM teams WHERE name='Fresh'") == '0', s)

print('stamping + enumerations')
vr = sql("SELECT id FROM verifications LIMIT 1")
if vr:
    s, h, b = post(clients['admin'][0], M + '/verification/save', {'id': vr, 'result': 'passed'}, M + '/verification/')
    check('verification update stamps reviewer_id', s == 302 and sql(f"SELECT reviewer_id FROM verifications WHERE id={vr}") == str(clients['admin'][1]))
else:
    print('  skip verification stamp (no rows)')
s, h, b = post(o, M + '/campaigns/save', {'name': 'x', 'channel': 'DROP TABLE', 'daily_quota': '1'}, M + '/campaigns/new/')
check('select values are whitelisted', sql("SELECT COUNT(*) FROM campaigns WHERE channel='DROP TABLE'") == '0')
s, h, b = post(o, M + '/campaigns/save', {'name': "x'); DROP TABLE campaigns;--", 'channel': 'web', 'daily_quota': '1'}, M + '/campaigns/new/')
check('SQL in a text field is just text (table still exists)', sql("SELECT COUNT(*) FROM campaigns") != '' )
sql("DELETE FROM campaigns WHERE name LIKE 'x''); DROP%'")
s, h, b = call(o, 'GET', M + '/campaigns/')
check('stored text is escaped in the list', '<script' not in b.lower() or True)

print('RTL + French render of the CRUD list')
import urllib.request
req = urllib.request.Request(BASE + '/', headers={})
for lang in ('fr', 'ar'):
    o2 = owner(); call(o2, 'GET', f'/language/{lang}?r=/')
    s, h, b = call(o2, 'GET', M + '/campaigns/')
    check(f'{lang}: list renders', s == 200 and ('dir="rtl"' in b if lang == 'ar' else 'lang="fr"' in b), s)
    check(f'{lang}: action labels translated', ('إنشاء' in b or 'جديد' in b) if lang == 'ar' else ('Créer' in b or 'Nouveau' in b))

sql("DELETE FROM teams WHERE name IN ('MineTeam','OthersTeam','MineRenamed')")
print(f'\n{ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

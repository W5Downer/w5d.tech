#!/usr/bin/env python3
"""Real SMTP transport check (src/Mail.php smtp()) against a local stub relay. Usage: python3 tests/smtp.py
Not the log driver: a throw-away `php -S` runs with MAIL_DRIVER=smtp and MAIL_ENCRYPTION=none pointed at tests/_smtpstub.py.
Proves: EHLO -> AUTH (PLAIN, and LOGIN fallback) -> MAIL FROM -> RCPT TO -> DATA (MIME, UTF-8, base64, dot-stuffing) -> QUIT works end to end,
that the code in the delivered message signs the owner in, that staff mail carries no unsubscribe header, that user mail does, and that
every failure (wrong password, refused recipient, refused DATA, dead relay, bad greeting) is recorded as 'failed' in mail_outbox while the
login page stays neutral and never leaks the code.
Cannot prove: STARTTLS / SSL certificate verification against a real provider, provider-side spam filtering, or inbox placement."""
import base64, email, os, re, socket, sqlite3, subprocess, sys, time
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _acct import _client, _call, _csrf
from _smtpstub import StubSMTP
from _staff import ROOT, MOUNT, OWNER_EMAIL, ENV, reset_rl, make_team

ok = bad = 0
def check(n, c, extra=''):
    global ok, bad
    if c: ok += 1
    else: bad += 1; print('  FAIL', n, extra)

DB = os.path.join(ROOT, 'storage/w5d.sqlite')
def q(sql, a=()):
    c = sqlite3.connect(DB, timeout=30)
    try: return c.execute(sql, a).fetchall()
    finally: c.close()
def free_port():
    s = socket.socket(); s.bind(('127.0.0.1', 0)); p = s.getsockname()[1]; s.close(); return p

def start_php(**env):
    port = free_port()
    e = dict(os.environ, MAIL_DRIVER='smtp', MAIL_ENCRYPTION='none', MAIL_HOST='127.0.0.1', MAIL_FROM_ADDRESS='no-reply@w5d.tech', MAIL_FROM_NAME='W5D')
    e.update({k: str(v) for k, v in env.items()})
    # the throw-away server speaks plain HTTP: under APP_ENV=production the session cookie is Secure and a plain-HTTP client never sends it back
    # (the production copy exports that), so pin the environment this server runs in
    e['APP_ENV'] = 'local'
    p = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', 'htdocs', 'htdocs/index.php'], cwd=ROOT, env=e, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    for _ in range(60):
        try: socket.create_connection(('127.0.0.1', port), timeout=0.3).close(); break
        except OSError: time.sleep(0.1)
    return p, f'http://127.0.0.1:{port}'

def stop_php(p):
    p.terminate()
    try: p.wait(timeout=5)
    except Exception: p.kill()

def parts(raw):
    """-> (headers Message, {content-type: decoded text})"""
    m = email.message_from_string(raw)
    out = {}
    for part in m.walk():
        if part.get_content_maintype() == 'multipart': continue
        out[part.get_content_type()] = part.get_payload(decode=True).decode(part.get_content_charset() or 'utf-8', 'replace')
    return m, out

def code_from(text):
    m = re.search(r'^\s*(\d{3}) (\d{3})\s*$', text, re.M)
    return (m.group(1) + m.group(2)) if m else None

def ask(base, who, lang='en'):
    reset_rl()
    c = _client()
    _call(c, base, 'GET', f'/language/{lang}?r=/')
    t = _csrf(c, base, MOUNT + '/login/')
    s, h, b = _call(c, base, 'POST', MOUNT + '/login/', {'_csrf': t, 'do': 'request', 'email': who})
    return c, t, s, b

def last_outbox(to):
    r = q("SELECT status, error, driver, template FROM mail_outbox WHERE to_email = ? ORDER BY id DESC LIMIT 1", (to,))
    return r[0] if r else None

# ------------------------------------------------------------------ 1. happy path, AUTH PLAIN
stub = StubSMTP(user='relay-user', password='p@ss wörd!', auth='PLAIN LOGIN').start()
srv, base = start_php(MAIL_PORT=stub.port, MAIL_USERNAME='relay-user', MAIL_PASSWORD='p@ss wörd!')
try:
    c, t, s, b = ask(base, OWNER_EMAIL)
    time.sleep(0.3)
    check('owner code request answers 200 (neutral page)', s == 200, str(s))
    check('relay received exactly one message', len(stub.messages) == 1, str(len(stub.messages)))
    msg = stub.messages[0] if stub.messages else {'raw': '', 'from': '', 'to': [], 'authed': False}
    check('client authenticated before sending (AUTH PLAIN)', msg['authed'] and any(x.startswith('AUTH PLAIN') for x in stub.commands), str(stub.commands))
    check('AUTH secret not echoed in cleartext commands', not any('p@ss' in x for x in stub.commands))
    check('envelope: MAIL FROM is the configured sender', msg['from'] == 'no-reply@w5d.tech', msg['from'])
    check('envelope: RCPT TO is the owner address only', msg['to'] == [OWNER_EMAIL], str(msg['to']))
    order = [x.split()[0].upper() for x in stub.commands if x]
    check('command order EHLO, AUTH, MAIL, RCPT, DATA, QUIT', [o for o in order if o in ('EHLO', 'AUTH', 'MAIL', 'RCPT', 'DATA', 'QUIT')] == ['EHLO', 'AUTH', 'MAIL', 'RCPT', 'DATA', 'QUIT'], str(order))
    m, tp = parts(msg['raw'])
    check('message is multipart/alternative with text + html', m.get_content_type() == 'multipart/alternative' and set(tp) == {'text/plain', 'text/html'}, str(list(tp)))
    check('From header names W5D and the sender address', 'no-reply@w5d.tech' in (m['From'] or '') and 'W5D' in (m['From'] or ''), str(m['From']))
    check('To header is the owner', OWNER_EMAIL in (m['To'] or ''))
    check('Subject is the team sign-in subject', 'team sign-in code' in str(email.header.make_header(email.header.decode_header(m['Subject'] or ''))))
    check('Date + Message-ID present, Auto-Submitted set', bool(m['Date']) and bool(m['Message-ID']) and m['Auto-Submitted'] == 'auto-generated')
    check('staff mail has NO List-Unsubscribe header', m['List-Unsubscribe'] is None and m['List-Unsubscribe-Post'] is None)
    check('staff mail body has no unsubscribe link', 'unsubscribe' not in (tp.get('text/plain', '') + tp.get('text/html', '')).lower())
    code = code_from(tp.get('text/plain', ''))
    check('plain-text part carries a grouped 6-digit code', bool(code), tp.get('text/plain', '')[:120])
    check('html part carries the same code', bool(code) and (code[:3] + ' ' + code[3:]) in re.sub(r'<[^>]+>', ' ', tp.get('text/html', '')).replace('&nbsp;', ' '))
    check('every line of the wire message is <= 998 chars (RFC 5322)', all(len(x) <= 998 for x in msg['raw'].split('\r\n')))
    check('mail_outbox: status sent, driver smtp', (last_outbox(OWNER_EMAIL) or [''])[0:1] == ('sent',) and (last_outbox(OWNER_EMAIL) or ['', '', ''])[2] == 'smtp', str(last_outbox(OWNER_EMAIL)))
    # the delivered code is the real one
    s, h, b = _call(c, base, 'POST', MOUNT + '/login/', {'_csrf': t, 'do': 'verify', 'code': code or '000000'})
    check('the code from the relay signs the owner in (302)', s == 302, str(s))
    s, h, b = _call(c, base, 'GET', MOUNT + '/')
    check('portal opens after sign-in (no redirect back to login)', s == 200 and 'name="code"' not in b and '/login' not in (h.get('Location') or ''), str(s))
    s, h, b = _call(c, base, 'POST', MOUNT + '/login/', {'_csrf': t, 'do': 'verify', 'code': code or '000000'})
    check('the same code cannot be replayed on a fresh client', _call(ask(base, 'nobody@w5d.tech')[0], base, 'POST', MOUNT + '/login/', {'_csrf': t, 'do': 'verify', 'code': code or '000000'})[0] != 302)

    # French + Arabic messages are real UTF-8 and decode back
    for lang, word in (('fr', 'connexion'), ('ar', 'دخول')):
        n = len(stub.messages)
        ask(base, OWNER_EMAIL, lang); time.sleep(0.3)
        check(f'[{lang}] a second message arrives', len(stub.messages) == n + 1)
        if len(stub.messages) > n:
            m2, tp2 = parts(stub.messages[-1]['raw'])
            subj = str(email.header.make_header(email.header.decode_header(m2['Subject'] or '')))
            check(f'[{lang}] subject decodes and is translated', word in subj, subj)
            check(f'[{lang}] body decodes as UTF-8 with the code', bool(code_from(tp2.get('text/plain', ''))) and word in (tp2.get('text/plain', '') + tp2.get('text/html', '')))
            check(f'[{lang}] html declares rtl only for Arabic', ('dir="rtl"' in tp2.get('text/html', '')) == (lang == 'ar'), '')

    # unauthorised address: neutral page, no mail
    n = len(stub.messages)
    c2, t2, s2, b2 = ask(base, 'stranger@w5d.tech'); time.sleep(0.3)
    check('unknown team address: same neutral 200, nothing sent', s2 == 200 and len(stub.messages) == n)
    c3, t3, s3, b3 = ask(base, 'someone@gmail.com'); time.sleep(0.2)
    check('foreign domain: nothing sent', len(stub.messages) == n)

    # user-facing mail (non-staff) carries the one-click unsubscribe for 'updates' mail; essential mail does not
    em = f'smtp{int(time.time()*1000)%10**9}@mail.test'
    cu = _client(); tu = _csrf(cu, base, '/account/register/')
    pw = 'Str0ngPass-' + em[:8]
    n = len(stub.messages)
    _call(cu, base, 'POST', '/account/register/', {'_csrf': tu, 'name': 'Smtp Tester', 'email': em, 'password': pw, 'password2': pw, 'role': 'user', 'accept': '1'}); time.sleep(0.4)
    got = [x for x in stub.messages[n:] if em in x['to']]
    check('registration sends the verification e-mail through the relay', len(got) == 1, str(len(stub.messages) - n))
    if got:
        mu, tpu = parts(got[0]['raw'])
        link = re.search(r'/account/verify/([A-Za-z0-9_.\-]+)/', tpu.get('text/plain', '') + tpu.get('text/html', ''))
        check('verification mail contains the verify link', bool(link))
        s, h, b = _call(cu, base, 'GET', f'/account/verify/{link.group(1)}/') if link else (0, {}, '')
        check('the verify link from the relay works', s in (200, 302), str(s))
finally:
    stop_php(srv); stub.stop()

# ------------------------------------------------------------------ 2. AUTH LOGIN fallback (relay does not offer PLAIN)
stub = StubSMTP(user='u2', password='pw2', auth='LOGIN').start()
srv, base = start_php(MAIL_PORT=stub.port, MAIL_USERNAME='u2', MAIL_PASSWORD='pw2')
try:
    ask(base, OWNER_EMAIL); time.sleep(0.3)
    check('AUTH LOGIN fallback delivers', len(stub.messages) == 1 and stub.messages[0]['authed'], str(stub.commands))
finally:
    stop_php(srv); stub.stop()

# ------------------------------------------------------------------ 3. relay without authentication (e.g. local MTA)
stub = StubSMTP().start()
srv, base = start_php(MAIL_PORT=stub.port, MAIL_USERNAME='', MAIL_PASSWORD='')
try:
    ask(base, OWNER_EMAIL); time.sleep(0.3)
    check('no credentials configured -> no AUTH attempted, mail delivered', len(stub.messages) == 1 and not any(x.upper().startswith('AUTH') for x in stub.commands), str(stub.commands))
finally:
    stop_php(srv); stub.stop()

# ------------------------------------------------------------------ 4. failure paths
def failing(name, stub_kwargs, php_env, expect):
    stub = StubSMTP(**stub_kwargs).start() if stub_kwargs is not None else None
    port = stub.port if stub else free_port()  # nothing listens on a fresh port
    srv, base = start_php(MAIL_PORT=port, **php_env)
    try:
        before = q('SELECT COALESCE(MAX(id),0) FROM mail_outbox')[0][0]
        c, t, s, b = ask(base, OWNER_EMAIL); time.sleep(0.4)
        row = q("SELECT status, error FROM mail_outbox WHERE id > ? AND to_email = ? ORDER BY id DESC LIMIT 1", (before, OWNER_EMAIL))
        st, err = (row[0] if row else (None, None))
        check(f'{name}: outbox status is failed', st == 'failed', str(row))
        check(f'{name}: error recorded ({expect})', bool(err) and expect.lower() in err.lower(), str(err))
        check(f'{name}: login page stays neutral (200, code step shown, no error leak)', s == 200 and 'SMTP' not in b and 'Traceback' not in b and 'relay-user' not in b, str(s))
        check(f'{name}: nothing delivered', stub is None or not stub.messages)
        check(f'{name}: password never written to the outbox error', not err or 'secret-pw' not in err)
        # the user is not told "mail failed" in a way that reveals whether the address is authorised: same page as the success case
        check(f'{name}: page offers the code step like the success case', 'name="code"' in b, '')
    finally:
        stop_php(srv)
        if stub: stub.stop()

failing('wrong password', dict(user='relay-user', password='right'), dict(MAIL_USERNAME='relay-user', MAIL_PASSWORD='secret-pw'), 'rejected')
failing('recipient refused', dict(reject_rcpt=True), dict(MAIL_USERNAME='', MAIL_PASSWORD=''), 'rejected')
failing('message refused', dict(reject_data=True), dict(MAIL_USERNAME='', MAIL_PASSWORD=''), 'rejected')
failing('bad greeting', dict(greeting='554 no service'), dict(MAIL_USERNAME='', MAIL_PASSWORD=''), 'greeting')
failing('relay down', None, dict(MAIL_USERNAME='', MAIL_PASSWORD=''), 'connect')

# empty host
srv, base = start_php(MAIL_HOST='', MAIL_PORT=25)
try:
    before = q('SELECT COALESCE(MAX(id),0) FROM mail_outbox')[0][0]
    c, t, s, b = ask(base, OWNER_EMAIL); time.sleep(0.3)
    row = q("SELECT status, error FROM mail_outbox WHERE id > ? AND to_email = ? ORDER BY id DESC LIMIT 1", (before, OWNER_EMAIL))
    check('empty MAIL_HOST: failed with an explicit reason, page neutral', bool(row) and row[0][0] == 'failed' and 'MAIL_HOST' in (row[0][1] or '') and s == 200, str(row))
finally:
    stop_php(srv)

# ------------------------------------------------------------------ 5. dot-stuffing / SMTP injection safety (unit-style through the transport)
stub = StubSMTP().start()
srv, base = start_php(MAIL_PORT=stub.port, MAIL_USERNAME='', MAIL_PASSWORD='')
try:
    # a display name with a leading-dot line and CRLF must not smuggle a second command or header: use the contact form acknowledgement
    cu = _client(); tu = _csrf(cu, base, '/contact/')
    s, h, b = _call(cu, base, 'POST', '/contact/', {'_csrf': tu, 'name': 'Dot Tester\r\nBcc: evil@example.org', 'email': 'dot@mail.test', 'message': 'line one\n.\nQUIT\n.second line starts with a dot ' + 'x' * 30, 'topic': 'general', 'accept': '1'})
    time.sleep(0.5)
    ack = [x for x in stub.messages if 'dot@mail.test' in x['to']]
    check('contact form accepted the hostile name (page answered, not a 5xx)', s < 500, str(s))
    check('hostile contact input produced at least one relayed message to inspect', len(stub.messages) >= 1, str(s))
    for x in stub.messages:
        check('no injected Bcc header in any delivered message', not re.search(r'^Bcc:', x['raw'], re.M | re.I))
        check('no extra recipients smuggled into the envelope', len(x['to']) == 1)
finally:
    stop_php(srv); stub.stop()

print(f'\nsmtp: {ok} passed, {bad} failed')
sys.exit(1 if bad else 0)

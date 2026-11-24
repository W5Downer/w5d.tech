"""Tiny stdlib SMTP server for tests (no TLS): records every message the PHP transport (src/Mail.php smtp()) hands over.

    srv = StubSMTP(user='u', password='p', auth='PLAIN LOGIN'); srv.start()    # srv.port, srv.messages, srv.commands
    ...
    srv.stop()

It enforces what a real relay enforces: EHLO first, AUTH before MAIL when credentials are set, MAIL before RCPT before DATA,
dot-unstuffing of the DATA block, and (optionally) rejections, so a wrong step in the transport shows up as a failed send.
It does NOT speak STARTTLS/SSL: certificate handling (verify_peer) against a real provider cannot be tested here.
"""
import base64, socket, threading


class StubSMTP:
    def __init__(self, user='', password='', auth='PLAIN LOGIN', reject_rcpt=False, reject_data=False, greeting='220 stub ESMTP ready'):
        self.user, self.password, self.auth = user, password, auth
        self.reject_rcpt, self.reject_data, self.greeting = reject_rcpt, reject_data, greeting
        self.messages = []      # [{'from','to':[..],'raw':str, 'authed':bool}]
        self.commands = []      # every command line received (AUTH secrets are masked)
        self.sock = socket.socket()
        self.sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        self.sock.bind(('127.0.0.1', 0))
        self.sock.listen(8)
        self.port = self.sock.getsockname()[1]
        self._run = True
        self._t = None

    def start(self):
        self._t = threading.Thread(target=self._loop, daemon=True)
        self._t.start()
        return self

    def stop(self):
        self._run = False
        try:
            socket.create_connection(('127.0.0.1', self.port), timeout=1).close()
        except OSError:
            pass
        self.sock.close()

    def _loop(self):
        while self._run:
            try:
                c, _ = self.sock.accept()
            except OSError:
                return
            if not self._run:
                c.close()
                return
            threading.Thread(target=self._serve, args=(c,), daemon=True).start()

    def _serve(self, c):
        c.settimeout(15)
        f = c.makefile('rwb', buffering=0)

        def send(s):
            f.write((s + '\r\n').encode())

        def line():
            l = f.readline()
            return l.decode('utf-8', 'replace').rstrip('\r\n') if l else None

        try:
            send(self.greeting)
            if not self.greeting.startswith('220'):
                return
            st = {'ehlo': False, 'authed': not self.user, 'from': None, 'to': []}
            while True:
                l = line()
                if l is None:
                    return
                u = l.upper()
                if u.startswith('AUTH'):
                    self.commands.append(u.split()[0] + ' ' + (u.split() + [''])[1])
                else:
                    self.commands.append(l)
                if u.startswith('EHLO') or u.startswith('HELO'):
                    st['ehlo'] = True
                    send('250-stub greets you')
                    if self.user:
                        send('250-AUTH ' + self.auth)
                    send('250 8BITMIME')
                elif u.startswith('AUTH PLAIN'):
                    if 'PLAIN' not in self.auth.split():
                        send('504 mechanism not available'); continue
                    parts = l.split(None, 2)
                    blob = parts[2] if len(parts) > 2 else ''
                    try:
                        _, uu, pp = base64.b64decode(blob).decode().split('\0')
                    except Exception:
                        send('501 bad PLAIN'); continue
                    st['authed'] = (uu, pp) == (self.user, self.password)
                    send('235 ok' if st['authed'] else '535 authentication failed')
                elif u.startswith('AUTH LOGIN'):
                    if 'LOGIN' not in self.auth.split():
                        send('504 mechanism not available'); continue
                    send('334 ' + base64.b64encode(b'Username:').decode())
                    uu = base64.b64decode(line() or '').decode('utf-8', 'replace')
                    send('334 ' + base64.b64encode(b'Password:').decode())
                    pp = base64.b64decode(line() or '').decode('utf-8', 'replace')
                    st['authed'] = (uu, pp) == (self.user, self.password)
                    send('235 ok' if st['authed'] else '535 authentication failed')
                elif u.startswith('MAIL FROM:'):
                    if not st['ehlo']:
                        send('503 EHLO first'); continue
                    if not st['authed']:
                        send('530 authentication required'); continue
                    st['from'] = l[10:].strip().strip('<>'); st['to'] = []
                    send('250 ok')
                elif u.startswith('RCPT TO:'):
                    if st['from'] is None:
                        send('503 MAIL first'); continue
                    if self.reject_rcpt:
                        send('550 no such mailbox'); continue
                    st['to'].append(l[8:].strip().strip('<>'))
                    send('250 ok')
                elif u == 'DATA':
                    if not st['to']:
                        send('503 RCPT first'); continue
                    send('354 go ahead')
                    buf = []
                    while True:
                        d = line()
                        if d is None:
                            return
                        if d == '.':
                            break
                        buf.append(d[1:] if d.startswith('..') else d)
                    if self.reject_data:
                        send('554 message refused'); st['from'] = None; continue
                    self.messages.append({'from': st['from'], 'to': list(st['to']), 'raw': '\r\n'.join(buf) + '\r\n', 'authed': st['authed']})
                    st['from'] = None; st['to'] = []
                    send('250 queued')
                elif u == 'QUIT':
                    send('221 bye'); return
                elif u == 'RSET':
                    st['from'] = None; st['to'] = []; send('250 ok')
                elif u == 'NOOP':
                    send('250 ok')
                else:
                    send('502 not implemented')
        except (OSError, ValueError):
            pass
        finally:
            try:
                f.close(); c.close()
            except OSError:
                pass

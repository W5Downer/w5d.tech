#!/usr/bin/env python3
"""Fake payment gateway for tests/gateways.py (stdlib only). Mimics the documented shapes of Flouci, Konnect and Paymee on 127.0.0.1.
It is NOT the real API: shapes were written from each provider's public documentation and must be re-checked once against the real sandbox.
Control:  POST /_set/<id>  {"status": "...", "amount": n}     Inspect: GET /_log   Reset: POST /_reset"""
import json, sys, uuid
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

PAY = {}      # id -> {"provider","status","amount","req"}
LOG = []
KEYS = {'flouci': 'PUBTOKEN123:PRIVTOKEN456', 'konnect': 'konnect-api-key-1', 'paymee': 'paymee-token-1'}

class H(BaseHTTPRequestHandler):
    def log_message(self, *a): pass
    def _send(self, code, obj):
        b = json.dumps(obj).encode(); self.send_response(code); self.send_header('Content-Type', 'application/json'); self.send_header('Content-Length', str(len(b))); self.end_headers(); self.wfile.write(b)
    def _body(self):
        n = int(self.headers.get('Content-Length') or 0); raw = self.rfile.read(n) if n else b''
        try: return json.loads(raw or b'{}')
        except Exception: return {}
    def _auth(self, prov):
        a = self.headers.get('Authorization', ''); k = self.headers.get('x-api-key', '')
        if prov == 'flouci': return a == 'Bearer ' + KEYS['flouci']
        if prov == 'konnect': return k == KEYS['konnect']
        return a == 'Token ' + KEYS['paymee']
    def do_GET(self):
        p = self.path.split('?')[0]
        if p == '/_log': return self._send(200, LOG)
        if p.startswith('/api/v2/verify_payment/'):                     # Flouci
            if not self._auth('flouci'): return self._send(401, {'success': False})
            d = PAY.get(p.rsplit('/', 1)[1])
            if not d: return self._send(404, {'success': False})
            return self._send(200, {'success': True, 'result': {'status': d['status'], 'amount': d['amount'], 'type': 'card'}})
        if p.startswith('/konnect/payments/'):                          # Konnect
            if not self._auth('konnect'): return self._send(401, {})
            d = PAY.get(p.rsplit('/', 1)[1])
            if not d: return self._send(404, {})
            return self._send(200, {'payment': {'status': d['status'], 'amount': d['amount']}})
        if p.startswith('/paymee/payments/') and p.endswith('/check'):  # Paymee
            if not self._auth('paymee'): return self._send(401, {'status': False})
            d = PAY.get(p.split('/')[-2])
            if not d: return self._send(404, {'status': False})
            return self._send(200, {'status': True, 'data': {'payment_status': d['status'] == 'paid', 'amount': d['amount']}})
        if p.startswith('/hosted/'): return self._send(200, {'hosted': p})
        self._send(404, {})
    def do_POST(self):
        p = self.path.split('?')[0]; b = self._body()
        if p == '/_reset': PAY.clear(); LOG.clear(); return self._send(200, {})
        if p.startswith('/_set/'):
            d = PAY.get(p.rsplit('/', 1)[1])
            if not d: return self._send(404, {})
            d.update({k: v for k, v in b.items() if k in ('status', 'amount')}); return self._send(200, d)
        if p == '/api/v2/generate_payment':
            if not self._auth('flouci'): return self._send(401, {'success': False})
            i = uuid.uuid4().hex[:16]; PAY[i] = {'provider': 'flouci', 'status': 'PENDING', 'amount': int(b.get('amount', 0)), 'req': b}; LOG.append({'p': 'flouci', 'id': i, 'req': b})
            return self._send(200, {'success': True, 'result': {'link': f'http://127.0.0.1:8099/hosted/{i}', 'payment_id': i}})
        if p == '/konnect/payments/init-payment':
            if not self._auth('konnect'): return self._send(401, {})
            i = uuid.uuid4().hex[:16]; PAY[i] = {'provider': 'konnect', 'status': 'pending', 'amount': int(b.get('amount', 0)), 'req': b}; LOG.append({'p': 'konnect', 'id': i, 'req': b})
            return self._send(200, {'payUrl': f'http://127.0.0.1:8099/hosted/{i}', 'paymentRef': i})
        if p == '/paymee/payments/create':
            if not self._auth('paymee'): return self._send(401, {'status': False})
            i = uuid.uuid4().hex[:20]; PAY[i] = {'provider': 'paymee', 'status': 'unpaid', 'amount': b.get('amount'), 'req': b}; LOG.append({'p': 'paymee', 'id': i, 'req': b})
            return self._send(200, {'status': True, 'data': {'payment_url': f'http://127.0.0.1:8099/hosted/{i}', 'token': i}})
        self._send(404, {})

if __name__ == '__main__':
    ThreadingHTTPServer(('127.0.0.1', int(sys.argv[1]) if len(sys.argv) > 1 else 8099), H).serve_forever()

#!/usr/bin/env python3
"""Tiny HTTPS -> HTTP reverse proxy used ONLY by tests/production.sh to simulate a TLS-terminating host.
Usage: tls_proxy.py <listen_port> <upstream_port> <cert.pem> <key.pem>   (adds X-Forwarded-Proto: https)"""
import http.client, http.server, ssl, sys

LISTEN, UP, CERT, KEY = int(sys.argv[1]), int(sys.argv[2]), sys.argv[3], sys.argv[4]
HOP = {'connection', 'keep-alive', 'transfer-encoding', 'te', 'trailers', 'upgrade', 'proxy-authorization', 'proxy-authenticate'}


class H(http.server.BaseHTTPRequestHandler):
    protocol_version = 'HTTP/1.1'

    def log_message(self, *a): pass

    def _do(self):
        n = int(self.headers.get('Content-Length') or 0)
        body = self.rfile.read(n) if n else None
        h = {k: v for k, v in self.headers.items() if k.lower() not in HOP}
        h['X-Forwarded-Proto'] = 'https'
        c = http.client.HTTPConnection('127.0.0.1', UP, timeout=60)
        c.request(self.command, self.path, body=body, headers=h)
        r = c.getresponse(); data = r.read()
        self.send_response(r.status)
        for k, v in r.getheaders():
            if k.lower() not in HOP and k.lower() != 'content-length': self.send_header(k, v)
        self.send_header('Content-Length', str(len(data))); self.end_headers()
        if self.command != 'HEAD': self.wfile.write(data)

    do_GET = do_POST = do_HEAD = do_PUT = do_DELETE = do_OPTIONS = _do


class S(http.server.ThreadingHTTPServer):
    daemon_threads = True

ctx = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER); ctx.load_cert_chain(CERT, KEY)
srv = S(('127.0.0.1', LISTEN), H); srv.socket = ctx.wrap_socket(srv.socket, server_side=True)
srv.serve_forever()

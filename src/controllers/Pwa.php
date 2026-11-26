<?php
declare(strict_types=1);

namespace W5D\Controllers;

use W5D\{Env, Request, Response};

/**
 * Installable owner/staff portal (PWA). Everything lives UNDER the portal mount, so the service worker's default scope is the portal
 * and no public page, robots.txt or sitemap ever mentions it:
 *   {mount}/manifest.webmanifest   app identity (start_url + scope = the portal)
 *   {mount}/sw.js                  service worker: caches ONLY the offline page and static assets (css/js/icons/fonts)
 *   {mount}/offline/               tri-lingual offline page (static, no personal data, no session needed)
 * Private pages (HTML, JSON, forms) are never cached: while offline a navigation shows the offline page, a POST is held back by portal.js.
 */
final class Pwa
{
    private const HEADERS = ['X-Robots-Tag' => 'noindex, nofollow', 'X-Content-Type-Options' => 'nosniff'];

    /** Static files that make the offline page (and the shell) work with no network. Each URL is exactly what asset() prints. */
    private static function shell(): array
    {
        return [asset('css/bundle.css'), asset('css/bundle-ar.css'), asset('js/theme-init.js'), asset('js/portal.min.js'),
            asset('images/logo.svg'), asset('images/icon-192.png'), asset('images/icon-512.png')];
    }

    public static function manifest(Request $q): Response
    {
        $i = fn(string $f, string $sz, string $purpose = 'any') => ['src' => url('assets/_base/images/' . $f), 'sizes' => $sz, 'type' => 'image/png', 'purpose' => $purpose];
        $start = admin_url();
        $j = json_encode([
            'name' => 'W5D Portal', 'short_name' => 'W5D Portal', 'description' => 'W5D team portal', 'id' => $start, 'start_url' => $start . '?source=pwa', 'scope' => $start,
            'display' => 'standalone', 'display_override' => ['window-controls-overlay', 'standalone'], 'orientation' => 'any', 'background_color' => '#070711', 'theme_color' => '#070711',
            'categories' => ['business', 'productivity'],
            'icons' => [$i('icon-192.png', '192x192'), $i('icon-512.png', '512x512'), $i('icon-maskable-512.png', '512x512', 'maskable')],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return Response::text((string)$j, 'application/manifest+json')->withHeaders(self::HEADERS + ['Cache-Control' => 'no-cache']);
    }

    public static function worker(Request $q): Response
    {
        $shell = self::shell();
        $ver = substr(md5(implode('|', $shell) . '|' . admin_url('offline/')), 0, 10);
        $js = json_encode(['v' => $ver, 'offline' => admin_url('offline/'), 'scope' => admin_url(), 'assets' => url('assets/_base/'), 'shell' => $shell], JSON_UNESCAPED_SLASHES);
        $body = <<<JS
/* W5D portal service worker. Caches the offline page and static assets only. Never caches pages, forms or JSON (they are private). */
'use strict';
var C = $js, STATIC = 'w5d-portal-static-' + C.v;
self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(STATIC).then(function (c) {
    return Promise.all([C.offline].concat(C.shell).map(function (u) { return c.add(new Request(u, { cache: 'reload' })).catch(function () {}); }));
  }).then(function () { return self.skipWaiting(); }));
});
self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (ks) {
    return Promise.all(ks.filter(function (k) { return k.indexOf('w5d-portal-') === 0 && k !== STATIC; }).map(function (k) { return caches.delete(k); }));
  }).then(function () { return self.clients.claim(); }));
});
self.addEventListener('fetch', function (e) {
  var r = e.request;
  if (r.method !== 'GET') return;
  var u = new URL(r.url);
  if (u.origin !== self.location.origin) return;
  if (r.mode === 'navigate') {
    e.respondWith(fetch(r).catch(function () { return caches.match(C.offline).then(function (m) { return m || new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain' } }); }); }));
    return;
  }
  if (u.pathname.indexOf(C.assets) === 0) {
    e.respondWith(caches.open(STATIC).then(function (c) {
      return c.match(r).then(function (hit) {
        var net = fetch(r).then(function (res) { if (res && res.ok) c.put(r, res.clone()); return res; }).catch(function () { return hit; });
        return hit || net;
      });
    }));
  }
});
JS;
        return Response::text($body, 'text/javascript')->withHeaders(self::HEADERS + ['Cache-Control' => 'no-cache, max-age=0']);
    }

    public static function offline(Request $q): Response
    {
        return Response::html(view('admin.offline', ['title' => 'Offline']))->withHeaders(self::HEADERS + ['Cache-Control' => 'no-cache']);
    }
}

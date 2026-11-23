/* portal.js — installable owner/staff portal: service worker, offline indicator, install button. External file (CSP script-src 'self'). */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var root = document.documentElement;

  /* ---- offline indicator: html.is-offline + banner; the page itself stays usable (read what is already on screen) ---- */
  var banner = $('[data-offline-banner]');
  function paint() {
    var off = navigator.onLine === false;
    root.classList.toggle('is-offline', off);
    if (banner) banner.hidden = !off;
    var st = $('[data-offline-status]');
    if (st) st.textContent = off ? '' : '✓';
    if (!off && $('[data-offline-page]')) { var back = document.referrer && document.referrer.indexOf(location.origin) === 0 ? document.referrer : null; if (back && back.indexOf('/offline') < 0) location.replace(back); }
  }
  window.addEventListener('online', paint);
  window.addEventListener('offline', paint);
  paint();
  $$('[data-offline-retry]').forEach(function (b) { b.addEventListener('click', function () { if (navigator.onLine === false) { var st = $('[data-offline-status]'); if (st) st.textContent = '…'; setTimeout(function () { location.reload(); }, 300); } else history.length > 1 ? history.back() : location.reload(); }); });

  /* ---- service worker: path comes from the page (data-sw) so the mount is never hard-coded here ---- */
  var sw = document.querySelector('meta[name=w5d-sw]');
  if (sw && 'serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    window.addEventListener('load', function () { navigator.serviceWorker.register(sw.getAttribute('content')).catch(function () {}); });
  }

  /* ---- install button (Chrome/Edge/Android: beforeinstallprompt; iOS Safari: manual hint) ---- */
  var deferred = null, standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
  var btns = $$('[data-pwa-install]'), ios = $$('[data-pwa-ios]');
  if (standalone) root.classList.add('is-installed');
  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); deferred = e; if (!standalone) btns.forEach(function (b) { b.hidden = false; }); });
  btns.forEach(function (b) { b.addEventListener('click', function () { if (!deferred) return; deferred.prompt(); deferred.userChoice.then(function () { deferred = null; btns.forEach(function (x) { x.hidden = true; }); }); }); });
  window.addEventListener('appinstalled', function () { deferred = null; btns.forEach(function (b) { b.hidden = true; }); root.classList.add('is-installed'); });
  var ua = navigator.userAgent || '';
  if (!standalone && /iphone|ipad|ipod/i.test(ua) && !/crios|fxios/i.test(ua)) ios.forEach(function (n) { n.hidden = false; });
})();

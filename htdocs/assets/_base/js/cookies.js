/* cookies.js — the cookie notice (always on, every public page, first-party, no dependency).
 * Shows once until the visitor chooses; reopen from the footer ([data-cookie-open]) or /trust/cookies/.
 * Storage (localStorage, never a cookie): w5d-cookies = {v:1, m:0|1, t:ms}  (m = measurement/advertising allowed)
 *                                         w5d-consent  = {v:1, a:0|1, t:ms}  (read by integrations.js; kept for compatibility)
 * Strictly necessary cookies (session, sign-in, CSRF) need no consent and are always on. Trackers load only when m = 1
 * AND the owner has switched an integration on (the notice then carries data-track="1"). Choice lasts 6 months, then it is asked again. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var box = $('[data-cookie-notice]');
  if (!box) return;
  var KEY = 'w5d-cookies', OLD = 'w5d-consent', TTL = 183 * 864e5;
  var track = box.getAttribute('data-track') === '1';

  function read() {
    try {
      var j = JSON.parse(localStorage.getItem(KEY) || 'null');
      if (j && j.v === 1 && Date.now() - j.t < TTL) return j;
      if (!j) { var o = JSON.parse(localStorage.getItem(OLD) || 'null'); if (o && o.v === 1 && Date.now() - o.t < TTL) return { v: 1, m: o.a ? 1 : 0, t: o.t }; }
    } catch (e) {}
    return null;
  }
  function write(m) {
    var t = Date.now();
    try { localStorage.setItem(KEY, JSON.stringify({ v: 1, m: m ? 1 : 0, t: t })); localStorage.setItem(OLD, JSON.stringify({ v: 1, a: m ? 1 : 0, t: t })); } catch (e) {}
  }
  function show(open) {
    box.hidden = false;
    var d = $('details', box); if (d && open) d.open = true;
    var tg = $('[data-cookie-measure]', box), c = read(); if (tg) tg.checked = !!(c && c.m);
    document.documentElement.classList.add('cookies-open');
  }
  function hide() { box.hidden = true; document.documentElement.classList.remove('cookies-open'); }
  function announce(m) { document.dispatchEvent(new CustomEvent('w5d:consent', { detail: { measure: !!m } })); }
  function choose(m) {
    var before = read(); write(m); hide();
    if (before && before.m && !m && track) { location.reload(); return; } // trackers already ran: reload so they stop
    announce(m);
    var live = $('[data-cookie-live]'); if (live) live.textContent = box.getAttribute('data-saved') || '';
  }

  $$('[data-cookie-all]', box).forEach(function (b) { b.addEventListener('click', function () { choose(track); }); });
  $$('[data-cookie-essential]', box).forEach(function (b) { b.addEventListener('click', function () { choose(false); }); });
  $$('[data-cookie-save]', box).forEach(function (b) { b.addEventListener('click', function () { var tg = $('[data-cookie-measure]', box); choose(!!(tg && tg.checked)); }); });
  $$('[data-cookie-open]').forEach(function (b) { b.addEventListener('click', function () { show(true); var f = $('button', box); if (f) f.focus(); }); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !box.hidden && read()) hide(); });

  var c = read();
  if (!c) show(false); else announce(c.m);
})();

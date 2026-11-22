/* integrations.js — third-party services, configured by the owner (portal → Integrations). External file: CSP script-src 'self'.
 * Config arrives in the data-w5d-int attribute of this script tag (JSON, no secrets). Nothing here runs unless the owner enabled it.
 *  - consent: trackers load ONLY after the visitor accepts in the cookie notice (js/cookies.js; localStorage "w5d-consent"); refusing keeps everything off; the choice can be changed from the footer.
 *  - reCAPTCHA v3 (invisible) / v2 (checkbox) on every <form> that carries [data-captcha] / [data-captcha-v2]; works for forms injected later (sign-in modal).
 */
(function () {
  'use strict';
  var me = document.currentScript || document.querySelector('script[data-w5d-int]');
  var cfg = {};
  try { cfg = JSON.parse((me && me.getAttribute('data-w5d-int')) || '{}'); } catch (e) { cfg = {}; }
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  function addScript(src, done) {
    var s = document.createElement('script'); s.async = true; s.src = src;
    if (done) s.onload = done;
    document.head.appendChild(s); return s;
  }

  /* ---------------- consent + trackers ---------------- */
  var KEY = 'w5d-consent', loaded = false;
  function stored() { try { var j = JSON.parse(localStorage.getItem(KEY) || 'null'); return j && j.v === 1 ? j : null; } catch (e) { return null; } }
  function save(a) { try { localStorage.setItem(KEY, JSON.stringify({ v: 1, a: a ? 1 : 0, t: Date.now() })); } catch (e) {} }

  function loadTrackers(t) {
    if (loaded) return; loaded = true;
    window.dataLayer = window.dataLayer || [];
    var gtag = window.gtag = function () { window.dataLayer.push(arguments); };
    var sig = !!t.sig;
    gtag('consent', 'default', { ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied', wait_for_update: 500 });
    gtag('consent', 'update', { analytics_storage: 'granted', ad_storage: sig ? 'granted' : 'denied', ad_user_data: sig ? 'granted' : 'denied', ad_personalization: sig ? 'granted' : 'denied' });
    var first = t.ga4 || (t.ads && t.ads.id);
    if (first) {
      gtag('js', new Date());
      var loc = t.loc ? { page_location: location.origin + t.loc, page_path: t.loc } : {};
      if (t.ga4) gtag('config', t.ga4, Object.assign({ allow_google_signals: sig, allow_ad_personalization_signals: sig }, loc));
      if (t.ads && t.ads.id) gtag('config', t.ads.id, Object.assign({ allow_ad_personalization_signals: sig }, loc));
      if (t.conv && t.ads && t.ads.id && t.ads.label) gtag('event', 'conversion', { send_to: t.ads.id + '/' + t.ads.label });
      if (t.conv && t.ga4) gtag('event', 'generate_lead', {});
      addScript('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(first));
    }
    if (t.gtm) {
      window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
      addScript('https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(t.gtm));
    }
    if (t.meta) {
      var n = window.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
      if (!window._fbq) window._fbq = n; n.push = n; n.loaded = true; n.version = '2.0'; n.queue = [];
      addScript('https://connect.facebook.net/en_US/fbevents.js');
      n('init', t.meta); n('track', 'PageView'); if (t.conv) n('track', 'Lead');
    }
  }

  /* The notice itself lives in js/cookies.js (always on, every page). It tells us the choice through the "w5d:consent" event
   * and keeps the legacy localStorage record; trackers start only when measure = true. */
  function setupConsent(t) {
    var c = stored();
    if (c && c.a) loadTrackers(t);
    document.addEventListener('w5d:consent', function (e) { if (e.detail && e.detail.measure) loadTrackers(t); });
  }
  if (cfg.track) setupConsent(cfg.track);

  /* ---------------- reCAPTCHA ---------------- */
  var cap = cfg.captcha;
  if (cap) {
    var ready = false, waiting = [], injected = false;
    function load() {
      if (injected) return; injected = true;
      window.w5dCaptchaReady = function () { ready = true; waiting.splice(0).forEach(function (f) { f(); }); };
      addScript('https://' + cap.domain + '/recaptcha/api.js?' + (cap.v === 3 ? 'render=' + encodeURIComponent(cap.key) + '&' : 'render=explicit&') + 'onload=w5dCaptchaReady');
    }
    function whenReady(f) { if (ready && window.grecaptcha) f(); else { waiting.push(f); load(); } }
    function scan(root) {
      if (!$('[data-captcha], [data-captcha-v2]', root || document) && !(root && root.matches && root.matches('[data-captcha],[data-captcha-v2]'))) return;
      load();
      if (cap.v === 2) whenReady(function () {
        $$('[data-captcha-v2]', root || document).forEach(function (el) {
          if (el.getAttribute('data-rendered')) return; el.setAttribute('data-rendered', '1');
          el.__wid = window.grecaptcha.render(el, { sitekey: cap.key, theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light' });
        });
      });
    }
    scan(document);
    if (window.MutationObserver) new MutationObserver(function (m) { m.forEach(function (r) { r.addedNodes.forEach(function (n) { if (n.nodeType === 1) scan(n); }); }); }).observe(document.body || document.documentElement, { childList: true, subtree: true });

    // Capture-phase, document level: runs before any form-specific handler (including the sign-in modal's fetch submit).
    document.addEventListener('submit', function (e) {
      var f = e.target; if (!f || f.tagName !== 'FORM') return;
      var v3 = $('[data-captcha]', f), v2 = $('[data-captcha-v2]', f);
      if (!v3 && !v2) return;
      if (f.getAttribute('data-captcha-pass') === '1') { f.removeAttribute('data-captcha-pass'); if (v2 && v2.__wid !== undefined) setTimeout(function () { try { window.grecaptcha.reset(v2.__wid); } catch (x) {} }, 1500); return; }
      if (v2) {
        var ta = $('textarea[name="g-recaptcha-response"]', f);
        if (!ta || !ta.value) { e.preventDefault(); e.stopImmediatePropagation(); var box = v2.parentNode; var msg = $('[data-captcha-msg]', f); if (msg) msg.hidden = false; v2.setAttribute('data-needs', '1'); v2.scrollIntoView({ block: 'center' }); return; }
        return;
      }
      e.preventDefault(); e.stopImmediatePropagation();
      var sub = e.submitter || null;
      whenReady(function () {
        window.grecaptcha.ready(function () {
          window.grecaptcha.execute(cap.key, { action: 'w5d_form' }).then(function (tok) {
            v3.value = tok; f.setAttribute('data-captcha-pass', '1');
            if (f.requestSubmit) { try { f.requestSubmit(sub && sub.form === f ? sub : undefined); } catch (x) { f.requestSubmit(); } } else f.submit();
          }, function () { f.setAttribute('data-captcha-pass', '1'); v3.value = ''; if (f.requestSubmit) f.requestSubmit(); else f.submit(); });
        });
      });
    }, true);
  }
})();

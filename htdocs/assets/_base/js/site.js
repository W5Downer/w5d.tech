/* site.js — dependency-free behaviour for the w5d template (theme, mega-menu, slider, FAQ, tabs, counters,
 * reveal, sticky CTA, wizard, live price quote, copy buttons). Replaces main.js, megamenu.js, shell.js, logos.js, api.js. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- theme ---------- */
  function syncThemeIcons() {
    var dark = document.documentElement.classList.contains('dark');
    $$('[data-theme-icon]').forEach(function (el) {
      el.classList.toggle('hidden', (el.getAttribute('data-theme-icon') === 'sun') !== dark);
    });
  }
  $$('[data-theme-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = document.documentElement.classList.toggle('dark');
      try { localStorage.setItem('w5d-theme', d ? 'dark' : 'light'); } catch (e) {}
      syncThemeIcons();
    });
  });
  syncThemeIcons();

  /* ---------- menus: one open/close animation (.menu-panel.is-open) for mobile menu + desktop mega panels ---------- */
  function setPanel(p, on) {
    if (!p) return;
    p.classList.toggle('is-open', on);
    p.setAttribute('aria-hidden', on ? 'false' : 'true');
  }
  var mt = $('[data-mobile-toggle]'), mm = $('#mobileMenu');
  if (mt && mm) {
    mt.addEventListener('click', function () {
      var open = !mm.classList.contains('is-open');
      setPanel(mm, open);
      mt.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    mm.addEventListener('click', function (e) { if (e.target.closest('a')) { setPanel(mm, false); mt.setAttribute('aria-expanded', 'false'); } });
    window.addEventListener('resize', function () { if (window.innerWidth >= 1024) { setPanel(mm, false); mt.setAttribute('aria-expanded', 'false'); } });
  }

  /* bottom tab bar (phones): "Menu" opens the same panel as the header burger */
  var tm = $('[data-tab-menu]');
  if (tm && mt) tm.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); mt.click(); });

  /* mega menu: hover (with a delay bridge) + click + keyboard */
  var triggers = $$('[data-mega-trigger]'), closeT;
  function closeAll(except) {
    triggers.forEach(function (t) {
      if (t === except) return;
      var b = $('button', t);
      setPanel($('[data-mega-panel]', t), false);
      if (b) b.setAttribute('aria-expanded', 'false');
    });
  }
  function openOne(t) {
    clearTimeout(closeT);
    closeAll(t);
    var b = $('button', t);
    setPanel($('[data-mega-panel]', t), true);
    if (b) b.setAttribute('aria-expanded', 'true');
  }
  var finePointer = window.matchMedia && window.matchMedia('(hover: hover)').matches;
  triggers.forEach(function (t) {
    if (finePointer) {
      t.addEventListener('mouseenter', function () { openOne(t); });
      t.addEventListener('mouseleave', function () { closeT = setTimeout(function () { closeAll(); }, 220); });
    }
    var b = $('button', t), p = $('[data-mega-panel]', t);
    if (p && finePointer) {
      p.addEventListener('mouseenter', function () { clearTimeout(closeT); });
      p.addEventListener('mouseleave', function () { closeT = setTimeout(function () { closeAll(); }, 220); });
    }
    if (b) b.addEventListener('click', function () {
      if (p && p.classList.contains('is-open')) closeAll(); else openOne(t);
    });
    t.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeAll(); if (b) b.focus(); } });
  });
  document.addEventListener('click', function (e) { if (!e.target.closest('[data-mega-trigger]')) closeAll(); });

  /* ---------- asides (language / share / user) ---------- */
  var backdrop = $('.w5d-aside-backdrop'), lastFocus = null;
  function closeAsides() { $$('.w5d-aside.is-open').forEach(function (a) { toggleAside(a.id, false); }); }
  function toggleAside(id, on) {
    var a = document.getElementById(id);
    if (!a) return;
    if (on) { $$('.w5d-aside.is-open').forEach(function (o) { if (o !== a) toggleAside(o.id, false); }); lastFocus = document.activeElement; }
    a.classList.toggle('is-open', on);
    a.setAttribute('aria-hidden', on ? 'false' : 'true');
    $$('[data-aside-open="' + id + '"]').forEach(function (b) { b.setAttribute('aria-expanded', on ? 'true' : 'false'); });
    if (backdrop) backdrop.classList.toggle('is-open', !!$('.w5d-aside.is-open'));
    if (on) { var f = $('a[href],button,input', a); setTimeout(function () { if (f) f.focus(); }, 60); }
    else if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (e) {} }
  }
  $$('[data-aside-open]').forEach(function (b) { b.addEventListener('click', function () { closeAll(); toggleAside(b.getAttribute('data-aside-open'), true); }); });
  $$('[data-aside-close]').forEach(function (b) { b.addEventListener('click', closeAsides); });
  if (backdrop) backdrop.addEventListener('click', closeAsides);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeAsides(); closeAll(); } });
  $$('.w5d-aside a[href]').forEach(function (a) { a.addEventListener('click', function () { if (!a.hasAttribute('target')) closeAsides(); }); });

  /* ---------- share aside: native Web Share (when supported) + copy link ---------- */
  $$('[data-share-native]').forEach(function (b) {
    if (!navigator.share) { b.classList.add('hidden'); return; }
    b.addEventListener('click', function () {
      navigator.share({ title: b.getAttribute('data-share-title') || document.title, text: b.getAttribute('data-share-text') || '', url: b.getAttribute('data-share-url') || location.href }).catch(function () {});
    });
  });
  $$('[data-share-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var url = b.getAttribute('data-share-copy') || location.href, lab = $('[data-share-label]', b), old = lab ? lab.textContent : '';
      function ok() { if (lab) { lab.textContent = b.getAttribute('data-copied') || '✓'; setTimeout(function () { lab.textContent = old; }, 1600); } }
      if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(url).then(ok, fallback); else fallback();
      function fallback() {
        var t = document.createElement('textarea'); t.value = url; t.setAttribute('readonly', ''); t.style.position = 'fixed'; t.style.opacity = '0';
        document.body.appendChild(t); t.select(); try { document.execCommand('copy'); ok(); } catch (e) {} document.body.removeChild(t);
      }
    });
  });

  /* ---------- FAQ ---------- */
  $$('[data-faq]').forEach(function (item) {
    var btn = $('[data-faq-btn]', item), panel = $('[data-faq-panel]', item), icon = $('[data-faq-icon]', item);
    if (!btn || !panel) return;
    btn.addEventListener('click', function () {
      var open = !panel.classList.contains('hidden');
      $$('[data-faq-panel]', item.parentNode).forEach(function (p) { p.classList.add('hidden'); });
      $$('[data-faq-icon]', item.parentNode).forEach(function (i) { i.classList.remove('rotate-45'); });
      $$('[data-faq-btn]', item.parentNode).forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
      if (!open) { panel.classList.remove('hidden'); btn.setAttribute('aria-expanded', 'true'); if (icon) icon.classList.add('rotate-45'); }
    });
  });

  /* ---------- tabs ---------- */
  $$('[data-tabs]').forEach(function (root) {
    var btns = $$('[data-tab]', root), panes = $$('[data-pane]', root);
    function show(id) {
      btns.forEach(function (b) { var on = b.getAttribute('data-tab') === id; b.setAttribute('aria-selected', on ? 'true' : 'false'); b.classList.toggle('bg-brand-500/15', on); });
      panes.forEach(function (p) { p.classList.toggle('hidden', p.getAttribute('data-pane') !== id); });
    }
    btns.forEach(function (b) { b.addEventListener('click', function () { show(b.getAttribute('data-tab')); }); });
  });

  /* ---------- reveal on scroll ---------- */
  var rv = $$('[data-rv],[data-reveal]');
  if (rv.length && 'IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('rv-in', 'is-visible'); io.unobserve(e.target); } });
    }, { threshold: 0.12 });
    rv.forEach(function (el) { io.observe(el); });
  } else rv.forEach(function (el) { el.classList.add('rv-in', 'is-visible'); });

  /* ---------- counters (values come from rollups/variables, rendered server-side in data-count) ---------- */
  var cs = $$('[data-count]');
  function runCount(el) {
    var to = parseFloat(el.getAttribute('data-count')) || 0, dec = parseInt(el.getAttribute('data-dec') || '0', 10);
    var suffix = el.getAttribute('data-suffix') || '';
    if (reduce || to === 0) { el.textContent = to.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec }) + suffix; return; }
    var t0 = null, dur = 1200;
    (function step(ts) {
      if (!t0) t0 = ts;
      var k = Math.min(1, (ts - t0) / dur), v = to * (1 - Math.pow(1 - k, 3));
      el.textContent = v.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec }) + suffix;
      if (k < 1) requestAnimationFrame(step);
    })(performance.now());
  }
  if (cs.length && 'IntersectionObserver' in window) {
    var io2 = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { runCount(e.target); io2.unobserve(e.target); } }); }, { threshold: 0.4 });
    cs.forEach(function (el) { io2.observe(el); });
  } else cs.forEach(runCount);

  /* ---------- JSON-driven slider ---------- */
  $$('[data-slider]').forEach(function (root) {
    var slides = $$('[data-slide]', root), dots = $$('[data-dot]', root), i = 0, timer = null;
    var ms = parseInt(root.getAttribute('data-interval') || '7000', 10), auto = root.getAttribute('data-autoplay') !== '0' && !reduce;
    root.style.setProperty('--slider-ms', ms + 'ms');
    function go(n) {
      i = (n + slides.length) % slides.length;
      slides.forEach(function (s, k) { s.classList.toggle('is-active', k === i); s.setAttribute('aria-hidden', k === i ? 'false' : 'true'); });
      dots.forEach(function (d, k) { d.setAttribute('aria-current', k === i ? 'true' : 'false'); });
      var live = $('[data-live]', root); if (live) live.textContent = (i + 1) + ' / ' + slides.length;
      if (auto) restart();
    }
    function restart() {
      clearTimeout(timer);
      var bar = $('.w5-progress > i', root);
      root.setAttribute('data-playing', '0');
      if (bar) { bar.style.animation = 'none'; void bar.offsetWidth; bar.style.animation = ''; }
      root.setAttribute('data-playing', '1');
      timer = setTimeout(function () { go(i + 1); }, ms);
    }
    function stop() { clearTimeout(timer); root.setAttribute('data-playing', '0'); }
    $$('[data-prev]', root).forEach(function (b) { b.addEventListener('click', function () { go(i - 1); }); });
    $$('[data-next]', root).forEach(function (b) { b.addEventListener('click', function () { go(i + 1); }); });
    dots.forEach(function (d, k) { d.addEventListener('click', function () { go(k); }); });
    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', function () { if (auto) restart(); });
    root.addEventListener('focusin', stop);
    root.addEventListener('keydown', function (e) {
      var rtl = document.documentElement.dir === 'rtl';
      if (e.key === 'ArrowRight') go(i + (rtl ? -1 : 1));
      if (e.key === 'ArrowLeft') go(i + (rtl ? 1 : -1));
    });
    var x0 = null;
    root.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
    root.addEventListener('touchend', function (e) {
      if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0; x0 = null;
      if (Math.abs(dx) > 50) go(i + ((dx < 0) !== (document.documentElement.dir === 'rtl') ? 1 : -1));
    });
    document.addEventListener('visibilitychange', function () { if (document.hidden) stop(); else if (auto) restart(); });
    go(0);
  });

  /* ---------- card image carousels (click/arrow/swipe; auto-advances only without reduced motion, pauses on hover) ---------- */
  $$('[data-card-carousel]').forEach(function (root) {
    var sl = $$('[data-cslide]', root), dots = $$('[data-cdot]', root), i = 0, timer = null;
    function go(n, ev) {
      if (ev) { ev.preventDefault(); ev.stopPropagation(); }
      i = (n + sl.length) % sl.length;
      sl.forEach(function (s, k) { s.classList.toggle('is-active', k === i); if (k) s.setAttribute('aria-hidden', k === i ? 'false' : 'true'); });
      dots.forEach(function (d, k) { d.classList.toggle('is-active', k === i); });
    }
    function play() { if (reduce) return; stop(); timer = setInterval(function () { go(i + 1); }, 4500); }
    function stop() { if (timer) clearInterval(timer); timer = null; }
    var pv = $('[data-cprev]', root), nx = $('[data-cnext]', root);
    if (pv) pv.addEventListener('click', function (e) { go(i - 1, e); });
    if (nx) nx.addEventListener('click', function (e) { go(i + 1, e); });
    root.addEventListener('mouseenter', stop); root.addEventListener('mouseleave', play);
    root.addEventListener('focusin', stop); root.addEventListener('focusout', play);
    var x0 = null;
    root.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
    root.addEventListener('touchend', function (e) {
      if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0; x0 = null;
      if (Math.abs(dx) > 40) go(i + ((dx < 0) !== (document.documentElement.dir === 'rtl') ? 1 : -1));
    });
    if ('IntersectionObserver' in window) new IntersectionObserver(function (en) { en[0].isIntersecting ? play() : stop(); }).observe(root); else play();
  });

  /* ---------- sticky CTA (after 40 % scroll) ---------- */
  var sc = $('[data-sticky-cta]');
  if (sc) {
    var onScroll = function () {
      var h = document.documentElement, pct = (window.scrollY + window.innerHeight) / h.scrollHeight;
      sc.classList.toggle('is-on', window.scrollY > 200 && pct > 0.4 && pct < 0.96);
    };
    window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
  }

  /* ---------- spotlight ---------- */
  $$('.spotlight').forEach(function (el) {
    el.addEventListener('mousemove', function (e) {
      var r = el.getBoundingClientRect();
      el.style.setProperty('--mx', (e.clientX - r.left) + 'px'); el.style.setProperty('--my', (e.clientY - r.top) + 'px');
    });
  });

  /* ---------- copy buttons ---------- */
  $$('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var src = document.getElementById(b.getAttribute('data-copy')), txt = src ? src.textContent : b.getAttribute('data-copy-text') || '';
      if (navigator.clipboard) navigator.clipboard.writeText(txt.trim()).then(function () {
        var old = b.textContent; b.textContent = '✓'; setTimeout(function () { b.textContent = old; }, 1200);
      });
    });
  });

  /* ---------- wizard (progressive enhancement; the form works without JS, every step visible) ---------- */
  $$('[data-wizard]').forEach(function (form) {
    var steps = $$('.wz-step', form), dots = $$('[data-wz-dot]', form), cur = 0;
    var start = parseInt(form.getAttribute('data-start') || '0', 10);
    function show(n) {
      cur = Math.max(0, Math.min(steps.length - 1, n));
      steps.forEach(function (s, k) { s.classList.toggle('is-active', k === cur); });
      dots.forEach(function (d, k) { d.classList.toggle('is-active', k === cur); d.classList.toggle('is-done', k < cur); });
      var top = form.getBoundingClientRect().top + window.scrollY - 100;
      if (!reduce && n > 0) window.scrollTo({ top: top, behavior: 'smooth' });
      if (steps[cur].querySelector('[data-review]')) { document.dispatchEvent(new CustomEvent('wizard:review')); }
    }
    function valid(step) {
      var ok = true;
      $$('input,select,textarea', step).forEach(function (f) {
        f.removeAttribute('aria-invalid');
        if (!f.checkValidity()) { ok = false; f.setAttribute('aria-invalid', 'true'); }
      });
      if (!ok) { var bad = step.querySelector('[aria-invalid]'); if (bad) { bad.reportValidity(); bad.focus(); } }
      return ok;
    }
    $$('[data-wz-next]', form).forEach(function (b) { b.addEventListener('click', function () { if (valid(steps[cur])) show(cur + 1); }); });
    $$('[data-wz-prev]', form).forEach(function (b) { b.addEventListener('click', function () { show(cur - 1); }); });
    show(start);
  });

  /* ---------- live price quote (calls the same published function through /api/quote) ---------- */
  $$('[data-quote]').forEach(function (box) {
    var form = box.closest('form') || document, out = {
      unit: $('[data-q-unit]', box), total: $('[data-q-total]', box), rows: $('[data-q-rows]', box), note: $('[data-q-note]', box)
    };
    var timer;
    function fmt(n) { return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function read(n, d) { var f = form.querySelector('[name="' + n + '"]'); return f ? f.value : d; }
    function refresh() {
      var q = new URLSearchParams({ parts: read('parts_per_day', 1), days: read('days', 30), start: read('start_on', ''), cadence: read('cadence', 'daily') });
      fetch(box.getAttribute('data-quote') + '?' + q.toString(), { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (out.unit) out.unit.textContent = fmt(j.today.unit) + ' ' + j.currency;
          if (out.total) out.total.textContent = fmt(j.schedule.total) + ' ' + j.currency;
          if (out.rows) {
            var h = '', rows = j.schedule.rows, show = rows.length > 14 ? rows.slice(0, 7).concat([null], rows.slice(-6)) : rows;
            show.forEach(function (r) {
              h += r ? '<tr><td>' + r.date + '</td><td class="text-end tabular-nums">' + fmt(r.unit) + '</td><td class="text-end tabular-nums">' + fmt(r.amount) + '</td></tr>'
                     : '<tr><td colspan="3" class="text-center opacity-60">…</td></tr>';
            });
            out.rows.innerHTML = h;
          }
          if (out.note) out.note.textContent = 'p = ' + j.p + ' · t(today) = ' + j.t;
        }).catch(function () {});
    }
    $$('input,select', form).forEach(function (f) { f.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(refresh, 200); }); });
    refresh();
  });

  /* ---------- review mirror (wizard final step shows the typed values) ---------- */
  document.addEventListener('wizard:review', function () {
    $$('[data-mirror]').forEach(function (el) {
      var f = document.querySelector('[name="' + el.getAttribute('data-mirror') + '"]');
      if (!f) return;
      el.textContent = f.tagName === 'SELECT' ? f.options[f.selectedIndex].text : f.value;
    });
  });


  /* ---------- client-side list filter (service catalog) ---------- */
  $$('[data-filter]').forEach(function (inp) {
    var list = document.querySelector(inp.getAttribute('data-filter'));
    if (!list) return;
    var items = $$('[data-filter-item]', list);
    inp.addEventListener('input', function () {
      var q = inp.value.trim().toLowerCase();
      items.forEach(function (li) { li.classList.toggle('hidden', q && li.getAttribute('data-filter-item').indexOf(q) < 0); });
    });
  });

  /* ---------- API playground (developers page) ---------- */
  $$('[data-try]').forEach(function (b) {
    b.addEventListener('click', function () {
      var out = document.getElementById(b.getAttribute('data-out'));
      if (out) out.textContent = '…';
      fetch(b.getAttribute('data-try'), { headers: { Accept: 'application/json' } }).then(function (r) { return r.text(); })
        .then(function (t) { if (out) out.textContent = t.slice(0, 4000); });
    });
  });


  /* ---------- auth modal (login / register) + quick-find dialog ----------
   * Progressive enhancement: every trigger is a real link ([data-auth-open] -> /account/login/ or /register/), so without JS the
   * full page opens. With JS the same form is fetched as a fragment (GET /account/modal/{mode}) and posted with X-W5D-Modal: 1,
   * which makes the server answer JSON instead of redirecting. CSRF, honeypot, rate limits and `next` are unchanged. */
  (function () {
    var dlg = document.getElementById('authDialog');
    var body = dlg && $('[data-auth-body]', dlg);
    var returnTo = null;
    function openDlg(d) { if (!d) return; returnTo = document.activeElement; if (d.showModal) { if (!d.open) d.showModal(); } else d.setAttribute('open', ''); document.documentElement.classList.add('dlg-open'); }
    function closeDlg(d) { if (!d) return; if (d.close) { if (d.open) d.close(); } else d.removeAttribute('open'); document.documentElement.classList.remove('dlg-open'); if (returnTo && returnTo.focus) { try { returnTo.focus(); } catch (e) {} } }
    function backdropClose(d) { d.addEventListener('mousedown', function (e) { if (e.target === d) closeDlg(d); }); d.addEventListener('close', function () { document.documentElement.classList.remove('dlg-open'); }); }
    if (dlg) backdropClose(dlg);
    var qd = document.getElementById('quickDialog');
    if (qd) backdropClose(qd);

    function modeOf(a) { var m = a.getAttribute('data-auth-open'); if (m === 'login' || m === 'register') return m; var h = a.getAttribute('href') || ''; return /register/.test(h) ? 'register' : 'login'; }
    function nextOf(a) { var n = a && a.getAttribute('data-next'); if (n) return n; try { var u = new URL(a.href, location.href); return u.searchParams.get('next') || ''; } catch (e) { return ''; } }
    function load(mode, next) {
      if (!dlg || !body) return;
      body.innerHTML = '<p class="text-sm text-slate-500">' + (dlg.getAttribute('data-loading') || '…') + '</p>';
      fetch((dlg.getAttribute('data-modal-url') || '/account/modal/') + mode + '/' + (next ? '?next=' + encodeURIComponent(next) : ''), { headers: { 'X-W5D-Modal': '1' }, credentials: 'same-origin' })
        .then(function (r) { if (r.status === 204) { location.reload(); return null; } return r.text(); })
        .then(function (h) { if (h === null) return; body.innerHTML = h; wire(); var f = $('input:not([type=hidden]):not(.hidden)', body); if (f) f.focus(); })
        .catch(function () { body.innerHTML = '<p class="text-sm text-red-500">' + (dlg.getAttribute('data-error') || 'Error') + '</p>'; });
    }
    function showErr(form, errors) {
      $$('[data-err]', form.closest('[data-auth-pane]') || form).forEach(function (el) { el.textContent = ''; });
      $$('.field[aria-invalid]', form).forEach(function (el) { el.removeAttribute('aria-invalid'); });
      var top = $('[data-form-error]', body); if (top) { top.classList.add('hidden'); top.textContent = ''; }
      Object.keys(errors || {}).forEach(function (k) {
        var el = $('[data-err="' + k + '"]', body), inp = $('[name="' + k + '"]', form);
        if (el) { el.textContent = errors[k]; if (inp) inp.setAttribute('aria-invalid', 'true'); }
        else if (top) { top.textContent = errors[k]; top.classList.remove('hidden'); }
      });
      var first = $('[aria-invalid="true"]', form); if (first) first.focus();
    }
    function wire() {
      $$('[data-auth-open]', body).forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); load(modeOf(a), nextOf(a)); }); });
      var form = $('[data-auth-form]', body); if (!form) return;
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        showErr(form, {});
        var btn = $('[data-submit]', form), label = btn ? btn.textContent : '';
        if (btn) { btn.disabled = true; btn.textContent = dlg.getAttribute('data-sending') || '…'; }
        fetch(form.getAttribute('action'), { method: 'POST', body: new URLSearchParams(new FormData(form)), headers: { 'X-W5D-Modal': '1', Accept: 'application/json' }, credentials: 'same-origin' })
          .then(function (r) { return r.json().then(function (j) { return { s: r.status, j: j }; }); })
          .then(function (x) {
            if (btn) { btn.disabled = false; btn.textContent = label; }
            if (x.j && x.j.ok) {
              var loc = x.j.redirect || '';
              var ok = $('[data-form-ok]', body);
              if (/\/account\/login\//.test(loc) && !/\/account\/login\/\?next/.test(loc) || (x.j.message && /\/account\/login\//.test(loc))) {
                if (ok) { ok.textContent = x.j.message || ''; ok.classList.remove('hidden'); }
                var link = $('[data-auth-open="login"]', body); if (form.parentNode) form.parentNode.removeChild(form);
                if (!link) setTimeout(function () { load('login', ''); }, 1800);
                return;
              }
              if (ok) { ok.textContent = dlg.getAttribute('data-redirect') || ''; ok.classList.remove('hidden'); }
              location.assign(loc || location.href);
              return;
            }
            if (x.j && x.j.errors) { showErr(form, x.j.errors); return; }
            showErr(form, { _form: dlg.getAttribute('data-error') || 'Error' });
          })
          .catch(function () { if (btn) { btn.disabled = false; btn.textContent = label; } showErr(form, { _form: dlg.getAttribute('data-error') || 'Error' }); });
      });
    }
    // Upgrade every sign-in / register link that is not already inside the dialog; skip owner portal links and ctrl/cmd-clicks.
    $$('a[data-auth-open], a[href*="/account/login"], a[href*="/account/register"]').forEach(function (a) {
      if (!dlg || a.closest('#authDialog') || a.hasAttribute('data-no-modal')) return;
      a.addEventListener('click', function (e) {
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.button) return;
        e.preventDefault(); closeAsides(); openDlg(dlg); load(modeOf(a), nextOf(a));
      });
    });
    $$('[data-auth-close]').forEach(function (b) { b.addEventListener('click', function () { closeDlg(dlg); }); });
    if (dlg && /[?&]auth=(login|register)\b/.test(location.search)) { var m = /[?&]auth=(login|register)/.exec(location.search)[1]; openDlg(dlg); load(m, ''); }

    /* quick-find */
    var qi = qd && $('[data-quick-input]', qd), items = qd ? $$('[data-quick-item]', qd) : [], empty = qd && $('[data-quick-empty]', qd);
    function filter() {
      var q = (qi.value || '').trim().toLowerCase(), n = 0;
      items.forEach(function (li) { var on = !q || li.getAttribute('data-quick-item').indexOf(q) >= 0; li.classList.toggle('hidden', !on); if (on) n++; });
      if (empty) empty.classList.toggle('hidden', n > 0);
    }
    function openQuick() { if (!qd) return; closeAsides(); openDlg(qd); if (qi) { qi.value = ''; filter(); setTimeout(function () { qi.focus(); }, 30); } }
    if (qd) qd.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.preventDefault(); closeDlg(qd); } });
    if (qi) {
      qi.addEventListener('input', filter);
      qi.addEventListener('keydown', function (e) {
        var vis = items.filter(function (li) { return !li.classList.contains('hidden'); }), a;
        if (e.key === 'Enter' && vis.length) { a = $('a', vis[0]); if (a) location.assign(a.href); }
        if (e.key === 'ArrowDown' && vis.length) { e.preventDefault(); a = $('a', vis[0]); if (a) a.focus(); }
      });
      $('[data-quick-list]', qd).addEventListener('keydown', function (e) {
        var a = e.target.closest('a'); if (!a) return; var li = a.parentNode, vis = items.filter(function (x) { return !x.classList.contains('hidden'); }), i = vis.indexOf(li);
        if (e.key === 'ArrowDown' && vis[i + 1]) { e.preventDefault(); $('a', vis[i + 1]).focus(); }
        if (e.key === 'ArrowUp') { e.preventDefault(); if (i > 0) $('a', vis[i - 1]).focus(); else qi.focus(); }
      });
    }
    $$('[data-quick-open]').forEach(function (b) { b.addEventListener('click', openQuick); });
    $$('[data-quick-close]').forEach(function (b) { b.addEventListener('click', function () { closeDlg(qd); }); });
    document.addEventListener('keydown', function (e) {
      var t = e.target, typing = t && (/^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName) || t.isContentEditable);
      if ((e.key === 'k' || e.key === 'K') && (e.ctrlKey || e.metaKey)) { e.preventDefault(); openQuick(); }
      else if (e.key === '/' && !typing && !e.ctrlKey && !e.metaKey && !e.altKey) { e.preventDefault(); openQuick(); }
    });
  })();

  /* ---------- CRUD: confirm before destructive submits ---------- */
  $$('form[data-confirm]').forEach(function (f) { f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault(); }); });

  /* owner/staff panel: phone drawer */
  (function () {
    var nav = document.getElementById('adminNav'), tg = $('[data-admin-nav-toggle]');
    if (!nav || !tg) return;
    function set(on) {
      nav.classList.toggle('is-open', on); nav.setAttribute('aria-hidden', on ? 'false' : 'true');
      tg.setAttribute('aria-expanded', on ? 'true' : 'false'); document.body.classList.toggle('admin-nav-open', on);
      if (on) { var a = nav.querySelector('a[aria-current]') || nav.querySelector('nav a'); a && a.focus({ preventScroll: true }); } else if (document.activeElement && nav.contains(document.activeElement)) tg.focus();
    }
    tg.addEventListener('click', function () { set(!nav.classList.contains('is-open')); });
    $$('[data-admin-nav-close]').forEach(function (b) { b.addEventListener('click', function () { set(false); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && nav.classList.contains('is-open')) set(false); });
    nav.addEventListener('click', function (e) { if (e.target.closest('nav a')) set(false); });
    window.addEventListener('resize', function () { if (window.innerWidth >= 1024) set(false); });
  })();
})();

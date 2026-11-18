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

/* forms.js — smart, dependency-free inputs (CSP: external file, no inline handlers).
 * Every enhancement is progressive: without JS the plain <input> keeps working and the server validates again.
 *  - phone  : input[type=tel] -> country-code picker + national number, E.164 value, country auto-detected
 *  - email  : trim/lowercase + "did you mean gmail.com?" hint
 *  - draft  : form[data-draft] is auto-saved locally (never passwords, codes, keys, references) and offered back
 *  - password: show/hide + strength meter (new-password fields)
 *  - number : +/- steppers (input[data-stepper]);  date chips (input[data-date-chips]);  data-format="upper|words|digits"
 */
(function () {
  'use strict';
  var doc = document, lang = (doc.documentElement.lang || 'en').slice(0, 2);
  var $ = function (s, r) { return (r || doc).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || doc).querySelectorAll(s)); };
  var T = {
    en: { bad: 'Enter a valid phone number for this country.', dym: 'Did you mean', show: 'Show', hide: 'Hide', restored: 'We restored what you had typed.', clear: 'Start over', country: 'Country code', weak: 'Weak', fair: 'Fair', good: 'Good', strong: 'Strong', plus: 'Increase', minus: 'Decrease', today: 'Today', tomorrow: 'Tomorrow', monday: 'Next Monday', next: 'Next month' },
    fr: { bad: 'Saisissez un numéro valide pour ce pays.', dym: 'Vouliez-vous dire', show: 'Afficher', hide: 'Masquer', restored: 'Nous avons restauré votre saisie.', clear: 'Recommencer', country: 'Indicatif du pays', weak: 'Faible', fair: 'Moyen', good: 'Bon', strong: 'Fort', plus: 'Augmenter', minus: 'Diminuer', today: 'Aujourd’hui', tomorrow: 'Demain', monday: 'Lundi prochain', next: 'Mois prochain' },
    ar: { bad: 'أدخل رقم هاتف صالحا لهذه الدولة.', dym: 'هل تقصد', show: 'إظهار', hide: 'إخفاء', restored: 'استعدنا ما كتبته.', clear: 'ابدأ من جديد', country: 'رمز الدولة', weak: 'ضعيفة', fair: 'متوسطة', good: 'جيدة', strong: 'قوية', plus: 'زيادة', minus: 'إنقاص', today: 'اليوم', tomorrow: 'غدا', monday: 'الاثنين القادم', next: 'الشهر القادم' }
  };
  var L = T[lang] || T.en;

  /* ---------- phone ---------- */
  // iso, dial code, min/max national digits, grouping
  var C = [['TN', '216', 8, 8, [2, 3, 3]], ['FR', '33', 9, 9, [1, 2, 2, 2, 2]], ['DZ', '213', 9, 9, [3, 2, 2, 2]], ['MA', '212', 9, 9, [3, 2, 2, 2]], ['LY', '218', 9, 10, [2, 3, 4]], ['EG', '20', 10, 10, [3, 3, 4]],
    ['SA', '966', 9, 9, [2, 3, 4]], ['AE', '971', 9, 9, [2, 3, 4]], ['QA', '974', 8, 8, [4, 4]], ['KW', '965', 8, 8, [4, 4]], ['BH', '973', 8, 8, [4, 4]], ['OM', '968', 8, 8, [4, 4]], ['JO', '962', 9, 9, [1, 4, 4]],
    ['LB', '961', 7, 8, [2, 3, 3]], ['TR', '90', 10, 10, [3, 3, 4]], ['DE', '49', 10, 11, [3, 4, 4]], ['IT', '39', 9, 10, [3, 3, 4]], ['ES', '34', 9, 9, [3, 3, 3]], ['PT', '351', 9, 9, [3, 3, 3]], ['BE', '32', 8, 9, [3, 2, 2, 2]],
    ['NL', '31', 9, 9, [1, 4, 4]], ['CH', '41', 9, 9, [2, 3, 2, 2]], ['SE', '46', 9, 9, [2, 3, 2, 2]], ['GB', '44', 10, 10, [4, 6]], ['IE', '353', 8, 9, [2, 3, 4]], ['US', '1', 10, 10, [3, 3, 4]], ['CA', '1', 10, 10, [3, 3, 4]],
    ['SN', '221', 9, 9, [2, 3, 2, 2]], ['CI', '225', 10, 10, [2, 2, 2, 2, 2]], ['ML', '223', 8, 8, [2, 2, 2, 2]], ['MR', '222', 8, 8, [2, 2, 2, 2]], ['SD', '249', 9, 9, [2, 3, 4]], ['PS', '970', 9, 9, [3, 3, 3]], ['IQ', '964', 10, 10, [3, 3, 4]], ['IN', '91', 10, 10, [5, 5]], ['CN', '86', 11, 11, [3, 4, 4]], ['BR', '55', 10, 11, [2, 5, 4]]];
  var byIso = {}; C.forEach(function (c) { byIso[c[0]] = c; });
  var TZ = { 'Africa/Tunis': 'TN', 'Europe/Paris': 'FR', 'Africa/Algiers': 'DZ', 'Africa/Casablanca': 'MA', 'Africa/Tripoli': 'LY', 'Africa/Cairo': 'EG', 'Asia/Riyadh': 'SA', 'Asia/Dubai': 'AE', 'Asia/Qatar': 'QA', 'Asia/Kuwait': 'KW', 'Asia/Bahrain': 'BH', 'Asia/Muscat': 'OM', 'Asia/Amman': 'JO', 'Asia/Beirut': 'LB', 'Europe/Istanbul': 'TR', 'Europe/Berlin': 'DE', 'Europe/Rome': 'IT', 'Europe/Madrid': 'ES', 'Europe/Lisbon': 'PT', 'Europe/Brussels': 'BE', 'Europe/Amsterdam': 'NL', 'Europe/Zurich': 'CH', 'Europe/Stockholm': 'SE', 'Europe/London': 'GB', 'Europe/Dublin': 'IE', 'America/New_York': 'US', 'America/Chicago': 'US', 'America/Los_Angeles': 'US', 'America/Toronto': 'CA', 'Africa/Dakar': 'SN', 'Africa/Abidjan': 'CI', 'Africa/Khartoum': 'SD', 'Asia/Baghdad': 'IQ', 'Asia/Kolkata': 'IN', 'Asia/Shanghai': 'CN', 'America/Sao_Paulo': 'BR' };
  function detectIso(def) {
    var tz = ''; try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}
    if (TZ[tz]) return TZ[tz];
    var loc = (navigator.languages && navigator.languages[0]) || navigator.language || '', m = loc.match(/[-_]([A-Za-z]{2})$/);
    if (m && byIso[m[1].toUpperCase()]) return m[1].toUpperCase();
    return byIso[def] ? def : 'TN';
  }
  function byDial(digits) { // longest dial-code prefix; +1 prefers US
    for (var n = 3; n >= 1; n--) { var p = digits.slice(0, n); for (var i = 0; i < C.length; i++) if (C[i][1] === p) return C[i]; }
    return null;
  }
  function regionName(iso) { try { return new Intl.DisplayNames([lang], { type: 'region' }).of(iso) || iso; } catch (e) { return iso; } }
  function group(d, pat) { var out = [], i = 0; for (var k = 0; k < pat.length && i < d.length; k++) { out.push(d.substr(i, pat[k])); i += pat[k]; } if (i < d.length) out.push(d.slice(i)); return out.join(' '); }

  function phone(src) {
    if (src.getAttribute('data-phone-ready')) return;
    src.setAttribute('data-phone-ready', '1');
    var def = src.getAttribute('data-phone-default') || 'TN';
    var wrap = doc.createElement('div'); wrap.className = 'ph-wrap';
    var sel = doc.createElement('select'); sel.className = 'field ph-cc'; sel.setAttribute('aria-label', L.country); sel.setAttribute('autocomplete', 'off');
    C.slice().sort(function (a, b) { return a[0] === def ? -1 : b[0] === def ? 1 : regionName(a[0]).localeCompare(regionName(b[0]), lang); }).forEach(function (c) {
      var o = doc.createElement('option'); o.value = c[0]; o.textContent = c[0] + ' +' + c[1] + ' · ' + regionName(c[0]); o.setAttribute('data-short', c[0] + ' +' + c[1]); sel.appendChild(o);
    });
    var vis = doc.createElement('input'); vis.type = 'tel'; vis.className = 'field ph-num'; vis.id = src.id; vis.setAttribute('inputmode', 'tel'); vis.setAttribute('autocomplete', 'tel-national'); vis.setAttribute('dir', 'ltr');
    if (src.required) vis.required = true;
    if (src.readOnly) { vis.readOnly = true; sel.disabled = true; }
    src.removeAttribute('id'); src.removeAttribute('required'); src.type = 'hidden';
    src.parentNode.insertBefore(wrap, src); wrap.appendChild(sel); wrap.appendChild(vis); wrap.appendChild(src);
    var cur = byIso[detectIso(def)];
    function digits(s) { return (s || '').replace(/\D+/g, ''); }
    function sync() {
      var d = digits(vis.value);
      if (d.length) src.value = '+' + cur[1] + d; else src.value = '';
      var ok = !d.length || (d.length >= cur[2] && d.length <= cur[3]);
      vis.setCustomValidity(ok ? '' : L.bad); vis.setAttribute('aria-invalid', ok ? 'false' : 'true');
      src.dispatchEvent(new Event('change', { bubbles: true }));
    }
    function setCountry(c) { cur = c; sel.value = c[0]; }
    function take(raw) { // a typed/pasted number: + or 00 prefix selects the country, leading trunk 0 is dropped
      var s = (raw || '').trim(), intl = /^(\+|00)/.test(s), d = digits(s);
      if (intl) { d = s.charAt(0) === '+' ? d : d.slice(2); var c = byDial(d); if (c) { setCountry(c); d = d.slice(c[1].length); } }
      else d = d.replace(/^0+/, '');
      d = d.slice(0, cur[3]); vis.value = group(d, cur[4]); sync();
    }
    var initial = src.value || src.getAttribute('value') || '';
    if (initial) take(initial); else setCountry(cur);
    sel.addEventListener('change', function () { cur = byIso[sel.value]; take(vis.value); });
    vis.addEventListener('input', function () { take(vis.value); });
    vis.addEventListener('paste', function (e) { var t = (e.clipboardData || window.clipboardData); if (!t) return; e.preventDefault(); take(t.getData('text')); });
    src.addEventListener('w5d:set', function () { take(src.value); });
  }

  /* ---------- email ---------- */
  var DOM = ['gmail.com', 'yahoo.com', 'yahoo.fr', 'hotmail.com', 'hotmail.fr', 'outlook.com', 'outlook.fr', 'live.com', 'icloud.com', 'topnet.tn', 'planet.tn', 'gnet.tn', 'w5d.tech'];
  function lev(a, b) { var m = [], i, j; for (i = 0; i <= a.length; i++) m[i] = [i]; for (j = 0; j <= b.length; j++) m[0][j] = j; for (i = 1; i <= a.length; i++) for (j = 1; j <= b.length; j++) m[i][j] = Math.min(m[i - 1][j] + 1, m[i][j - 1] + 1, m[i - 1][j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1)); return m[a.length][b.length]; }
  function email(inp) {
    if (inp.getAttribute('data-email-ready') || inp.readOnly) return;
    inp.setAttribute('data-email-ready', '1');
    inp.setAttribute('autocapitalize', 'off'); inp.setAttribute('spellcheck', 'false');
    var hint = doc.createElement('p'); hint.className = 'fld-hint'; hint.hidden = true; hint.setAttribute('role', 'status'); inp.parentNode.appendChild(hint);
    inp.addEventListener('blur', function () {
      inp.value = inp.value.trim().toLowerCase(); hint.hidden = true;
      var at = inp.value.lastIndexOf('@'); if (at < 1) return;
      var d = inp.value.slice(at + 1), best = null, bd = 3;
      if (!d || DOM.indexOf(d) >= 0) return;
      DOM.forEach(function (x) { var k = lev(d, x); if (k < bd && k > 0) { bd = k; best = x; } });
      if (best && bd <= 2) {
        var fix = inp.value.slice(0, at + 1) + best;
        hint.textContent = L.dym + ' '; var b = doc.createElement('button'); b.type = 'button'; b.className = 'btn-link'; b.textContent = fix + ' ?'; hint.appendChild(b); hint.hidden = false;
        b.addEventListener('click', function () { inp.value = fix; hint.hidden = true; inp.dispatchEvent(new Event('input', { bubbles: true })); inp.focus(); });
      }
    });
  }

  /* ---------- password ---------- */
  function score(p) { var s = 0; if (p.length >= 10) s++; if (p.length >= 14) s++; if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s++; if (/\d/.test(p)) s++; if (/[^A-Za-z0-9]/.test(p)) s++; return Math.min(4, Math.max(p.length ? 1 : 0, s - (p.length < 8 ? 1 : 0))); }
  function password(inp) {
    if (inp.getAttribute('data-pw-ready')) return; inp.setAttribute('data-pw-ready', '1');
    var wrap = doc.createElement('div'); wrap.className = 'pw-wrap'; inp.parentNode.insertBefore(wrap, inp); wrap.appendChild(inp);
    var b = doc.createElement('button'); b.type = 'button'; b.className = 'pw-toggle'; b.textContent = L.show; b.setAttribute('aria-pressed', 'false'); wrap.appendChild(b);
    b.addEventListener('click', function () { var show = inp.type === 'password'; inp.type = show ? 'text' : 'password'; b.textContent = show ? L.hide : L.show; b.setAttribute('aria-pressed', show ? 'true' : 'false'); });
    if ((inp.getAttribute('autocomplete') || '') === 'new-password') {
      var m = doc.createElement('div'); m.className = 'pw-meter'; m.setAttribute('aria-live', 'polite'); m.innerHTML = '<i></i><i></i><i></i><i></i><span></span>'; wrap.parentNode.insertBefore(m, wrap.nextSibling);
      var names = [, L.weak, L.fair, L.good, L.strong];
      inp.addEventListener('input', function () { var s = score(inp.value); m.setAttribute('data-s', s); m.lastChild.textContent = s ? names[s] : ''; });
    }
  }

  /* ---------- number stepper / date chips / formatting ---------- */
  function stepper(inp) {
    if (inp.getAttribute('data-step-ready')) return; inp.setAttribute('data-step-ready', '1');
    var w = doc.createElement('div'); w.className = 'stp-wrap'; inp.parentNode.insertBefore(w, inp);
    var mk = function (t, label, d) { var b = doc.createElement('button'); b.type = 'button'; b.className = 'stp-btn'; b.textContent = t; b.setAttribute('aria-label', label); b.addEventListener('click', function () { if (inp.readOnly) return; var v = parseFloat(inp.value || inp.min || '0') + d * (parseFloat(inp.step) || 1), lo = inp.min !== '' ? parseFloat(inp.min) : -Infinity, hi = inp.max !== '' ? parseFloat(inp.max) : Infinity; inp.value = Math.max(lo, Math.min(hi, v)); inp.dispatchEvent(new Event('input', { bubbles: true })); }); return b; };
    w.appendChild(mk('−', L.minus, -1)); w.appendChild(inp); w.appendChild(mk('+', L.plus, 1));
  }
  function ymd(d) { var z = function (n) { return (n < 10 ? '0' : '') + n; }; return d.getFullYear() + '-' + z(d.getMonth() + 1) + '-' + z(d.getDate()); }
  function dateChips(inp) {
    if (inp.getAttribute('data-chips-ready')) return; inp.setAttribute('data-chips-ready', '1');
    var box = doc.createElement('div'); box.className = 'chips'; var t = new Date(); t.setHours(12, 0, 0, 0);
    var mon = new Date(t); mon.setDate(t.getDate() + ((8 - t.getDay()) % 7 || 7)); var nm = new Date(t.getFullYear(), t.getMonth() + 1, 1, 12);
    [[L.today, t], [L.tomorrow, new Date(t.getTime() + 864e5)], [L.monday, mon], [L.next, nm]].forEach(function (x) {
      if (inp.min && ymd(x[1]) < inp.min) return;
      var b = doc.createElement('button'); b.type = 'button'; b.className = 'chip'; b.textContent = x[0]; b.addEventListener('click', function () { inp.value = ymd(x[1]); inp.dispatchEvent(new Event('input', { bubbles: true })); mark(); }); box.appendChild(b);
    });
    function mark() { $$('.chip', box).forEach(function (b, i) { b.setAttribute('aria-pressed', 'false'); }); }
    inp.parentNode.appendChild(box);
  }
  function fmt(inp) {
    var k = inp.getAttribute('data-format');
    inp.addEventListener('input', function () {
      var v = inp.value, s = inp.selectionStart;
      if (k === 'upper') v = v.toUpperCase().replace(/\s+/g, '');
      else if (k === 'digits') v = v.replace(/\D+/g, '');
      else if (k === 'words') v = v.replace(/(^|[\s'’-])(\p{L})/gu, function (m, a, b) { return a + b.toUpperCase(); });
      if (v !== inp.value) { inp.value = v; try { inp.setSelectionRange(s, s); } catch (e) {} }
    });
  }

  /* ---------- drafts ---------- */
  var SKIP = /pass|token|csrf|key|txn|code|secret|iban|card|captcha|website|^ref$|_ref$|^current$/i;
  function draft(form) {
    if (form.getAttribute('data-draft-ready')) return; form.setAttribute('data-draft-ready', '1');
    var key = 'w5d-draft:' + location.pathname + ':' + (form.getAttribute('data-draft') || form.id || 'f'), ttl = 7 * 864e5, store;
    try { store = window.localStorage; store.getItem(key); } catch (e) { return; }
    var fields = function () { return $$('input,select,textarea', form).filter(function (f) { return f.name && !SKIP.test(f.name) && ['password', 'file', 'submit', 'button', 'hidden'].indexOf(f.type) < 0 || (f.type === 'hidden' && f.hasAttribute('data-phone-ready') && !SKIP.test(f.name)); }); };
    function save() {
      var o = {}; fields().forEach(function (f) { if (f.type === 'checkbox') o[f.name] = f.checked ? '1' : ''; else if (f.type === 'radio') { if (f.checked) o[f.name] = f.value; } else o[f.name] = f.value; });
      try { store.setItem(key, JSON.stringify({ t: Date.now(), v: o, s: form.getAttribute('data-step') || '' })); } catch (e) {}
    }
    var raw = null; try { raw = JSON.parse(store.getItem(key) || 'null'); } catch (e) {}
    if (raw && Date.now() - raw.t < ttl && raw.v) {
      var restored = 0;
      fields().forEach(function (f) {
        var v = raw.v[f.name]; if (v === undefined) return;
        if (f.type === 'checkbox') { if (!f.checked && v === '1') { f.checked = true; restored++; } return; }
        if (f.type === 'radio') { if (f.value === v && !f.checked && !$$('input[name="' + f.name + '"]:checked', form).length) { f.checked = true; restored++; } return; }
        if (f.readOnly || (f.value && f.value !== f.defaultValue) || (f.value && f.type !== 'select-one')) return;
        if (f.type === 'select-one') { if (f.selectedIndex > 0 || !v) return; }
        f.value = v; restored++;
        f.dispatchEvent(new Event(f.hasAttribute('data-phone-ready') ? 'w5d:set' : 'input', { bubbles: true }));
      });
      if (restored) {
        var bar = doc.createElement('p'); bar.className = 'draft-bar'; bar.setAttribute('role', 'status'); bar.textContent = L.restored + ' ';
        var b = doc.createElement('button'); b.type = 'button'; b.className = 'btn-link'; b.textContent = L.clear; bar.appendChild(b); form.insertBefore(bar, form.firstChild);
        b.addEventListener('click', function () { try { store.removeItem(key); } catch (e) {} location.reload(); });
      }
    }
    var t; form.addEventListener('input', function () { clearTimeout(t); t = setTimeout(save, 400); }); form.addEventListener('change', function () { clearTimeout(t); t = setTimeout(save, 400); });
    form.addEventListener('submit', function () { try { store.removeItem(key); } catch (e) {} });
  }

  /* ---------- image tool: drag-drop zone, size presets, click-to-select address fields (owner image library) ---------- */
  function dropzone(zone) {
    var input = $('[data-drop-input]', zone), list = $('[data-drop-list]', zone);
    if (!input) return;
    var show = function () {
      var f = input.files || [], n = f.length, kb = 0, i;
      for (i = 0; i < n; i++) kb += f[i].size;
      list.textContent = n ? n + ' · ' + (kb > 1048576 ? (kb / 1048576).toFixed(1) + ' MB' : Math.round(kb / 1024) + ' KB') + (n <= 4 ? ' · ' + Array.prototype.map.call(f, function (x) { return x.name; }).join(', ') : '') : '';
      zone.classList.toggle('has-files', !!n);
    };
    input.addEventListener('change', show);
    ['dragenter', 'dragover'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('is-over'); }); });
    ['dragleave', 'dragend', 'drop'].forEach(function (ev) { zone.addEventListener(ev, function () { zone.classList.remove('is-over'); }); });
    zone.addEventListener('drop', function (e) {
      e.preventDefault();
      var dt = e.dataTransfer; if (!dt || !dt.files || !dt.files.length) return;
      try { input.files = dt.files; } catch (x) { return; }   // very old browsers: the click-to-choose path still works
      show();
    });
    zone.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
  }
  $$('[data-dropzone]').forEach(dropzone);
  $$('[data-preset]').forEach(function (b) {
    b.addEventListener('click', function () {
      var p = b.getAttribute('data-preset').split('x'), f = b.form || b.closest('form'); if (!f) return;
      var w = $('[data-size=w]', f), h = $('[data-size=h]', f); if (w) w.value = p[0]; if (h) h.value = p[1];
    });
  });
  $$('input[data-select-all]').forEach(function (i) { i.addEventListener('focus', function () { i.select(); }); });

  function init(root) {
    $$('input[type=tel][data-phone]', root).forEach(phone);
    $$('input[type=email]', root).forEach(email);
    $$('input[type=password]', root).forEach(password);
    $$('input[data-stepper]', root).forEach(stepper);
    $$('input[data-date-chips]', root).forEach(dateChips);
    $$('input[data-format]', root).forEach(fmt);
    $$('form[data-draft]', root).forEach(draft);
  }
  init(doc);
  doc.addEventListener('w5d:enhance', function (e) { init(e.target || doc); });
})();

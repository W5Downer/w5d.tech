/* Runs in <head> (blocking, small) to avoid a theme flash and to dismiss the SVG page loader. External file => CSP script-src 'self'. */
(function () {
  var d = document.documentElement, t = null, t0 = Date.now(), done = false;
  d.classList.add('js');
  /* The SVG loader is a first-impression nicety. Once it has been seen in this tab it is skipped, so navigating never costs time. */
  try { if (sessionStorage.getItem('w5d-loader-seen') === '1') d.classList.add('no-loader'); else sessionStorage.setItem('w5d-loader-seen', '1'); } catch (e) {}
  try { if (localStorage.getItem('w5d-motion') === 'calm') d.setAttribute('data-motion', 'calm'); } catch (e) {} /* animations are ON by default; only the visitor's own switch makes them calm */
  try { t = localStorage.getItem('w5d-theme'); } catch (e) {}
  if (t === 'light') d.classList.remove('dark'); else d.classList.add('dark');

  function hide() {
    if (done) return;
    var l = document.getElementById('w5d-loader');
    if (!l) { if (document.readyState === 'loading') return; done = true; return; }
    done = true;
    var wait = d.classList.contains('no-loader') ? 0 : Math.max(0, 300 - (Date.now() - t0)); // first visit only: avoid a flash (~0.3 s); repeat visits: instant
    setTimeout(function () { l.classList.add('is-hidden'); }, wait);
    setTimeout(function () { if (l.parentNode) l.parentNode.removeChild(l); }, wait + 700);
  }
  window.addEventListener('load', hide);
  document.addEventListener('DOMContentLoaded', function () { if (document.readyState === 'complete') hide(); });
  window.addEventListener('pageshow', function (e) { if (e.persisted) { var l = document.getElementById('w5d-loader'); if (l && l.parentNode) l.parentNode.removeChild(l); } });
  setTimeout(hide, 5000); // failsafe: a slow asset must never trap the visitor behind the loader
})();

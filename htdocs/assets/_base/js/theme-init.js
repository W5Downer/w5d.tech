/* Runs in <head> (blocking, small) to avoid a theme flash and to dismiss the SVG page loader. External file => CSP script-src 'self'. */
(function () {
  var d = document.documentElement, t = null, t0 = Date.now(), done = false;
  d.classList.add('js');
  try { if (localStorage.getItem('w5d-motion') === 'full') d.setAttribute('data-motion', 'full'); } catch (e) {}
  try { t = localStorage.getItem('w5d-theme'); } catch (e) {}
  if (t === 'light') d.classList.remove('dark'); else d.classList.add('dark');

  function hide() {
    if (done) return;
    var l = document.getElementById('w5d-loader');
    if (!l) { if (document.readyState === 'loading') return; done = true; return; }
    done = true;
    var wait = Math.max(0, 450 - (Date.now() - t0)); // never a flash: at least ~0.45 s on screen
    setTimeout(function () { l.classList.add('is-hidden'); }, wait);
    setTimeout(function () { if (l.parentNode) l.parentNode.removeChild(l); }, wait + 700);
  }
  window.addEventListener('load', hide);
  document.addEventListener('DOMContentLoaded', function () { if (document.readyState === 'complete') hide(); });
  window.addEventListener('pageshow', function (e) { if (e.persisted) { var l = document.getElementById('w5d-loader'); if (l && l.parentNode) l.parentNode.removeChild(l); } });
  setTimeout(hide, 5000); // failsafe: a slow asset must never trap the visitor behind the loader
})();

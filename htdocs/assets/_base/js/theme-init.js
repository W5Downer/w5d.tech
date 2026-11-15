/* Runs in <head> (blocking, 1 KB) to avoid a theme flash. External file => compatible with CSP script-src 'self'. */
(function () {
  var d = document.documentElement, t = null;
  d.classList.add('js');
  try { t = localStorage.getItem('w5d-theme'); } catch (e) {}
  if (t === 'light') d.classList.remove('dark'); else d.classList.add('dark');
})();

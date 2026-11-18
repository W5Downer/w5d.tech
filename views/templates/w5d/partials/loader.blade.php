{{-- Animated SVG page loader (from the static w5d template). Shown only when JS is on (html.js); removed by theme-init.js on load. --}}
<div id="w5d-loader" role="status" aria-live="polite" aria-label="{{ tr3('Loading', 'Chargement', 'جارٍ التحميل') }}">
  <div class="w5l-inner">
    <svg class="w5l-svg" viewBox="0 0 160 160" width="132" height="132" aria-hidden="true" focusable="false">
      <defs>
        <linearGradient id="w5lg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#7c3aed"/><stop offset=".55" stop-color="#6366f1"/><stop offset="1" stop-color="#06b6d4"/></linearGradient>
        <linearGradient id="w5lr" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#7c3aed"/><stop offset="1" stop-color="#06b6d4"/></linearGradient>
      </defs>
      <circle cx="80" cy="80" r="66" fill="none" stroke="rgba(148,163,184,.18)" stroke-width="2"/>
      <circle class="w5l-ring" cx="80" cy="80" r="66" fill="none" stroke="url(#w5lr)" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="90 330"/>
      <g class="w5l-orbit">
        <circle cx="80" cy="14" r="5" fill="#a78bfa"/><circle cx="142.8" cy="59.6" r="5" fill="#818cf8"/><circle cx="118.8" cy="133.4" r="5" fill="#38bdf8"/><circle cx="41.2" cy="133.4" r="5" fill="#22d3ee"/><circle cx="17.2" cy="59.6" r="5" fill="#c4b5fd"/>
      </g>
      <g class="w5l-mark"><rect x="46" y="46" width="68" height="68" rx="18" fill="url(#w5lg)"/><text x="80" y="91" text-anchor="middle" font-family="Space Grotesk,Inter,system-ui,sans-serif" font-weight="800" font-size="28" fill="#fff">W5</text></g>
    </svg>
    <span class="w5l-text">W5D</span>
    <span class="w5l-bar"></span>
  </div>
  <noscript><style>#w5d-loader{display:none!important}</style></noscript>
</div>

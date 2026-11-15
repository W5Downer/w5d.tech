@php
  $nav = \W5D\Content::get('nav', 'main') ?? ['items' => []];
  $macros = \W5D\Content::all('macros');
  $here = $canonicalPath ?? '/';
@endphp
<header class="fixed top-0 inset-x-0 z-50">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-3">
    <nav class="glass rounded-2xl flex items-center justify-between px-4 sm:px-6 py-3" aria-label="Primary">
      <a href="{{ url('/') }}" class="flex items-center gap-2" aria-label="W5D">
        <span class="relative inline-flex w-9 h-9 rounded-xl bg-gradient-to-br from-brand-500 via-indigo-500 to-cyan-400 items-center justify-center shadow-lg shadow-brand-500/30">
          <span class="font-display font-bold text-white text-sm">W5</span>
        </span>
        <span class="font-display font-bold text-lg tracking-tight text-slate-900 dark:text-white">W5D</span>
      </a>

      <ul data-mega-host class="hidden lg:flex items-center gap-7 text-sm font-medium">
        @foreach($nav['items'] as $item)
          <li class="relative" data-mega-trigger @if(!empty($item['active_prefix']) && str_starts_with($here, $item['active_prefix'])) data-active @endif>
            <button type="button" class="inline-flex items-center gap-1 py-2 hover:text-brand-500 transition" aria-expanded="false" aria-haspopup="true">
              {{ t($item['label']) }} {{ icon('chevron-down', 'w-4 h-4 opacity-60') }}
            </button>
            <div data-mega-panel class="hidden absolute top-full pt-3 {{ lang() === 'ar' ? 'right-0' : 'left-0' }} z-50">
              <div class="glass rounded-2xl shadow-2xl p-5 grid gap-6 {{ !empty($item['promo']) ? 'grid-cols-[1fr_1fr_16rem]' : 'grid-cols-2' }} min-w-[34rem]">
                @foreach($item['groups'] as $g)
                  <div>
                    <p class="text-[0.68rem] uppercase tracking-[0.18em] text-slate-500 mb-2 font-semibold">{{ t($g['heading']) }}</p>
                    <ul class="space-y-1">
                      @php
                        $links = $g['items'] ?? [];
                        if (($g['each'] ?? '') === 'macros') {
                          $links = [];
                          foreach ($macros as $m) $links[] = ['icon' => $m['icon'], 'label' => $m['name'], 'desc' => $m['tagline'], 'href' => '/macro-works/' . $m['slug'] . '/'];
                        }
                      @endphp
                      @foreach($links as $l)
                        <li>
                          <a href="{{ link_to($l['href']) }}" @if(rtrim($here, '/') === rtrim($l['href'], '/')) data-active-link @endif class="flex items-start gap-3 rounded-xl px-3 py-2 hover:bg-brand-500/10 transition">
                            <span class="mt-0.5 text-brand-500">{{ icon($l['icon'], 'w-5 h-5') }}</span>
                            <span><span class="block font-semibold text-slate-900 dark:text-white">{{ t($l['label']) }}</span>
                            <span class="block text-xs text-slate-500">{{ t($l['desc']) }}</span></span>
                          </a>
                        </li>
                      @endforeach
                    </ul>
                  </div>
                @endforeach
                @if(!empty($item['promo']))
                  <a href="{{ link_to($item['promo']['href']) }}" class="rounded-xl p-4 text-white on-grad block" style="--grad: var(--g-{{ $item['promo']['gradient'] ?? 'technology' }})">
                    <span class="tier-badge">{{ t($item['promo']['tag']) }}</span>
                    <span class="block font-display font-bold text-lg mt-2">{{ t($item['promo']['title']) }}</span>
                    <span class="block text-sm mt-1 opacity-90">{{ t($item['promo']['body']) }}</span>
                  </a>
                @endif
              </div>
            </div>
          </li>
        @endforeach
      </ul>

      <div class="flex items-center gap-2">
        <button type="button" data-aside-open="aside-lang" class="btn-ghost rounded-lg h-10 px-3 inline-flex items-center gap-1.5 text-sm" aria-label="{{ __('nav.language') }}">
          {{ icon('languages', 'w-5 h-5') }}<span class="uppercase text-xs font-bold">{{ lang() }}</span>
        </button>
        <button type="button" data-theme-toggle class="btn-ghost rounded-lg w-10 h-10 inline-flex items-center justify-center" aria-label="{{ __('nav.theme') }}">
          <span data-theme-icon="sun">{{ icon('sun', 'w-5 h-5') }}</span>
          <span data-theme-icon="moon" class="hidden">{{ icon('moon', 'w-5 h-5') }}</span>
        </button>
        <a href="{{ url('/checkout/') }}" class="hidden sm:inline-flex btn-focus text-sm !py-2 !px-4">{{ __('cta.invest') }} {{ icon('arrow-right', 'w-4 h-4 rtl-flip') }}</a>
        <button type="button" data-mobile-toggle class="lg:hidden btn-ghost rounded-lg w-10 h-10 inline-flex items-center justify-center" aria-label="{{ __('nav.menu') }}" aria-expanded="false" aria-controls="mobileMenu">{{ icon('menu', 'w-5 h-5') }}</button>
      </div>
    </nav>

    <div id="mobileMenu" class="hidden lg:hidden glass rounded-2xl mt-2 p-4 max-h-[75vh] overflow-y-auto">
      @foreach($nav['items'] as $item)
        <p class="text-[0.68rem] uppercase tracking-[0.18em] text-slate-500 mt-3 mb-1 font-semibold">{{ t($item['label']) }}</p>
        @foreach($item['groups'] as $g)
          @php
            $links = $g['items'] ?? [];
            if (($g['each'] ?? '') === 'macros') { $links = []; foreach ($macros as $m) $links[] = ['icon' => $m['icon'], 'label' => $m['name'], 'href' => '/macro-works/' . $m['slug'] . '/']; }
          @endphp
          @foreach($links as $l)
            <a href="{{ link_to($l['href']) }}" class="flex items-center gap-2 py-1.5 text-sm">{{ icon($l['icon'], 'w-4 h-4 text-brand-500') }} {{ t($l['label']) }}</a>
          @endforeach
        @endforeach
      @endforeach
      <a href="{{ url('/checkout/') }}" class="btn-focus mt-4 w-full">{{ __('cta.invest') }}</a>
    </div>
  </div>
</header>

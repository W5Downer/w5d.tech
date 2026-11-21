@php
  $nav = \W5D\Content::get('nav', 'main') ?? ['items' => []];
  $macros = \W5D\Content::all('macros');
  $here = $canonicalPath ?? '/';
  $hereN = rtrim($here, '/') ?: '/';
  // Longest-match wins: exact link hit => aria-current="page"; a section/prefix hit marks the parent item.
  $navBest = -1; $navBestLen = 0; $navExact = null;
  foreach ($nav['items'] as $ni => $nitem) {
    $cands = [];
    if (!empty($nitem['active_prefix'])) $cands[] = [rtrim($nitem['active_prefix'], '/'), false];
    foreach ($nitem['groups'] ?? [] as $ng) {
      foreach ($ng['items'] ?? [] as $nl) { $cands[] = [rtrim($nl['href'], '/') ?: '/', true]; }
      if (($ng['each'] ?? '') === 'macros') $cands[] = ['/macro-works', false];
    }
    foreach ($cands as [$cp, $isLink]) {
      if ($cp === '' || $cp === '/') continue;
      $hit = $hereN === $cp || str_starts_with($hereN . '/', $cp . '/');
      if ($hit && (strlen($cp) > $navBestLen || ($hereN === $cp && $navExact === null && strlen($cp) >= $navBestLen))) {
        $navBest = $ni; $navBestLen = strlen($cp); if ($hereN === $cp) $navExact = $ni;
      }
    }
  }
  if ($navExact !== null) $navBest = $navExact;
  $hc = \W5D\Cta::for($here);
  $navLinkOn = function (string $href) use ($hereN, $navBest, $navBestLen): string {
    $h = rtrim($href, '/') ?: '/';
    return $hereN === $h ? 'page' : ($h !== '/' && str_starts_with($hereN . '/', $h . '/') ? 'true' : '');
  };
@endphp
<header class="fixed top-0 inset-x-0 z-50">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-3">
    <nav class="nav-solid rounded-2xl flex items-center justify-between px-3 min-[400px]:px-4 sm:px-6 py-3" aria-label="Primary">
      <a href="{{ url('/') }}" class="flex items-center gap-2" aria-label="W5D">
        <img src="{{ asset('images/logo.svg') }}" alt="" width="40" height="19" class="h-8 w-auto shrink-0" decoding="async">
        <span class="font-display font-bold text-lg tracking-tight text-slate-900 dark:text-white">W5D</span>
      </a>

      <ul data-mega-host class="hidden lg:flex items-center gap-7 text-sm font-medium">
        @foreach($nav['items'] as $ni => $item)
          <li data-mega-trigger @if($ni === $navBest) data-active @endif>
            <button type="button" class="inline-flex items-center gap-1 py-2 hover:text-brand-500 transition" aria-expanded="false" aria-haspopup="true" @if($ni === $navBest) aria-current="{{ $navExact === $ni ? 'page' : 'true' }}" @endif>
              {{ t($item['label']) }} {{ icon('chevron-down', 'w-4 h-4 opacity-60') }}
            </button>
            <div data-mega-panel class="menu-panel mega-panel absolute inset-x-0 top-full pt-2 z-50" aria-hidden="true">
              <div class="mega-surface shadow-2xl">
              <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-7 grid gap-8 items-start {{ !empty($item['promo']) ? 'grid-cols-[minmax(0,1fr)_minmax(0,1fr)_16rem]' : 'grid-cols-2' }}">
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
                          @php $lon = $navLinkOn($l['href']); @endphp
                          <a href="{{ link_to($l['href']) }}" @if($lon !== '') data-active-link aria-current="{{ $lon }}" @endif class="flex items-start gap-3 rounded-xl px-3 py-2 hover:bg-brand-500/10 transition">
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
                  <a href="{{ link_to($item['promo']['href']) }}" class="rounded-xl p-4 text-white on-grad block self-stretch" style="--grad: var(--g-{{ $item['promo']['gradient'] ?? 'technology' }})">
                    <span class="tier-badge">{{ t($item['promo']['tag']) }}</span>
                    <span class="block font-display font-bold text-lg mt-2">{{ t($item['promo']['title']) }}</span>
                    <span class="block text-sm mt-1 opacity-90">{{ t($item['promo']['body']) }}</span>
                  </a>
                @endif
              </div>
              </div>
            </div>
          </li>
        @endforeach
      </ul>

      @php
        $acctUser = \W5D\Account::user();
        $ownerIn = \W5D\Auth::check();
        $signedIn = $acctUser !== null || $ownerIn;
        $unread = \W5D\Account::unread();
      @endphp
      <div class="flex items-center gap-0.5 min-[400px]:gap-1.5 sm:gap-2">
        @if(\W5D\Social::enabled())
        <button type="button" data-aside-open="aside-share" class="max-[359px]:hidden btn-ghost rounded-lg w-9 h-9 min-[400px]:w-10 min-[400px]:h-10 inline-flex items-center justify-center" aria-label="{{ __('share.open') }}" aria-controls="aside-share" aria-haspopup="dialog">{{ icon('share-2', 'w-5 h-5') }}</button>
        @endif
        <button type="button" data-quick-open class="max-lg:hidden btn-ghost rounded-lg w-9 h-9 min-[400px]:w-10 min-[400px]:h-10 inline-flex items-center justify-center" aria-label="{{ __('ux.find') }}" title="{{ __('ux.find_hint') }}">{{ icon('search', 'w-5 h-5') }}</button>
        <button type="button" data-aside-open="aside-user" class="btn-ghost relative rounded-lg w-9 h-9 min-[400px]:w-10 min-[400px]:h-10 inline-flex items-center justify-center {{ $signedIn ? 'text-brand-500' : '' }}" aria-label="{{ __('nav.user') }}" aria-controls="aside-user" aria-haspopup="dialog">
          {{ icon($signedIn ? 'circle-user' : 'user-round', 'w-5 h-5') }}
          @if($unread > 0)<span class="absolute -top-0.5 -end-0.5 min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-pink-500 text-white text-[0.65rem] font-bold leading-[1.1rem] text-center">{{ $unread > 9 ? '9+' : $unread }}</span>@elseif($signedIn)<span class="absolute top-1.5 end-1.5 w-2 h-2 rounded-full bg-emerald-400 ring-2 ring-white dark:ring-ink-900"></span>@endif
        </button>
        <button type="button" data-aside-open="aside-lang" class="btn-ghost rounded-lg h-9 min-[400px]:h-10 px-2 min-[400px]:px-3 inline-flex items-center gap-1 text-sm" aria-label="{{ __('nav.language') }}">
          {{ icon('languages', 'w-5 h-5') }}<span class="uppercase text-xs font-bold">{{ lang() }}</span>
        </button>
        <button type="button" data-theme-toggle class="btn-ghost rounded-lg w-9 h-9 min-[400px]:w-10 min-[400px]:h-10 inline-flex items-center justify-center" aria-label="{{ __('nav.theme') }}">
          <span data-theme-icon="sun">{{ icon('sun', 'w-5 h-5') }}</span>
          <span data-theme-icon="moon" class="hidden">{{ icon('moon', 'w-5 h-5') }}</span>
        </button>
        @if(!$signedIn)<a href="{{ url('/account/login/') }}" data-auth-open="login" class="hidden md:inline-flex btn-ghost text-sm !py-2 !px-3">{{ __('ux.sign_in') }}</a>@endif
        <a href="{{ link_to($hc['href']) }}" data-cta="{{ $hc['key'] }}" class="hidden sm:inline-flex btn-focus text-sm !py-2 !px-4">{{ $hc['label'] }} {{ icon('arrow-right', 'w-4 h-4 rtl-flip') }}</a>
        <button type="button" data-mobile-toggle class="lg:hidden btn-ghost rounded-lg w-9 h-9 min-[400px]:w-10 min-[400px]:h-10 inline-flex items-center justify-center" aria-label="{{ __('nav.menu') }}" aria-expanded="false" aria-controls="mobileMenu">{{ icon('menu', 'w-5 h-5') }}</button>
      </div>
    </nav>

    <div id="mobileMenu" class="menu-panel lg:hidden nav-solid rounded-2xl mt-2 p-4 max-h-[75vh] overflow-y-auto" aria-hidden="true">
      @if(!$signedIn)<div class="grid grid-cols-2 gap-2 mb-2"><a class="btn-ghost justify-center" href="{{ url('/account/login/') }}" data-auth-open="login">{{ __('ux.sign_in') }}</a><a class="btn-ghost justify-center" href="{{ url('/account/register/') }}" data-auth-open="register">{{ __('ux.sign_up') }}</a></div>@endif
      @foreach($nav['items'] as $ni => $item)
        <p class="text-[0.68rem] uppercase tracking-[0.18em] mt-3 mb-1 font-semibold {{ $ni === $navBest ? 'text-brand-500' : 'text-slate-500' }}">{{ t($item['label']) }}</p>
        @foreach($item['groups'] as $g)
          @php
            $links = $g['items'] ?? [];
            if (($g['each'] ?? '') === 'macros') { $links = []; foreach ($macros as $m) $links[] = ['icon' => $m['icon'], 'label' => $m['name'], 'href' => '/macro-works/' . $m['slug'] . '/']; }
          @endphp
          @foreach($links as $l)
            @php $lon = $navLinkOn($l['href']); @endphp
            <a href="{{ link_to($l['href']) }}" @if($lon !== '') data-active-link aria-current="{{ $lon }}" @endif class="flex items-center gap-2 py-1.5 text-sm">{{ icon($l['icon'], 'w-4 h-4 text-brand-500') }} {{ t($l['label']) }}</a>
          @endforeach
        @endforeach
      @endforeach
      <a href="{{ link_to($hc['href']) }}" data-cta="{{ $hc['key'] }}" class="btn-focus mt-4 w-full">{{ $hc['label'] }}</a>
    </div>
  </div>
</header>

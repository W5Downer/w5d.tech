{{-- Slim top bar for focused pages (sign-in, payment, QCM round, certificate, unsubscribe): brand, way out, language, theme. No mega menu, no tab bar. --}}
@php
  $mbBack = $backHref ?? (\W5D\Account::signedIn() ? url('/account/') : url('/'));
  $mbBackLabel = $backLabel ?? (\W5D\Account::signedIn() ? __('ux.mini_account') : __('ux.mini_home'));
@endphp
<header class="w5-minibar">
  <a href="{{ url('/') }}" class="flex items-center gap-2" aria-label="W5D">
    <img src="{{ asset('images/logo.svg') }}" alt="" width="40" height="19" class="h-8 w-auto shrink-0" decoding="async">
    <span class="font-display font-bold text-lg tracking-tight text-slate-900 dark:text-white">W5D</span>
  </a>
  <div class="flex items-center gap-1.5">
    <a class="back" href="{{ $mbBack }}">{{ icon('arrow-left', 'w-4 h-4 rtl:rotate-180') }} {{ $mbBackLabel }}</a>
    <button type="button" data-aside-open="aside-lang" class="btn-ghost rounded-lg h-9 px-2 inline-flex items-center gap-1 text-sm" aria-label="{{ __('nav.language') }}" aria-controls="aside-lang">{{ icon('languages', 'w-5 h-5') }}<span class="uppercase text-xs font-bold">{{ lang() }}</span></button>
    <button type="button" data-theme-toggle class="btn-ghost rounded-lg w-9 h-9 inline-flex items-center justify-center" aria-label="{{ __('nav.theme') }}">
      <span data-theme-icon="sun">{{ icon('sun', 'w-5 h-5') }}</span><span data-theme-icon="moon" class="hidden">{{ icon('moon', 'w-5 h-5') }}</span>
    </button>
  </div>
</header>

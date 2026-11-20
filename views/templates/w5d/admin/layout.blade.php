<!DOCTYPE html>
<html lang="{{ lang() }}" dir="{{ dir_attr() }}" class="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'Owner portal') — W5D</title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%237c3aed'/%3E%3C/svg%3E">
  <script src="{{ asset('js/theme-init.js') }}"></script>
  <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
  <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}">
  <link rel="stylesheet" href="{{ asset('css/attention.css') }}">
  @if(lang() === 'ar')<link rel="stylesheet" href="{{ asset('css/rtl.css') }}">@endif
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-ink-950 dark:text-slate-200 antialiased">
  @include('partials.loader')
  @php $menus = $menus ?? []; @endphp
  @auth
  <div class="min-h-screen lg:flex">
    {{-- mobile top bar: brand + menu toggle (the 26-item menu lives in a drawer, never stacked above the page) --}}
    <header class="admin-topbar lg:hidden">
      <a href="{{ admin_url() }}" class="flex items-center gap-2 min-w-0">
        <img src="{{ asset('images/logo.svg') }}" alt="" width="40" height="19" class="h-7 w-auto shrink-0" decoding="async">
        <span class="truncate font-display font-bold text-slate-900 dark:text-white">{{ t($menus[array_search($active ?? '', array_column($menus, 'slug'))]['label'] ?? ['en' => __('admin.title'), 'fr' => __('admin.title'), 'ar' => __('admin.title')]) }}</span>
      </a>
      <button type="button" data-admin-nav-toggle class="btn-ghost rounded-lg w-10 h-10 inline-flex items-center justify-center shrink-0" aria-label="{{ __('nav.menu') }}" aria-controls="adminNav" aria-expanded="false">{{ icon('menu', 'w-5 h-5') }}</button>
    </header>
    <div class="admin-backdrop lg:hidden" data-admin-nav-close></div>
    <aside id="adminNav" class="admin-nav" aria-label="Admin" aria-hidden="true">
      <div class="px-5 py-4 flex items-center justify-between gap-3">
        <a href="{{ admin_url() }}" class="flex items-center gap-2 min-w-0">
          <img src="{{ asset('images/logo.svg') }}" alt="" width="40" height="19" class="h-7 w-auto shrink-0" decoding="async">
          <span class="min-w-0"><span class="block font-display font-bold leading-tight text-slate-900 dark:text-white truncate">{{ __('admin.title') }}</span><span class="block text-xs text-slate-500 truncate">{{ $user['name'] ?? '' }} · {{ $user['role'] ?? '' }}</span></span>
        </a>
        <button type="button" data-admin-nav-close class="lg:hidden btn-ghost rounded-lg w-9 h-9 inline-flex items-center justify-center shrink-0" aria-label="Close">{{ icon('x', 'w-5 h-5') }}</button>
      </div>
      <nav class="px-3 pb-4 grid grid-cols-1 gap-0.5 text-sm">
        @foreach($menus as $m)
          @php $href = $m['slug'] === 'command-center' ? admin_url() : admin_url($m['slug'] . '/'); @endphp
          <a href="{{ $href }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2.5 transition {{ ($active ?? '') === $m['slug'] ? 'bg-brand-500/15 text-brand-700 dark:text-brand-300 font-semibold' : 'hover:bg-slate-500/10' }}" @if(($active ?? '') === $m['slug']) aria-current="page" @endif>
            {{ icon($m['icon'], 'w-4 h-4 shrink-0') }}<span class="truncate">{{ t($m['label']) }}</span>
          </a>
        @endforeach
      </nav>
      <div class="px-5 pb-6 flex flex-wrap items-center gap-x-3 gap-y-2 text-xs">
        <a class="btn-link" href="{{ url('/') }}">{{ icon('external-link', 'w-3.5 h-3.5') }} Site</a>
        @foreach(['en', 'fr', 'ar'] as $l)<a class="{{ lang() === $l ? 'font-bold' : 'text-slate-500' }}" href="{{ url('/language/' . $l . '?r=/' . trim((string)\W5D\Env::get('ADMIN_URL_PATH', 'panel-x9z'), '/') . '/') }}">{{ strtoupper($l) }}</a>@endforeach
        <form method="post" action="{{ admin_url('logout') }}" class="ms-auto">{!! csrf_field() !!}<button class="btn-link" type="submit">{{ __('admin.logout') }}</button></form>
      </div>
    </aside>
    <main id="main" class="flex-1 min-w-0 w-full p-4 sm:p-8">
      @if(!empty($flash))<p class="mb-5 rounded-lg border p-3 text-sm {{ $flash['kind'] === 'ok' ? 'border-emerald-500/40 bg-emerald-500/10' : 'border-red-500/40 bg-red-500/10' }}" role="status">{{ $flash['msg'] }}</p>@php unset($_SESSION['_admin_flash']); @endphp@endif
      @yield('content')
    </main>
  </div>
  @else
    @yield('content')
  @endauth
  <script src="{{ asset('js/site.js') }}" defer></script>
</body>
</html>

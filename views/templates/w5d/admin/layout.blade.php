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
  @auth
  <div class="min-h-screen lg:flex">
    <aside class="lg:w-72 shrink-0 border-b lg:border-b-0 lg:border-e border-slate-200 dark:border-white/10 bg-white dark:bg-ink-900/60 lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto">
      <div class="px-5 py-4 flex items-center justify-between lg:block">
        <a href="{{ admin_url() }}" class="flex items-center gap-2">
          <span class="inline-flex w-9 h-9 rounded-xl bg-gradient-to-br from-brand-500 via-indigo-500 to-cyan-400 items-center justify-center font-display font-bold text-white text-sm">W5</span>
          <span><span class="block font-display font-bold leading-tight text-slate-900 dark:text-white">{{ __('admin.title') }}</span><span class="block text-xs text-slate-500">{{ $user['name'] ?? '' }} · {{ $user['role'] ?? '' }}</span></span>
        </a>
      </div>
      <nav aria-label="Admin" class="px-3 pb-4 grid grid-cols-2 lg:grid-cols-1 gap-0.5 text-sm">
        @foreach($menus as $m)
          @php $href = $m['slug'] === 'command-center' ? admin_url() : admin_url($m['slug'] . '/'); @endphp
          <a href="{{ $href }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 transition {{ ($active ?? '') === $m['slug'] ? 'bg-brand-500/15 text-brand-700 dark:text-brand-300 font-semibold' : 'hover:bg-slate-500/10' }}" @if(($active ?? '') === $m['slug']) aria-current="page" @endif>
            {{ icon($m['icon'], 'w-4 h-4 shrink-0') }}<span class="truncate">{{ t($m['label']) }}</span>
          </a>
        @endforeach
      </nav>
      <div class="px-5 pb-6 flex items-center gap-3 text-xs">
        <a class="btn-link" href="{{ url('/') }}">{{ icon('external-link', 'w-3.5 h-3.5') }} Site</a>
        @foreach(['en', 'fr', 'ar'] as $l)<a class="{{ lang() === $l ? 'font-bold' : 'text-slate-500' }}" href="{{ url('/lang/' . $l . '?r=/' . trim((string)\W5D\Env::get('ADMIN_URL_PATH', 'panel-x9z'), '/') . '/') }}">{{ strtoupper($l) }}</a>@endforeach
        <form method="post" action="{{ admin_url('logout') }}" class="ms-auto">{!! csrf_field() !!}<button class="btn-link" type="submit">{{ __('admin.logout') }}</button></form>
      </div>
    </aside>
    <main id="main" class="flex-1 min-w-0 p-4 sm:p-8">
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

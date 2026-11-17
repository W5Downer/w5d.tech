<!DOCTYPE html>
<html lang="{{ lang() }}" dir="{{ dir_attr() }}" class="dark scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'W5D')</title>
  <meta name="description" content="@yield('description', __('brand.tagline'))">
  <meta name="theme-color" content="#070711">
  <meta name="robots" content="@yield('robots', 'index,follow')">
  <link rel="canonical" href="{{ \W5D\Env::baseUrl() . $canonicalPath }}">
  <meta property="og:title" content="@yield('title', 'W5D')">
  <meta property="og:description" content="@yield('description', __('brand.tagline'))">
  <meta property="og:type" content="website">
  <link rel="alternate" type="application/json" href="{{ url('/api/index.json') }}" title="W5D API">
  <link rel="alternate" type="text/plain" href="{{ url('/llms.txt') }}" title="llms.txt">
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%237c3aed'/%3E%3Ctext x='16' y='21' font-family='sans-serif' font-size='14' font-weight='700' text-anchor='middle' fill='white'%3EW5%3C/text%3E%3C/svg%3E">
  <script src="{{ asset('js/theme-init.js') }}"></script>
  <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
  <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}">
  <link rel="stylesheet" href="{{ asset('css/attention.css') }}">
  @if(lang() === 'ar')<link rel="stylesheet" href="{{ asset('css/rtl.css') }}">@endif
  @stack('head')
</head>
<body class="bg-white text-slate-800 dark:bg-ink-950 dark:text-slate-200 transition-colors duration-300 antialiased">
  <a class="skip-link" href="#main">{{ __('nav.skip') }}</a>
  @include('partials.header')
  <main id="main" tabindex="-1">
    @yield('content')
  </main>
  @yield('sticky')
  @include('partials.footer')
  @include('partials.aside')
  <script src="{{ asset('js/site.js') }}" defer></script>
  @stack('scripts')
</body>
</html>

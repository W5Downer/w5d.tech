<!DOCTYPE html>
<html lang="{{ lang() }}" dir="{{ dir_attr() }}" class="dark scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
@php
  $seoTitle = html_entity_decode(strip_tags($__env->yieldContent('title', 'W5D')), ENT_QUOTES);
  $seoDesc = html_entity_decode(strip_tags($__env->yieldContent('description', '')), ENT_QUOTES);
  if ($seoDesc === '') $seoDesc = __('brand.tagline');
  $seoDesc = trim(preg_replace('/\s+/u', ' ', $seoDesc));
  // Keep snippets in the 50-160 character window search engines display.
  if (mb_strlen($seoDesc) < (lang() === 'ar' ? 40 : 55)) $seoDesc = rtrim($seoDesc, ' .') . '. ' . __('brand.desc_suffix');
  if (mb_strlen($seoDesc) > 165) { $cut = mb_substr($seoDesc, 0, 160); $sp = mb_strrpos($cut, ' '); $seoDesc = rtrim(mb_substr($cut, 0, $sp > 100 ? $sp : 160), ' ,;:.—-') . '…'; }
  $seoRobots = $__env->yieldContent('robots', 'index,follow');
  $seoPath = $canonicalPath ?? '/';
  $seoUrl = \W5D\Env::baseUrl() . url($seoPath);
  $seoImg = \W5D\Social::imageUrl($seoPath, $__env->hasSection('og_image') ? $__env->yieldContent('og_image') : null);
  $ogLocale = ['en' => 'en_US', 'fr' => 'fr_FR', 'ar' => 'ar_TN'];
  $seoPublic = !str_contains($seoRobots, 'noindex');
@endphp
  <title>{{ $seoTitle }}</title>
  <meta name="description" content="{{ $seoDesc }}">
  <meta name="theme-color" content="#070711">
  <meta name="color-scheme" content="dark light">
  <meta name="robots" content="{{ $seoRobots . ($seoPublic ? ',max-image-preview:large,max-snippet:-1' : '') }}">
  <link rel="canonical" href="{{ $seoUrl }}">
  <link rel="alternate" hreflang="x-default" href="{{ $seoUrl }}">
  <link rel="alternate" hreflang="{{ lang() }}" href="{{ $seoUrl }}">
  <meta property="og:site_name" content="W5D">
  <meta property="og:type" content="website">
  <meta property="og:url" content="{{ $seoUrl }}">
  <meta property="og:title" content="{{ $seoTitle }}">
  <meta property="og:description" content="{{ $seoDesc }}">
  <meta property="og:locale" content="{{ $ogLocale[lang()] ?? 'en_US' }}">
  {!! implode("\n  ", array_map(fn($lc) => '<meta property="og:locale:alternate" content="' . $ogLocale[$lc] . '">', array_values(array_diff(array_keys($ogLocale), [lang()])))) !!}
  <meta property="og:image" content="{{ $seoImg }}">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:alt" content="{{ $seoTitle }}">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $seoTitle }}">
  <meta name="twitter:description" content="{{ $seoDesc }}">
  <meta name="twitter:image" content="{{ $seoImg }}">
  @php $tw = \W5D\Social::via(); $fb = (string)\W5D\Env::get('SOCIAL_FACEBOOK_APP_ID', ''); @endphp
  @if($tw !== '')
  <meta name="twitter:site" content="{{ '@' . $tw }}">
  @endif
  @if($fb !== '')
  <meta property="fb:app_id" content="{{ $fb }}">
  @endif
  <link rel="alternate" type="application/json" href="{{ url('/api/index.json') }}" title="W5D API">
  <link rel="sitemap" type="application/xml" href="{{ url('/sitemap.xml') }}">
  <link rel="icon" type="image/svg+xml" href="{{ asset('images/favicon.svg') }}">
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
  <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
  <link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
  @if($seoPublic)
  <script type="application/ld+json">{!! \W5D\Seo::jsonLd(\W5D\Seo::graph($seoPath, $seoTitle, $seoDesc, $seoRobots, $seoImg)) !!}</script>
  @endif
  <script src="{{ asset('js/theme-init.js') }}"></script>
  <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
  <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}">
  <link rel="stylesheet" href="{{ asset('css/attention.css') }}">
  @if(lang() === 'ar')<link rel="stylesheet" href="{{ asset('css/rtl.css') }}">@endif
  @foreach(\W5D\Integrations::verificationTags() as $__vn => $__vc)
  <meta name="{{ $__vn }}" content="{{ $__vc }}">
  @endforeach
  @stack('head')
</head>
<body class="bg-white text-slate-800 dark:bg-ink-950 dark:text-slate-200 transition-colors duration-300 antialiased">
  @include('partials.loader')
  <a class="skip-link" href="#main">{{ __('nav.skip') }}</a>
  @include('partials.header')
  <main id="main" tabindex="-1">
    @yield('content')
  </main>
  @yield('sticky')
  @include('partials.footer')
  @include('partials.aside')
  @include('partials.tabbar')
  @include('partials.auth-dialog')
  @include('partials.quickfind')
  <script src="{{ asset('js/site.js') }}" defer></script>
  @include('partials.integrations')
  @stack('scripts')
</body>
</html>

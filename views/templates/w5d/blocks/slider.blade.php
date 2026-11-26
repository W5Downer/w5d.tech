@php if (!empty($d['source']) && ($src = \W5D\Content::slider($d['source']))) { $d = $src + $d; } $slides = $d['slides'] ?? []; $ms = (int)($d['interval'] ?? 7500); @endphp
<section @if(!empty($d['id']))id="{{ $d['id'] }}"@endif class="tier-e focus-5 relative overflow-hidden w5-slider hero-dark" data-tier="e" data-focus="5" data-slider data-interval="{{ $ms }}" data-tone="{{ ($d['slides'][0]['gradient'] ?? 'peak') }}" style="--slider-ms: {{ $ms }}ms; --slider-h: {{ $d['height'] ?? '40rem' }}" aria-roledescription="carousel" aria-label="{{ t($d['label'] ?? 'W5D') }}">
  <div class="absolute inset-0 grid-bg pointer-events-none -z-10"></div>
  <div class="orb-a"></div><div class="orb-b"></div><div class="orb-c"></div>
  <div class="w5-slides">
    @foreach($slides as $i => $s)
      <div class="w5-slide {{ $i === 0 ? 'is-active' : '' }}" data-slide data-tone="{{ $s['gradient'] ?? 'peak' }}" role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} / {{ count($slides) }}">
        <div class="relative mx-auto max-w-5xl w-full px-4 sm:px-6 lg:px-8 pt-36 pb-28 text-center">
          <div class="rise">
            <span class="hero-pill glass">{{ icon($s['icon'] ?? 'sparkles') }} {{ rich($s['eyebrow'] ?? '') }}</span>
            <{{ $i === 0 ? 'h1' : 'h2' }} class="font-display text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mt-6 leading-[1.06] text-slate-900 dark:text-white">@if($i === 0)<span class="sr-only">W5D — </span>@endif{{ hl($s['title'] ?? '') }}</{{ $i === 0 ? 'h1' : 'h2' }}>
            <p class="mt-6 text-lg sm:text-xl text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">{{ rich($s['lead'] ?? '') }}</p>
            <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
              @foreach(($s['ctas'] ?? []) as $c)<x-cta :c="$c" />@endforeach
            </div>
            @if(!empty($s['note']))<p class="mt-5 text-xs text-slate-600 dark:text-slate-400">{{ rich($s['note']) }}</p>@endif
          </div>
        </div>
      </div>
    @endforeach
  </div>
  <div class="absolute bottom-0 inset-x-0 z-10">
    <div class="w5-progress"><i></i></div>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between text-slate-700 dark:text-white">
      <div class="flex items-center" role="group" aria-label="{{ __('cta.next') }}">
        @foreach($slides as $i => $s)<button type="button" class="w5-dot" data-dot aria-label="{{ $i + 1 }}" @if($i === 0) aria-current="true" @endif></button>@endforeach
      </div>
      <div class="flex items-center gap-2">
        <span class="hidden min-[400px]:inline text-xs tabular-nums opacity-80" data-live aria-live="polite">1 / {{ count($slides) }}</span>
        <button type="button" data-prev class="rounded-full glass hover:border-brand-500/60 p-2" aria-label="{{ __('cta.back') }}">{{ icon('chevron-left', 'w-4 h-4 rtl:rotate-180') }}</button>
        <button type="button" data-next class="rounded-full glass hover:border-brand-500/60 p-2" aria-label="{{ __('cta.next') }}">{{ icon('chevron-right', 'w-4 h-4 rtl:rotate-180') }}</button>
      </div>
    </div>
  </div>
</section>

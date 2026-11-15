@php if (!empty($d['source']) && ($src = \W5D\Content::slider($d['source']))) { $d = $src + $d; } $slides = $d['slides'] ?? []; $ms = (int)($d['interval'] ?? 7500); @endphp
<section @if(!empty($d['id']))id="{{ $d['id'] }}"@endif class="tier-e focus-5 relative overflow-hidden w5-slider" data-tier="e" data-focus="5" data-slider data-interval="{{ $ms }}" style="--slider-ms: {{ $ms }}ms; --slider-h: {{ $d['height'] ?? '38rem' }}" aria-roledescription="carousel" aria-label="{{ t($d['label'] ?? 'W5D') }}">
  <div class="w5-slides">
    @foreach($slides as $i => $s)
      @php $g = $s['gradient'] ?? 'peak'; $gv = in_array($g, ['cultures','built','sciences','governance','technology']) ? g_macro($g) : 'var(--g-' . $g . ')'; @endphp
      <div class="w5-slide on-grad {{ $i === 0 ? 'is-active' : '' }}" data-slide style="--grad: {{ $gv }}" role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} / {{ count($slides) }}">
        <div class="absolute inset-0 grid-bg opacity-25 pointer-events-none"></div>
        <div class="relative mx-auto max-w-7xl w-full px-4 sm:px-6 lg:px-8 pt-32 pb-24">
          <div class="rise max-w-3xl">
            <p class="tier-badge text-white/90">{{ icon($s['icon'] ?? 'sparkles', 'w-4 h-4') }} {{ rich($s['eyebrow'] ?? '') }}</p>
            <h2 class="font-display text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mt-4 leading-[1.08]">@if($i === 0)<span class="sr-only">W5D — </span>@endif{{ hl($s['title'] ?? '') }}</h2>
            <p class="mt-5 text-lg sm:text-xl text-white/90 max-w-2xl">{{ rich($s['lead'] ?? '') }}</p>
            <div class="mt-8 flex flex-wrap items-center gap-3">
              @foreach(($s['ctas'] ?? []) as $c)<x-cta :c="$c" />@endforeach
            </div>
            @if(!empty($s['note']))<p class="mt-4 text-xs text-white/80">{{ rich($s['note']) }}</p>@endif
          </div>
        </div>
      </div>
    @endforeach
  </div>
  <div class="absolute bottom-0 inset-x-0 z-10">
    <div class="w5-progress"><i></i></div>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between text-white">
      <div class="flex items-center gap-2" role="tablist">
        @foreach($slides as $i => $s)<button type="button" class="w5-dot" data-dot aria-label="{{ $i + 1 }}" @if($i === 0) aria-current="true" @endif></button>@endforeach
      </div>
      <div class="flex items-center gap-2">
        <span class="text-xs tabular-nums opacity-80" data-live aria-live="polite">1 / {{ count($slides) }}</span>
        <button type="button" data-prev class="rounded-full bg-black/30 hover:bg-black/50 p-2" aria-label="{{ __('cta.back') }}">{{ icon('chevron-left', 'w-4 h-4 rtl:rotate-180') }}</button>
        <button type="button" data-next class="rounded-full bg-black/30 hover:bg-black/50 p-2" aria-label="{{ __('cta.next') }}">{{ icon('chevron-right', 'w-4 h-4 rtl:rotate-180') }}</button>
      </div>
    </div>
  </div>
</section>

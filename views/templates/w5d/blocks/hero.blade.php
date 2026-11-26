@php
  $variant = $d['variant'] ?? 'classic';
  $tone = $d['gradient'] ?? 'peak';
  $center = $variant !== 'product';
@endphp
<section @if(!empty($d['id']))id="{{ $d['id'] }}"@endif class="tier-e focus-5 hero-dark spotlight pt-36 pb-20 sm:pb-24" data-tier="e" data-focus="5" data-tone="{{ $tone }}">
  <div class="absolute inset-0 grid-bg pointer-events-none -z-10"></div>
  <div class="orb-a"></div><div class="orb-b"></div><div class="orb-c"></div>
  <div class="relative mx-auto {{ $center ? 'max-w-5xl' : 'max-w-7xl lg:grid-cols-2 items-center' }} px-4 sm:px-6 lg:px-8 grid gap-12">
    <div class="{{ $center ? 'text-center mx-auto' : 'max-w-3xl' }}">
      @if(!empty($d['eyebrow']))<span class="hero-pill glass">{{ icon($d['icon'] ?? 'sparkles') }} {{ rich($d['eyebrow']) }}</span>@endif
      <h1 class="font-display text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mt-6 leading-[1.06] text-slate-900 dark:text-white">{{ hl($d['title'] ?? '') }}</h1>
      @if(!empty($d['lead']))<p class="mt-6 text-lg sm:text-xl text-slate-600 dark:text-slate-400 max-w-2xl {{ $center ? 'mx-auto' : '' }}">{{ rich($d['lead']) }}</p>@endif
      @if(!empty($d['ctas']))
      <div class="mt-9 flex flex-wrap items-center gap-3 {{ $center ? 'justify-center' : '' }}">
        @foreach($d['ctas'] as $c)<x-cta :c="$c" />@endforeach
      </div>
      @endif
      @if(!empty($d['badges']))
      <ul class="mt-8 flex flex-wrap gap-2 text-xs {{ $center ? 'justify-center' : '' }}">
        @foreach($d['badges'] as $b)<li class="glass rounded-full px-3 py-1.5 inline-flex items-center gap-1.5">{{ icon($b['icon'] ?? 'check', 'w-3.5 h-3.5') }} {{ rich($b['label']) }}</li>@endforeach
      </ul>
      @endif
      @if(!empty($d['note']))<p class="mt-5 text-xs text-slate-600 dark:text-slate-400 max-w-xl {{ $center ? 'mx-auto' : '' }}">{{ rich($d['note']) }}</p>@endif
    </div>
    @if($variant === 'product')
    <div class="grad-border rounded-3xl glass p-6 shadow-2xl shadow-brand-500/10 text-slate-900 dark:text-white">
      @php $pr = \W5D\Variables::get('price'); @endphp
      <p class="tier-badge">{{ __('price.live') }}</p>
      <p class="font-display text-5xl font-bold mt-3">{{ number_format((float)$pr['day'], 2) }} <span class="text-lg font-medium">{{ $pr['currency'] }}</span></p>
      <p class="text-sm opacity-80 mt-1">{{ __('price.per_day') }}</p>
      <div class="mt-6 space-y-3 text-sm">
        <div><div class="flex justify-between"><span>{{ __('price.progress') }}</span><span>{{ number_format($pr['p']*100, 1) }}%</span></div><div class="h-1.5 rounded bg-slate-500/20 mt-1"><div class="h-full rounded bg-brand-500" style="width: {{ max(1.5, $pr['p']*100) }}%"></div></div></div>
        <div><div class="flex justify-between"><span>{{ __('price.time') }}</span><span>{{ number_format($pr['t']*100, 1) }}%</span></div><div class="h-1.5 rounded bg-slate-500/20 mt-1"><div class="h-full rounded bg-cyan-500" style="width: {{ max(1.5, $pr['t']*100) }}%"></div></div></div>
      </div>
      <p class="text-xs opacity-70 mt-5">{{ __('price.formula') }}</p>
    </div>
    @endif
  </div>
</section>

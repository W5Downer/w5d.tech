@php
  $variant = $d['variant'] ?? 'classic';
  $grad = $d['gradient'] ?? 'peak';
  $gv = in_array($grad, ['cultures','built','sciences','governance','technology']) ? g_macro($grad) : 'var(--g-' . $grad . ')';
@endphp
<section @if(!empty($d['id']))id="{{ $d['id'] }}"@endif class="tier-e focus-5 on-grad relative overflow-hidden pt-32 pb-20 sm:pt-40 sm:pb-28" data-tier="e" data-focus="5" style="--grad: {{ $gv }}">
  <div class="absolute inset-0 grid-bg opacity-30 pointer-events-none"></div>
  <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 grid gap-12 {{ $variant === 'product' ? 'lg:grid-cols-2 items-center' : '' }}">
    <div class="max-w-3xl {{ $variant === 'editorial' ? 'mx-auto text-center' : '' }}">
      @if(!empty($d['eyebrow']))<p class="tier-badge text-white/90">{{ icon($d['icon'] ?? 'sparkles', 'w-4 h-4') }} {{ rich($d['eyebrow']) }}</p>@endif
      <h1 class="font-display text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mt-4 leading-[1.08]">{{ hl($d['title'] ?? '') }}</h1>
      @if(!empty($d['lead']))<p class="mt-6 text-lg sm:text-xl text-white/90 max-w-2xl {{ $variant === 'editorial' ? 'mx-auto' : '' }}">{{ rich($d['lead']) }}</p>@endif
      @if(!empty($d['ctas']))
      <div class="mt-8 flex flex-wrap items-center gap-3 {{ $variant === 'editorial' ? 'justify-center' : '' }}">
        @foreach($d['ctas'] as $c)<x-cta :c="$c" />@endforeach
      </div>
      @endif
      @if(!empty($d['badges']))
      <ul class="mt-8 flex flex-wrap gap-2 text-xs {{ $variant === 'editorial' ? 'justify-center' : '' }}">
        @foreach($d['badges'] as $b)<li class="rounded-full bg-black/25 backdrop-blur px-3 py-1.5 inline-flex items-center gap-1.5">{{ icon($b['icon'] ?? 'check', 'w-3.5 h-3.5') }} {{ rich($b['label']) }}</li>@endforeach
      </ul>
      @endif
      @if(!empty($d['note']))<p class="mt-4 text-xs text-white/80 max-w-xl {{ $variant === 'editorial' ? 'mx-auto' : '' }}">{{ rich($d['note']) }}</p>@endif
    </div>
    @if($variant === 'product')
    <div class="glass rounded-3xl p-6 text-slate-900 dark:text-white">
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

@props(['d' => [], 'class' => '', 'pad' => 'py-16 sm:py-20', 'bare' => false])
@php
  $tier = $d['tier'] ?? 'i';
  $focus = (int)($d['focus'] ?? 2);
  $grad = $d['gradient'] ?? null;
@endphp
<section @if(!empty($d['id']))id="{{ $d['id'] }}"@endif class="tier-{{ $tier }} focus-{{ $focus }} {{ $pad }} {{ $class }}" data-tier="{{ $tier }}" data-focus="{{ $focus }}" @if($grad)style="--grad: {{ g_macro($grad) === 'var(--g-focus)' ? $grad : g_macro($grad) }}"@endif>
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" data-rv="up">
    @if(!$bare && (!empty($d['title']) || !empty($d['eyebrow']) || !empty($d['lead'])))
    <header class="max-w-3xl mb-10 {{ !empty($d['center']) ? 'mx-auto text-center' : '' }}">
      @if(!empty($d['eyebrow']))<p class="eyebrow">{{ rich($d['eyebrow']) }}</p>@endif
      @if(!empty($d['title']))<h2 class="font-display text-3xl sm:text-4xl font-bold tracking-tight text-slate-900 dark:text-white">{{ hl($d['title']) }}</h2>@endif
      @if(!empty($d['lead']))<p class="mt-4 text-lg text-slate-600 dark:text-slate-400">{{ rich($d['lead']) }}</p>@endif
    </header>
    @endif
    {{ $slot }}
  </div>
</section>

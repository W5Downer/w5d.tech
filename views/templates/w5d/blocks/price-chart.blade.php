@php
  $curve = \W5D\Pricing::curve(20); $snap = \W5D\Variables::get('price');
  $W = 640; $H = 280; $pad = 36; $max = (float)$snap['base_week'] * (float)$snap['cap'];
  $px = fn($x) => $pad + $x * ($W - 2*$pad);
  $py = fn($v) => $H - $pad - ($v / $max) * ($H - 2*$pad);
  $pts = fn($k) => implode(' ', array_map(fn($r) => round($px($r['x']),1) . ',' . round($py($r[$k]),1), $curve));
@endphp
<x-section :d="$d">
  <div class="grid gap-8 lg:grid-cols-[1.4fr_1fr] items-start">
    <figure class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white/60 dark:bg-white/5 p-4">
      <svg viewBox="0 0 {{ $W }} {{ $H }}" class="w-full h-auto" role="img" aria-label="{{ __('price.formula') }}">
        @for($i = 0; $i <= 4; $i++)<line class="pc-grid" x1="{{ $pad }}" x2="{{ $W - $pad }}" y1="{{ $pad + $i * ($H - 2*$pad)/4 }}" y2="{{ $pad + $i * ($H - 2*$pad)/4 }}"/><text x="4" y="{{ $pad + $i * ($H - 2*$pad)/4 + 4 }}" font-size="10" fill="currentColor" opacity=".6">{{ round($max * (1 - $i/4)) }}</text>@endfor
        <line class="pc-cap" x1="{{ $pad }}" x2="{{ $W - $pad }}" y1="{{ $py($snap['base_week'] * $snap['cap']) }}" y2="{{ $py($snap['base_week'] * $snap['cap']) }}"/>
        <polyline class="pc-line pc-week" points="{{ $pts('week') }}"/>
        <polyline class="pc-line pc-day" points="{{ $pts('day') }}"/>
        <circle class="pc-now" r="6" cx="{{ $px(max($snap['p'], $snap['t'])) }}" cy="{{ $py($snap['day']) }}"/>
        <text x="{{ $pad }}" y="{{ $H - 8 }}" font-size="10" fill="currentColor" opacity=".7">{{ __('price.progress') }} / {{ __('price.time') }}  0 → 100%</text>
      </svg>
      <figcaption class="text-xs text-slate-500 mt-2 flex flex-wrap gap-4"><span><i class="inline-block w-3 h-0.5 bg-violet-500 align-middle"></i> {{ __('price.per_day') }}</span><span><i class="inline-block w-3 h-0.5 bg-cyan-500 align-middle"></i> {{ __('price.per_week') }}</span><span><i class="inline-block w-3 h-0.5 bg-red-600 align-middle"></i> {{ __('price.cap') }}</span></figcaption>
    </figure>
    <div class="space-y-4">
      <div class="rounded-2xl border border-brand-500/40 bg-brand-500/10 p-5">
        <p class="tier-badge">{{ __('price.today') }}</p>
        <p class="font-display text-4xl font-bold mt-2 text-slate-900 dark:text-white">{{ number_format($snap['day'], 2) }} <span class="text-base">{{ $snap['currency'] }}</span></p>
        <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('price.per_day') }} · {{ number_format($snap['week'], 2) }} {{ __('price.per_week') }}</p>
      </div>
      <dl class="text-sm grid grid-cols-2 gap-3">
        <div><dt class="text-slate-500">{{ __('price.progress') }} (p)</dt><dd class="font-semibold">{{ number_format($snap['p'] * 100, 2) }}%</dd></div>
        <div><dt class="text-slate-500">{{ __('price.time') }} (t)</dt><dd class="font-semibold">{{ number_format($snap['t'] * 100, 2) }}%</dd></div>
        <div><dt class="text-slate-500">{{ __('price.at_p0') }}</dt><dd class="font-semibold">{{ number_format($snap['base_day'], 2) }}</dd></div>
        <div><dt class="text-slate-500">{{ __('price.at_p1') }}</dt><dd class="font-semibold">{{ number_format($snap['base_day'] * $snap['cap'], 2) }}</dd></div>
      </dl>
      <p class="text-xs font-mono text-slate-500">{{ __('price.formula') }}</p>
    </div>
  </div>
  <x-ctas :list="$d['ctas'] ?? []" />
</x-section>

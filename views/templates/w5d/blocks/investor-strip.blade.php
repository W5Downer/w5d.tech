@php $pr = \W5D\Variables::get('price'); $v = \W5D\Variables::all(); @endphp
<x-section :d="$d" pad="py-12">
  <div class="grad-border grad-border-live shine rounded-3xl border border-brand-500/30 bg-gradient-to-br from-brand-500/10 to-cyan-500/10 p-6 sm:p-8 grid gap-6 lg:grid-cols-[1.3fr_1fr] items-center">
    <div>
      <p class="tier-badge">{{ t($d['eyebrow'] ?? '') }}</p>
      <h2 class="font-display text-2xl sm:text-3xl font-bold mt-2 text-slate-900 dark:text-white">{{ hl($d['title'] ?? '') }}</h2>
      <p class="mt-3 text-slate-600 dark:text-slate-400">{{ rich($d['lead'] ?? '') }}</p>
      <div class="mt-6 flex flex-wrap gap-3">@foreach(($d['ctas'] ?? []) as $c)<x-cta :c="$c" />@endforeach</div>
    </div>
    <div class="grid grid-cols-2 gap-3 text-sm">
      <div class="rounded-xl bg-white/60 dark:bg-white/5 p-4"><p class="text-slate-500">{{ __('price.today') }}</p><p class="font-display text-2xl font-bold">{{ number_format($pr['day'], 2) }}</p><p class="text-xs text-slate-500">{{ $pr['currency'] }} / {{ __('price.per_day') }}</p></div>
      <div class="rounded-xl bg-white/60 dark:bg-white/5 p-4"><p class="text-slate-500">{{ __('price.progress') }}</p><p class="font-display text-2xl font-bold">{{ number_format($pr['p']*100, 1) }}%</p><p class="text-xs text-slate-500">{{ num($v['experts']['current']) }} / {{ num($v['d']['experts_target']) }}</p></div>
    </div>
  </div>
</x-section>

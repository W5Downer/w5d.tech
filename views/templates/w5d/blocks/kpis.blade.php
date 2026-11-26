<x-section :d="$d">
  <dl class="grid gap-4 grid-cols-2 lg:grid-cols-{{ min(5, max(2, count($d['items'] ?? []))) }}">
    @foreach(($d['items'] ?? []) as $k)
      <div data-rvi="zoom" class="tilt shine flex flex-col rounded-2xl border border-slate-200 dark:border-white/10 bg-white/60 dark:bg-white/5 p-5">
        @if(!empty($k['zero_label']) && numval($k['value']) <= 0)
        <dd class="order-1 font-display text-2xl sm:text-3xl font-bold text-brand-600 dark:text-brand-300">{{ t($k['zero_label']) }}</dd>
        @else
        <dd class="order-1 font-display text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white stat-num"><span data-count="{{ numval($k['value']) }}" data-dec="{{ $k['dec'] ?? 0 }}" @if(!empty($k['suffix']))data-suffix="{{ $k['suffix'] }}"@endif>{{ number_format(numval($k['value']), (int)($k['dec'] ?? 0)) }}{{ $k['suffix'] ?? '' }}</span></dd>
        @endif
        <dt class="order-2 text-sm text-slate-600 dark:text-slate-400 mt-1">{{ rich($k['label']) }}</dt>
        @if(!empty($k['note']))<dd class="order-3 text-xs text-slate-600 dark:text-slate-400 mt-2">{{ rich($k['note']) }}</dd>@endif
      </div>
    @endforeach
  </dl>
</x-section>

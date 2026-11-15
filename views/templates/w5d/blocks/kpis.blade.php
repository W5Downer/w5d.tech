<x-section :d="$d">
  <dl class="grid gap-4 grid-cols-2 lg:grid-cols-{{ min(5, max(2, count($d['items'] ?? []))) }}">
    @foreach(($d['items'] ?? []) as $k)
      <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white/60 dark:bg-white/5 p-5">
        <dd class="font-display text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white stat-num"><span data-count="{{ numval($k['value']) }}" data-dec="{{ $k['dec'] ?? 0 }}" @if(!empty($k['suffix']))data-suffix="{{ $k['suffix'] }}"@endif>{{ number_format(numval($k['value']), (int)($k['dec'] ?? 0)) }}{{ $k['suffix'] ?? '' }}</span></dd>
        <dt class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ rich($k['label']) }}</dt>
        @if(!empty($k['note']))<p class="text-xs text-slate-500 mt-2">{{ rich($k['note']) }}</p>@endif
      </div>
    @endforeach
  </dl>
</x-section>

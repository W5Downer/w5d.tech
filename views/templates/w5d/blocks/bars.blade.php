<x-section :d="$d">
  <ul class="space-y-4 max-w-3xl">
    @foreach(($d['items'] ?? []) as $b)
      @php $v = numval($b['value']); $mx = max(1, numval($b['max'] ?? 100)); $pct = max(1.5, min(100, $v / $mx * 100)); @endphp
      <li>
        <div class="flex justify-between text-sm"><span class="font-medium text-slate-900 dark:text-white">{{ t($b['label']) }}</span><span class="tabular-nums text-slate-500">{{ number_format($v, (int)($b['dec'] ?? 0)) }}{{ $b['suffix'] ?? '' }} / {{ number_format($mx) }}</span></div>
        <div class="h-2 rounded bg-slate-500/15 mt-1.5"><div class="h-full rounded bg-gradient-to-r from-brand-500 to-cyan-500" style="width: {{ $pct }}%"></div></div>
        @if(!empty($b['note']))<p class="text-xs text-slate-500 mt-1">{{ rich($b['note']) }}</p>@endif
      </li>
    @endforeach
  </ul>
</x-section>

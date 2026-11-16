<x-section :d="$d">
  <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10">
    <table class="w5-table">
      <thead><tr><th>{{ __('lbl.status') }}</th><th>{{ __('lbl.date') }}</th><th>Evidence</th></tr></thead>
      <tbody>@foreach(($d['rows'] ?? []) as $r)<tr><td><span class="tier-badge">{{ t($r['status']) }}</span> <strong>{{ t($r['name'] ?? '') }}</strong></td><td class="font-mono text-xs">{{ t($r['date'] ?? '') }}</td><td>{{ rich($r['evidence'] ?? '') }}</td></tr>@endforeach</tbody>
    </table>
  </div>
</x-section>

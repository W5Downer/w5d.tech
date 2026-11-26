<x-section :d="$d">
  <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10">
    <table class="w5-table">
      <thead><tr>@foreach(($d['columns'] ?? []) as $c)<th scope="col">{{ t($c) }}</th>@endforeach</tr></thead>
      <tbody>@foreach(($d['rows'] ?? []) as $r)<tr>@foreach($r as $cell)<td>{{ rich($cell) }}</td>@endforeach</tr>@endforeach</tbody>
    </table>
  </div>
  @if(!empty($d['note']))<p class="text-xs text-slate-600 dark:text-slate-400 mt-3">{{ rich($d['note']) }}</p>@endif
  <x-ctas :list="$d['ctas'] ?? []" />
</x-section>

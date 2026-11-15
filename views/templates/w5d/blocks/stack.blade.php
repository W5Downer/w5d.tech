@php $layers = \W5D\Content::all('layers'); $hi = $d['highlight'] ?? null; @endphp
<x-section :d="$d">
  <ol class="space-y-3 max-w-4xl">
    @foreach(array_reverse($layers, true) as $l)
      <li class="rounded-2xl border p-4 flex gap-4 items-start {{ $hi === $l['slug'] ? 'border-brand-500 bg-brand-500/10' : 'border-slate-200 dark:border-white/10 bg-white/50 dark:bg-white/5' }}">
        <span class="font-mono text-xs px-2 py-1 rounded bg-slate-900 text-white dark:bg-white dark:text-slate-900">{{ $l['code'] }}</span>
        <div><p class="font-display font-semibold text-slate-900 dark:text-white">{{ t($l['name']) }}</p><p class="text-sm text-slate-600 dark:text-slate-400">{{ rich($l['body']) }}</p></div>
      </li>
    @endforeach
  </ol>
  <x-ctas :list="$d['ctas'] ?? []" />
</x-section>

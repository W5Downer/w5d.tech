<x-section :d="$d">
  <dl class="grid gap-x-10 gap-y-5 md:grid-cols-2 max-w-5xl">
    @foreach(($d['items'] ?? []) as $it)
      <div class="border-s-2 border-brand-500/50 ps-4">
        <dt class="font-semibold text-slate-900 dark:text-white text-sm">{{ t($it['term']) }}</dt>
        <dd class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ rich($it['def']) }}</dd>
      </div>
    @endforeach
  </dl>
  <x-ctas :list="$d['ctas'] ?? []" />
</x-section>

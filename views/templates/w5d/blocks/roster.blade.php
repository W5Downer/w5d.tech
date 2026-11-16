<x-section :d="$d">
  <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
    @foreach(($d['items'] ?? []) as $r)
      <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white/60 dark:bg-white/5 p-5">
        <span class="inline-flex w-11 h-11 rounded-full bg-gradient-to-br from-brand-500 to-cyan-500 items-center justify-center text-white">{{ icon($r['icon'] ?? 'user', 'w-5 h-5') }}</span>
        <h3 class="font-display font-semibold mt-3 text-slate-900 dark:text-white">{{ t($r['title']) }}</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ rich($r['body']) }}</p>
        @if(!empty($r['count']))<p class="text-xs text-slate-500 mt-2">{{ t($r['count']) }}</p>@endif
      </div>
    @endforeach
  </div>
  <x-ctas :list="$d['ctas'] ?? []" />
</x-section>

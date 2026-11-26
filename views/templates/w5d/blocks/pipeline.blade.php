<x-section :d="$d">
  <ol class="grid gap-5 md:grid-cols-{{ min(4, max(2, count($d['items'] ?? []))) }}">
    @foreach(($d['items'] ?? []) as $i => $s)
      <li data-rvi="up" class="group tilt shine relative rounded-2xl border border-slate-200 dark:border-white/10 bg-white/60 dark:bg-white/5 overflow-hidden">
        <x-card-media :k="'pipeline:' . ($d['id'] ?? '') . ':' . $i" :alt="t($s['title'])" />
        <div class="p-6">
        <span class="inline-flex w-9 h-9 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-cyan-500 text-white font-bold text-sm">{{ $i + 1 }}</span>
        <h3 class="font-display font-semibold text-lg mt-4 text-slate-900 dark:text-white">{{ t($s['title']) }}</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-2">{{ rich($s['body'] ?? '') }}</p>
        @if(!empty($s['meta']))<p class="text-xs mt-3 text-slate-600 dark:text-slate-400">{{ icon('clock', 'w-3.5 h-3.5 inline') }} {{ t($s['meta']) }}</p>@endif
        </div>
      </li>
    @endforeach
  </ol>
  <x-ctas :list="$d['ctas'] ?? []" />
</x-section>

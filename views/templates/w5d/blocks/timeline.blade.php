<x-section :d="$d">
  <ol class="relative border-s border-slate-300 dark:border-white/15 ms-3 space-y-8 max-w-3xl">
    @foreach(($d['items'] ?? []) as $it)
      <li data-rvi="up" class="ps-6 relative">
        <span class="absolute -start-[7px] top-1.5 w-3 h-3 rounded-full bg-brand-500 ring-4 ring-white dark:ring-ink-950"></span>
        <p class="text-xs font-mono text-slate-500">{{ t($it['when']) }}</p>
        <h3 class="font-display font-semibold text-slate-900 dark:text-white">{{ t($it['title']) }}</h3>
        @if(!empty($it['body']))<p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ rich($it['body']) }}</p>@endif
      </li>
    @endforeach
  </ol>
</x-section>

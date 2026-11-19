<x-section :d="$d">
  <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
    @foreach(($d['items'] ?? []) as $x)
      <a href="{{ link_to($x['href']) }}" class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white/60 dark:bg-white/5 overflow-hidden hover:border-brand-500/60 transition">
        <x-card-media :k="'desks:' . ($d['id'] ?? '') . ':' . $loop->index" :alt="t($x['title'])" />
        <div class="p-5">
        <span class="text-brand-500">{{ icon($x['icon'] ?? 'mail', 'w-6 h-6') }}</span>
        <h3 class="font-display font-semibold mt-2 text-slate-900 dark:text-white">{{ t($x['title']) }}</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ rich($x['body']) }}</p>
        </div>
      </a>
    @endforeach
  </div>
</x-section>

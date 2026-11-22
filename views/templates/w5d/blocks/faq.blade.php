<x-section :d="$d">
  <div class="max-w-3xl divide-y divide-slate-200 dark:divide-white/10 border-y border-slate-200 dark:border-white/10">
    @foreach(($d['items'] ?? []) as $i => $q)
      <div data-faq data-rvi="up">
        <button type="button" data-faq-btn class="w-full flex items-center justify-between gap-4 py-4 text-start font-semibold text-slate-900 dark:text-white" aria-expanded="false">
          <span>{{ t($q['q']) }}</span><span data-faq-icon class="transition-transform">{{ icon('plus', 'w-5 h-5') }}</span>
        </button>
        <div data-faq-panel class="hidden pb-4 text-slate-600 dark:text-slate-400 text-sm"><p>{{ rich($q['a']) }}</p></div>
      </div>
    @endforeach
  </div>
  <x-ctas :list="$d['ctas'] ?? []" />
</x-section>

<x-section :d="$d" pad="py-10">
  <aside class="rounded-2xl border border-amber-500/40 bg-amber-500/10 p-6 max-w-4xl" role="note">
    <p class="font-display font-semibold flex items-center gap-2 text-amber-700 dark:text-amber-300">{{ icon('scale', 'w-5 h-5') }} {{ t($d['title'] ?? __('limits.title')) }}</p>
    <ul class="mt-3 space-y-2 text-sm text-slate-700 dark:text-slate-300 list-disc ps-5">
      @foreach(($d['items'] ?? [__('limits.jobs'), __('limits.design'), __('limits.returns')]) as $it)<li>{{ rich($it) }}</li>@endforeach
    </ul>
    <a class="btn-link mt-4" href="{{ url($d['href'] ?? __('limits.link')) }}">{{ __('cta.read_limits') }} {{ icon('arrow-right', 'w-4 h-4 rtl:rotate-180') }}</a>
  </aside>
</x-section>

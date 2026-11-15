<x-section :d="$d">
  <figure class="max-w-3xl mx-auto text-center">
    {{ icon('quote', 'w-8 h-8 mx-auto text-brand-500') }}
    <blockquote class="font-display text-2xl sm:text-3xl font-semibold mt-4 text-slate-900 dark:text-white leading-snug">{{ rich($d['text'] ?? '') }}</blockquote>
    <figcaption class="mt-5 text-sm text-slate-500">{{ t($d['by'] ?? '') }}</figcaption>
  </figure>
  <x-ctas :list="$d['ctas'] ?? []" class="mt-6 justify-center" />
</x-section>

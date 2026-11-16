@php
  $all = \W5D\Content::all('projects');
  $only = $d['only'] ?? null; $limit = (int)($d['limit'] ?? 6);
  $list = $only ? array_values(array_filter(array_map(fn($s) => $all[$s] ?? null, $only))) : array_values($all);
  $list = array_slice($list, 0, $limit);
@endphp
<x-section :d="$d">
  <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
    @foreach($list as $p)
      <a href="{{ url('/projects/' . $p['slug'] . '/') }}" class="group rounded-2xl border border-slate-200 dark:border-white/10 bg-white/60 dark:bg-white/5 p-6 flex flex-col gap-3 hover:-translate-y-0.5 hover:border-brand-500/60 transition">
        <div class="flex items-center justify-between"><span class="inline-flex w-10 h-10 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500">{{ icon($p['icon'] ?? 'folder', 'w-5 h-5') }}</span><span class="tier-badge">{{ t($p['status_label']) }}</span></div>
        <h3 class="font-display font-semibold text-lg text-slate-900 dark:text-white">{{ t($p['name']) }}</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400">{{ t($p['tagline']) }}</p>
        <span class="mt-auto text-sm font-semibold text-brand-600 dark:text-brand-300 inline-flex items-center gap-1">{{ __('cta.view_project') }} {{ icon('arrow-right', 'w-4 h-4 rtl:rotate-180') }}</span>
      </a>
    @endforeach
  </div>
  <x-ctas :list="$d['ctas'] ?? []" />
</x-section>

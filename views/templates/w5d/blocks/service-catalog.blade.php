@php $macro = $d['macro'] ?? null; $list = $macro ? \W5D\Content::servicesOf($macro) : \W5D\Content::all('services'); @endphp
<x-section :d="$d">
  <div class="mb-4"><label class="sr-only" for="svc-filter">{{ __('lbl.search') }}</label><input id="svc-filter" type="search" class="field max-w-sm" placeholder="{{ __('lbl.search') }}" data-filter="#svc-grid"></div>
  <ul id="svc-grid" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @foreach($list as $s)
      <li data-filter-item="{{ mb_strtolower(t($s['title']) . ' ' . $s['slug']) }}">
        <a href="{{ url('/services/' . $s['slug'] . '/') }}" class="flex gap-3 rounded-xl border border-slate-200 dark:border-white/10 bg-white/60 dark:bg-white/5 p-4 hover:border-brand-500/60 transition h-full">
          <span class="font-mono text-xs text-slate-500 pt-0.5">#{{ str_pad((string)$s['id'], 3, '0', STR_PAD_LEFT) }}</span>
          <span><span class="block font-semibold text-slate-900 dark:text-white text-sm">{{ t($s['title']) }}</span><span class="block text-xs text-slate-500 mt-1">{{ t($s['short']) }}</span></span>
        </a>
      </li>
    @endforeach
  </ul>
</x-section>

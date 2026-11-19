@php $macros = \W5D\Content::all('macros'); @endphp
<x-section :d="$d">
  <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-5">
    @foreach($macros as $m)
      <a href="{{ url('/macro-works/' . $m['slug'] . '/') }}" class="on-grad rounded-2xl overflow-hidden flex flex-col min-h-[15rem] transition hover:-translate-y-1" style="--grad: {{ g_macro($m['slug']) }}">
        <x-card-media :k="'macro:' . $m['slug']" :alt="t($m['name'])" />
        <div class="p-5 flex flex-col gap-3 flex-1">
        {{ icon($m['icon'], 'w-8 h-8') }}
        <h3 class="font-display font-bold text-xl">{{ t($m['name']) }}</h3>
        <p class="text-sm text-white/90">{{ t($m['tagline']) }}</p>
        <span class="mt-auto flex items-center justify-between text-sm font-semibold"><span class="rounded-full bg-black/20 px-2.5 py-1 text-xs">{{ \W5D\Variables::get('experts.services_per_macro') }} {{ __('nav.services_unit') }}</span><span class="inline-flex items-center gap-1">{{ __('cta.explore') }} {{ icon('arrow-right', 'w-4 h-4 rtl:rotate-180') }}</span></span>
        </div>
      </a>
    @endforeach
  </div>
  <x-ctas :list="$d['ctas'] ?? []" />
</x-section>

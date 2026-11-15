@php $macros = \W5D\Content::all('macros'); @endphp
<x-section :d="$d">
  <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-5">
    @foreach($macros as $m)
      <a href="{{ url('/macro-works/' . $m['slug'] . '/') }}" class="on-grad rounded-2xl p-5 flex flex-col gap-3 min-h-[15rem] transition hover:-translate-y-1" style="--grad: {{ g_macro($m['slug']) }}">
        {{ icon($m['icon'], 'w-8 h-8') }}
        <h3 class="font-display font-bold text-xl">{{ t($m['name']) }}</h3>
        <p class="text-sm text-white/90">{{ t($m['tagline']) }}</p>
        <span class="mt-auto text-sm font-semibold inline-flex items-center gap-1">{{ \W5D\Variables::get('experts.services_per_macro') }} {{ __('nav.services') !== '' ? '' : '' }}{{ __('cta.explore') }} {{ icon('arrow-right', 'w-4 h-4 rtl:rotate-180') }}</span>
      </a>
    @endforeach
  </div>
  <x-ctas :list="$d['ctas'] ?? []" />
</x-section>

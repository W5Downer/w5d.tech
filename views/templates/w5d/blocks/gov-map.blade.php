@php $regs = \W5D\Content::file('countries/tn.json')['regions'] ?? []; @endphp
<x-section :d="$d">
  <ul class="grid gap-2 grid-cols-2 sm:grid-cols-3 lg:grid-cols-6">
    @foreach($regs as $r)
      <li><a class="gov-tile block rounded-xl border border-slate-200 dark:border-white/10 bg-white/60 dark:bg-white/5 px-3 py-2.5 text-sm font-medium" href="{{ url('/tn/' . $r['slug'] . '/') }}">{{ t($r['name']) }}</a></li>
    @endforeach
  </ul>
  <x-ctas :list="$d['ctas'] ?? []" />
</x-section>

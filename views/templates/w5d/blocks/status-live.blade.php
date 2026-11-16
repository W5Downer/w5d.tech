@php $v = \W5D\Variables::all(); $pr = $v['price']; @endphp
<x-section :d="$d">
  <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 text-sm">
    @foreach([
      ['Experts certified', num($v['d']['experts_current'])], ['Design ceiling', num($v['d']['experts_target'])],
      ['Developers delivering', num($v['experts']['developers_today'])], ['Progress p', number_format($pr['p']*100, 2) . '%'],
      ['Time t', number_format($pr['t']*100, 2) . '%'], ['Price / day', number_format($pr['day'], 2) . ' ' . $pr['currency']],
      ['Price / week', number_format($pr['week'], 2) . ' ' . $pr['currency']], ['As of', $v['dates']['as_of']],
    ] as [$k, $val])
      <div class="rounded-xl border border-slate-200 dark:border-white/10 p-4"><dt class="text-slate-500">{{ $k }}</dt><dd class="font-display text-xl font-bold mt-1">{{ $val }}</dd></div>
    @endforeach
  </dl>
  <p class="text-xs text-slate-500 mt-3 font-mono"><a class="underline" href="{{ url('/api/price.json') }}">/api/price.json</a> · <a class="underline" href="{{ url('/api/kpis.json') }}">/api/kpis.json</a></p>
</x-section>

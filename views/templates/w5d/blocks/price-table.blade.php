@php $steps = [0, .25, .5, .75, 1]; @endphp
<x-section :d="$d">
  <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10">
    <table class="w5-table"><thead><tr><th>p \ t</th>@foreach($steps as $t)<th>{{ (int)($t*100) }}%</th>@endforeach</tr></thead><tbody>
      @foreach($steps as $p)<tr><th scope="row">{{ (int)($p*100) }}%</th>@foreach($steps as $t)<td class="tabular-nums">{{ number_format(\W5D\Pricing::perPart('day', $p, $t), 2) }}</td>@endforeach</tr>@endforeach
    </tbody></table>
  </div>
  <p class="text-xs text-slate-600 dark:text-slate-400 mt-3">{{ __('price.per_day') }} · {{ \W5D\Variables::get('money.currency') }} · {{ __('price.formula') }}</p>
</x-section>

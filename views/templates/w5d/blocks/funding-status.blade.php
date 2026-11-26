@php
  $t = \W5D\Funding::totals(); $ch = \W5D\Funding::all(); $gate = \W5D\Ads::canApplyDetail(); $sal = \W5D\Funding::salary();
  $salNote = ['deferred' => 'funding.salary_deferred', 'condition_met' => 'funding.salary_met', 'not_deferred' => 'funding.salary_off'][$sal['state']];
  $cur = \W5D\Variables::get('money.currency');
@endphp
<x-section :d="$d">
  <div class="max-w-4xl" data-funding-status>
    <h2 class="font-display text-2xl font-bold">{{ __('funding.title') }}</h2>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ __('funding.intro') }}</p>

    <ul class="mt-6 space-y-2 font-medium" data-status-lines>
      <li data-line="fund">{{ __('funding.fund_line') }}</li>
      <li data-line="ads">{{ __('funding.ads_line') }}</li>
      <li data-line="salary">{{ __('funding.salary_line') }}</li>
    </ul>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-salary-state="{{ $sal['state'] }}">{{ __($salNote) }}</p>
    <p class="mt-3 text-sm">{{ __('funding.revenue') }}</p>
    <p class="text-sm">{{ __('funding.withdrawal') }}</p>

    <dl class="mt-6 grid gap-4 sm:grid-cols-4 text-sm" data-fund-numbers>
      <div class="rounded-xl border border-slate-200 dark:border-white/10 p-4"><dt class="text-slate-600 dark:text-slate-400">{{ __('funding.target') }}</dt><dd class="font-display text-xl font-bold mt-1">{{ \W5D\Funding::fmt($t['required']) }} {{ $cur }}</dd></div>
      <div class="rounded-xl border border-slate-200 dark:border-white/10 p-4"><dt class="text-slate-600 dark:text-slate-400">{{ __('funding.achieved') }}</dt><dd class="font-display text-xl font-bold mt-1">{{ \W5D\Funding::fmt($t['achieved']) }} {{ $cur }}</dd></div>
      <div class="rounded-xl border border-slate-200 dark:border-white/10 p-4"><dt class="text-slate-600 dark:text-slate-400">{{ __('funding.gap') }}</dt><dd class="font-display text-xl font-bold mt-1">{{ \W5D\Funding::fmt($t['gap']) }} {{ $cur }}</dd></div>
      <div class="rounded-xl border border-slate-200 dark:border-white/10 p-4"><dt class="text-slate-600 dark:text-slate-400">{{ __('funding.escrow') }}</dt><dd class="font-display text-xl font-bold mt-1">{{ \W5D\Funding::fmt($t['f4_escrowed']) }} {{ $cur }}</dd></div>
    </dl>
    <div class="mt-3 h-2 rounded-full bg-slate-200 dark:bg-white/10 overflow-hidden" role="progressbar" aria-label="{{ __('funding.bar') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $t['pct'] }}"><i class="block h-full bg-gradient-to-r from-violet-500 to-cyan-500" style="width: {{ $t['pct'] }}%"></i></div>

    <h3 class="font-display text-lg font-semibold mt-8">{{ __('funding.ch_title') }}</h3>
    <ul class="mt-3 grid gap-2 sm:grid-cols-2" data-channels>
      @foreach($ch as $k => $c)
        <li class="rounded-xl border border-slate-200 dark:border-white/10 p-3 text-sm" data-channel="{{ $k }}" data-available="{{ $c['available'] ? '1' : '0' }}">
          <p class="font-semibold">{{ __('funding.' . $k) }} · <span class="{{ $c['available'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-300' }}">{{ $c['available'] ? __('funding.open') : __('funding.closed') }}</span></p>
          <p class="text-slate-600 dark:text-slate-300 mt-1">{{ \W5D\Funding::reasonLocal($c) }}</p>
        </li>
      @endforeach
    </ul>

    <h3 class="font-display text-lg font-semibold mt-8">{{ __('funding.gate_title') }}</h3>
    <p class="mt-2 text-sm" data-ads-gate="{{ $gate['ok'] ? '1' : '0' }}">{{ __('funding.gate_line', ['counted' => $gate['counted'], 'needed' => $gate['needed'], 'min' => \W5D\Ads::minWords()]) }} {{ \W5D\Funding::reasonLocal($gate, ['counted' => $gate['counted'], 'needed' => $gate['needed'], 'min' => \W5D\Ads::minWords()]) }}</p>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ __('funding.ads_never') }}</p>
    <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">{{ __('funding.caveat') }}</p>
    <p class="mt-4 flex flex-wrap gap-4 text-sm"><a class="btn-link" href="{{ url('/sponsor/') }}">{{ __('funding.sponsor_cta') }}</a> <a class="btn-link" href="{{ url('/trust/ads-policy/') }}">{{ __('funding.policy') }}</a></p>
  </div>
</x-section>

@extends('admin.layout')
@section('title', 'Command Center')
@section('content')
@php
  $cur = \W5D\Variables::get('money.currency');
  $max = max(1, max(array_column($series ?: [['n' => 1]], 'n')));
  $cards = [
    ['Active subscriptions', $k['subs_active'] ?? 0, 'repeat', ($k['parts_day'] ?? 0) . ' parts / day committed'],
    ['Pledged (demo, sum of schedules)', number_format((float)($k['pledged_total'] ?? 0), 2) . ' ' . $cur, 'coins', ($k['orders'] ?? 0) . ' orders · ' . ($k['investors'] ?? 0) . ' investors'],
    ['Manager applications', $k['mgr_all'] ?? 0, 'user-check', ($k['mgr_new'] ?? 0) . ' awaiting review'],
    ['Inbox', $k['inbox_new'] ?? 0, 'inbox', 'new messages · ' . ($k['newsletter'] ?? 0) . ' newsletter'],
  ];
@endphp
<header class="flex flex-wrap items-end justify-between gap-4 mb-8">
  <div><p class="eyebrow">{{ __('admin.title') }}</p><div class="flex items-center gap-3"><h1 class="font-display text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">Command Center</h1><span class="hidden lg:inline-flex">{{ help_btn('admin.command-center', 'Command Center', 'start', 'page') }}</span></div><p class="text-sm text-slate-500 mt-1">Figures as of {{ \W5D\Variables::get('dates.as_of') }} · payment mode <strong>{{ \W5D\Env::get('PAYMENT_MODE', 'demo') }}</strong></p></div>
  @if($canSeed)
  <div class="flex gap-2">
    <form method="post" action="{{ admin_url('seed') }}">{!! csrf_field() !!}<input type="hidden" name="do" value="seed"><button class="btn-focus text-sm" type="submit">{{ icon('database', 'w-4 h-4') }} {{ __('admin.demo_data') }}</button></form>
    <form method="post" action="{{ admin_url('seed') }}">{!! csrf_field() !!}<input type="hidden" name="do" value="purge"><button class="btn-ghost text-sm" type="submit">Purge demo</button></form>
  </div>
  @endif
</header>
@if(!$db)<p class="mb-6 rounded-lg border border-red-500/40 bg-red-500/10 p-4 text-sm">Database unavailable. Run <code>php bin/migrate.php</code>.</p>@endif

<section class="mb-6 rounded-2xl border border-violet-500/30 bg-violet-500/5 p-5" aria-label="{{ tr3('Your desk', 'Votre bureau', 'مكتبك') }}" data-desk-role="{{ $user['role'] ?? '' }}">
  <p class="eyebrow">{{ tr3('Your desk', 'Votre bureau', 'مكتبك') }} · {{ t($desk['label']) }}</p>
  <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ t($desk['blurb']) }}</p>
  @if($desk['queues'])
  <ul class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach($desk['queues'] as $qq)<li><a class="block rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-3 hover:border-violet-500/50" href="{{ $qq['href'] }}"><span class="block font-display text-2xl font-bold">{{ $qq['count'] }}</span><span class="text-xs text-slate-500">{{ t($qq['label']) }}</span></a></li>@endforeach
  </ul>
  @endif
  <ul class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach($desk['widgets'] as [$wi, $wl, $wb])<li class="rounded-xl border border-slate-200 dark:border-white/10 p-3"><p class="flex items-center gap-2 text-sm font-semibold">{{ icon($wi, 'w-4 h-4 text-violet-500') }} {{ t($wl) }}</p><p class="mt-1 text-xs text-slate-500">{{ t($wb) }}</p></li>@endforeach
  </ul>
  <p class="mt-4 text-sm"><a class="btn-link" href="{{ admin_url('api-docs/') }}">{{ icon('code', 'w-4 h-4') }} {{ tr3('API and documentation for my role', 'API et documentation pour mon rôle', 'واجهة API ووثائق دوري') }}</a></p>
</section>

<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Key figures">
  @foreach($cards as [$label, $val, $ic, $sub])
    <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-5">
      <div class="flex items-center justify-between text-slate-500 text-xs uppercase tracking-wider">{{ $label }} {{ icon($ic, 'w-4 h-4') }}</div>
      <p class="font-display text-3xl font-bold mt-2 text-slate-900 dark:text-white">{{ $val }}</p><p class="text-xs text-slate-500 mt-1">{{ $sub }}</p>
    </div>
  @endforeach
</section>

<section class="grid gap-6 lg:grid-cols-3 mt-6">
  <div class="lg:col-span-2 rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-5">
    <h2 class="font-display font-semibold text-slate-900 dark:text-white">Orders, last 14 days</h2>
    <svg viewBox="0 0 280 90" class="w-full mt-4" role="img" aria-label="Orders per day, last 14 days">
      @foreach($series as $i => $p)
        @php $h = $p['n'] / $max * 70; @endphp
        <rect x="{{ $i * 20 + 2 }}" y="{{ 78 - $h }}" width="14" height="{{ max(1.5, $h) }}" rx="2" class="fill-brand-500"><title>{{ $p['d'] }}: {{ $p['n'] }}</title></rect>
        <text x="{{ $i * 20 + 9 }}" y="88" text-anchor="middle" font-size="6" class="fill-slate-500">{{ substr($p['d'], 8) }}</text>
      @endforeach
    </svg>
  </div>
  <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-5">
    <h2 class="font-display font-semibold text-slate-900 dark:text-white">Price today</h2>
    <p class="font-display text-4xl font-bold mt-3">{{ number_format($price['day'], 2) }} <span class="text-base font-medium">{{ $price['currency'] }}</span></p>
    <p class="text-xs text-slate-500">per part per day · week {{ number_format($price['week'], 2) }}</p>
    <dl class="grid grid-cols-2 gap-2 text-sm mt-4"><div><dt class="text-slate-500">p (progress)</dt><dd class="font-mono">{{ number_format($price['p'], 4) }}</dd></div><div><dt class="text-slate-500">t (time)</dt><dd class="font-mono">{{ number_format($price['t'], 4) }}</dd></div></dl>
    <a class="btn-focus mt-4 text-sm" href="{{ admin_url('progressive-pricing/') }}">{{ icon('trending-up', 'w-4 h-4') }} Progressive Pricing</a>
  </div>
</section>

<section class="grid gap-6 lg:grid-cols-2 mt-6">
  <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-5 overflow-x-auto">
    <h2 class="font-display font-semibold mb-3 text-slate-900 dark:text-white">Latest orders</h2>
    @if($recentOrders)<table class="w5-table"><thead><tr><th>Ref</th><th>Investor</th><th>Parts × days</th><th class="text-end">Total</th></tr></thead><tbody>
      @foreach($recentOrders as $o)<tr><td class="font-mono text-xs">{{ $o['ref'] }}</td><td>{{ $o['full_name'] }}</td><td>{{ $o['parts_per_day'] }} × {{ $o['days'] }}</td><td class="text-end tabular-nums">{{ number_format((float)$o['total'], 2) }}</td></tr>@endforeach
    </tbody></table>@else<p class="text-sm text-slate-500">{{ __('lbl.none') }}</p>@endif
  </div>
  <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-5 overflow-x-auto">
    <div class="flex items-center justify-between mb-3"><h2 class="font-display font-semibold text-slate-900 dark:text-white">Manager shortlist (by triage score)</h2><a class="btn-link text-xs" href="{{ admin_url('manager-applications/') }}">All</a></div>
    @if($topManagers)<table class="w5-table"><thead><tr><th>Candidate</th><th>Macro</th><th>Yrs / team</th><th class="text-end">Score</th></tr></thead><tbody>
      @foreach($topManagers as $m)<tr><td>{{ $m['full_name'] }}<span class="block font-mono text-[0.65rem] text-slate-500">{{ $m['ref'] }}</span></td><td>{{ $m['macro_slug'] }}</td><td>{{ $m['years'] }} / {{ $m['team_size_led'] }}</td><td class="text-end font-bold tabular-nums">{{ $m['score'] }}</td></tr>@endforeach
    </tbody></table>@else<p class="text-sm text-slate-500">{{ __('lbl.none') }}</p>@endif
    <p class="text-xs text-slate-500 mt-3">Score is triage only: experience, team size, scenario rubric. A human decides.</p>
  </div>
</section>

<section class="mt-6 rounded-2xl border border-amber-500/40 bg-amber-500/10 p-5 text-sm">
  <h2 class="font-display font-semibold flex items-center gap-2">{{ icon('scale', 'w-4 h-4') }} Honest numbers (public KPIs read rollups only)</h2>
  <p class="mt-2">Experts certified: <strong>{{ $d['experts_current'] }}</strong> of a design target of {{ num($d['experts_target']) }} ({{ $d['progress_pct'] }}%). Developers delivering today: {{ \W5D\Variables::get('experts.developers_today') }}. Registered / verified / certified (rollup {{ $rollup['as_of'] ?? 'none yet' }}): {{ $rollup['registered'] }} / {{ $rollup['verified'] }} / {{ $rollup['certified'] }}.</p>
  <p class="mt-1 text-slate-600 dark:text-slate-400">Demo seeding never writes rollups, so nothing shown on the public site is inflated.</p>
</section>
@endsection

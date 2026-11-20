@extends('layouts.app')
@section('title', $title . ' · W5D')
@section('description', __('acct.guest_lead'))
@section('robots', 'noindex,nofollow')
@section('content')
@php
  $nav = [
    'overview' => ['layout-dashboard', __('acct.overview')], 'orders' => ['coins', __('acct.orders')], 'payments' => ['smartphone', __('acct.payments')],
    'applications' => ['badge-check', __('acct.applications')], 'qcm' => ['list-checks', tr3('QCM rounds', 'Sessions QCM', 'جولات الاختبار')], 'certificates' => ['award', tr3('Certificates', 'Certificats', 'الشهادات')],
    'affiliate' => ['share-2', tr3('Affiliate', 'Affiliation', 'الإحالة')], 'notifications' => ['bell', __('acct.notifications')],
    'profile' => ['user-round', __('acct.profile')], 'security' => ['shield-check', __('acct.security')],
    'api' => ['code', tr3('API & docs', 'API et docs', 'API والوثائق')],
  ];
  $unread = \W5D\Account::unread();
  $stl = fn($s) => match($s) { 'verified','paid','active' => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300', 'rejected','cancelled' => 'bg-red-500/15 text-red-600 dark:text-red-300', default => 'bg-amber-500/15 text-amber-700 dark:text-amber-300' };
  $stx = fn($s) => match($s) { 'pending' => __('acct.st_pending'), 'verified','paid' => __('acct.st_verified'), 'rejected' => __('acct.st_rejected'), 'awaiting_payment' => __('acct.st_awaiting'), default => (string)$s };
@endphp
<section class="py-10 sm:py-14">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 grid gap-8 lg:grid-cols-[16rem_1fr]">
    <aside aria-label="{{ __('acct.menu') }}">
      <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-4">
        <p class="font-display font-semibold text-slate-900 dark:text-white truncate">{{ $u['name'] }}</p>
        <p class="text-xs text-slate-500 truncate">{{ $u['email'] }}</p>
        <p class="mt-2 inline-block rounded-full px-2 py-0.5 text-xs {{ $u['email_verified_at'] ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300' : 'bg-amber-500/15' }}">{{ $u['email_verified_at'] ? __('acct.verified') : __('acct.not_verified') }}</p>
      </div>
      <nav class="mt-4 flex lg:flex-col gap-1 overflow-x-auto" aria-label="{{ __('acct.dashboard') }}">
        @foreach($nav as $k => [$ic, $lb])
          <a href="{{ url('/account/' . ($k === 'overview' ? '' : $k . '/')) }}" @if($panel === $k) aria-current="page" @endif class="flex items-center gap-2 whitespace-nowrap rounded-xl px-3 py-2 text-sm {{ $panel === $k ? 'bg-violet-500/15 text-violet-700 dark:text-violet-200 font-semibold' : 'hover:bg-slate-100 dark:hover:bg-white/5' }}">
            {{ icon($ic, 'w-4 h-4') }}<span>{{ $lb }}</span>
            @if($k === 'notifications' && $unread)<span class="ms-auto rounded-full bg-violet-600 text-white text-[11px] px-1.5">{{ $unread }}</span>@endif
          </a>
        @endforeach
      </nav>
      <form method="post" action="{{ url('/account/logout/') }}" class="mt-4">{!! csrf_field() !!}<button class="btn-ghost w-full" type="submit">{{ icon('log-out', 'w-4 h-4') }} {{ __('acct.sign_out') }}</button></form>
    </aside>

    <div>
      <h1 class="font-display text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">{{ $title }}</h1>
      @if(!empty($flash))<p class="mt-4 rounded-lg p-3 text-sm {{ $flash['kind'] === 'ok' ? 'bg-emerald-500/10 border border-emerald-500/40' : 'bg-red-500/10 border border-red-500/40' }}" role="status">{{ t($flash['msg']) }}</p>@endif
      @if(!empty($errors['_form']))<p class="mt-4 rounded-lg bg-red-500/10 border border-red-500/40 p-3 text-sm" role="alert">{{ t($errors['_form']) }}</p>@endif

      @if($panel === 'overview')
        <div class="mt-6 rounded-2xl border border-slate-200 dark:border-white/10 p-5 flex flex-wrap items-center gap-4" data-dash-role="{{ $dash['role'] }}">
          <span class="inline-flex w-12 h-12 items-center justify-center rounded-xl bg-violet-500/15 text-violet-600 dark:text-violet-300">{{ icon($dash['icon'], 'w-6 h-6') }}</span>
          <div class="min-w-0"><p class="eyebrow">{{ tr3('Your dashboard', 'Votre tableau de bord', 'لوحتك') }} · {{ t($dash['label']) }}</p><p class="text-sm text-slate-600 dark:text-slate-300">{{ t($dash['blurb']) }}</p></div>
        </div>
        <div class="mt-4 rounded-2xl p-6 border border-violet-500/30 bg-gradient-to-br from-violet-500/10 to-indigo-500/10">
          <p class="eyebrow">{{ __('acct.next_action') }}</p>
          <div class="mt-2 flex items-start gap-4">
            <span class="mt-1 text-violet-500">{{ icon($next['icon'], 'w-8 h-8') }}</span>
            <div>
              <h2 class="font-display text-xl font-semibold text-slate-900 dark:text-white">{{ $next['title'] }}</h2>
              <p class="mt-1 text-slate-600 dark:text-slate-300">{{ $next['body'] }}</p>
              <p class="mt-4"><a class="btn-peak" href="{{ $next['href'] }}">{{ $next['cta'] }}</a></p>
            </div>
          </div>
        </div>
        <div class="mt-6 grid gap-4 sm:grid-cols-3">
          @foreach($dash['tiles'] as [$tl, $tv, $ts])
            <div class="rounded-2xl border border-slate-200 dark:border-white/10 p-4"><p class="text-xs text-slate-500">{{ t($tl) }}</p><p class="mt-1 text-2xl font-display font-bold break-words">{{ $tv }}</p><p class="text-xs text-slate-500">{{ t($ts) }}</p></div>
          @endforeach
        </div>
        <h2 class="mt-8 font-display text-lg font-semibold">{{ tr3('Quick actions', 'Actions rapides', 'إجراءات سريعة') }}</h2>
        <ul class="mt-3 grid gap-3 sm:grid-cols-2">
          @foreach($dash['actions'] as [$ai, $al, $ah])
            <li><a class="flex items-center gap-3 rounded-xl border border-slate-200 dark:border-white/10 p-3 hover:bg-slate-100 dark:hover:bg-white/5" href="{{ $ah }}">{{ icon($ai, 'w-5 h-5 text-violet-500') }}<span class="text-sm font-medium">{{ t($al) }}</span>{{ icon('arrow-right', 'w-4 h-4 ms-auto rtl:rotate-180') }}</a></li>
          @endforeach
        </ul>
        @if(!empty($d['notes']))
          <h2 class="mt-8 font-display text-lg font-semibold">{{ __('acct.notifications') }}</h2>
          <ul class="mt-3 space-y-2">@foreach(array_slice($d['notes'], 0, 3) as $n)<li class="rounded-xl border border-slate-200 dark:border-white/10 p-3 text-sm"><strong>{{ $n['title'] }}</strong> <span class="text-slate-500">· {{ $n['body'] }}</span></li>@endforeach</ul>
        @endif
        <p class="mt-8 text-xs text-slate-500">{{ __('limits.jobs') }}</p>

      @elseif($panel === 'api')
        <div class="mt-6">@include('partials.api-guide', ['guide' => $guide, 'tabs' => $tabs, 'as' => $as, 'tab_base' => $tab_base, 'role' => $role])</div>

      @elseif($panel === 'qcm')
        @include('account.sec-qcm')

      @elseif($panel === 'certificates')
        @include('account.sec-certificates')

      @elseif($panel === 'affiliate')
        @include('account.sec-affiliate')

      @elseif($panel === 'orders')
        @if(!$d['orders'])
          <p class="mt-6 text-slate-600 dark:text-slate-300">{{ __('acct.none_orders') }}</p><p class="mt-4"><a class="btn-peak" href="{{ url('/checkout/') }}">{{ __('acct.go_checkout') }}</a></p>
        @else
          <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10"><table class="w-full text-sm">
            <thead class="text-start text-xs uppercase text-slate-500"><tr><th class="p-3 text-start">{{ __('acct.order') }}</th><th class="p-3 text-start">{{ __('lbl.parts') }}</th><th class="p-3 text-start">{{ __('lbl.days') }}</th><th class="p-3 text-start">{{ __('acct.amount') }}</th><th class="p-3 text-start">{{ __('acct.status') }}</th></tr></thead>
            <tbody>@foreach($d['orders'] as $o)<tr class="border-t border-slate-200 dark:border-white/10"><td class="p-3 font-mono">{{ $o['ref'] }}</td><td class="p-3">{{ $o['parts_per_day'] ?? '' }}</td><td class="p-3">{{ $o['days'] ?? '' }}</td><td class="p-3">{{ number_format((float)($o['total'] ?? 0), 2) }} {{ $o['currency'] ?? '' }}</td><td class="p-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $stl($o['pay_status'] ?? $o['status']) }}">{{ $stx($o['pay_status'] ?? $o['status']) }}</span></td></tr>@endforeach</tbody>
          </table></div>
          <p class="mt-4"><a class="btn-ghost" href="{{ url('/subscription/manage/') }}">{{ __('acct.go_manage') }}</a></p>
        @endif

      @elseif($panel === 'payments')
        @if(!$d['pays'])<p class="mt-6 text-slate-600 dark:text-slate-300">{{ __('acct.none_pays') }}</p>
        @else
          <ul class="mt-6 space-y-3">@foreach($d['pays'] as $p)
            <li class="rounded-2xl border border-slate-200 dark:border-white/10 p-4 flex flex-wrap items-center gap-3 justify-between">
              <div><p class="font-mono text-sm">{{ $p['ref'] }} · {{ $p['order_ref'] }}</p><p class="text-xs text-slate-500">D17 · {{ number_format((float)$p['amount'], 2) }} {{ $p['currency'] }} · {{ $p['created_at'] }}</p>@if(!empty($p['note']))<p class="text-xs mt-1">{{ $p['note'] }}</p>@endif</div>
              <div class="flex items-center gap-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $stl($p['status']) }}">{{ $stx($p['status']) }}</span>@if($p['status'] !== 'verified')<a class="btn-ghost text-sm" href="{{ \W5D\Payments::link($p['order_ref']) }}">{{ __('acct.pay_d17') }}</a>@endif</div>
            </li>@endforeach</ul>
        @endif

      @elseif($panel === 'applications')
        @php $any = $d['applicant'] || $d['managers'] || $d['investor']; @endphp
        @if(!$any)<p class="mt-6 text-slate-600 dark:text-slate-300">{{ __('acct.none_apps') }}</p>
          <p class="mt-4 flex flex-wrap gap-3"><a class="btn-peak" href="{{ url('/app/register/') }}">{{ __('cta.register') }}</a><a class="btn-ghost" href="{{ url('/careers/managers/') }}">{{ __('cta.manager') }}</a></p>
        @else
          <ul class="mt-6 space-y-3">
            @if($d['investor'])<li class="rounded-2xl border border-slate-200 dark:border-white/10 p-4"><p class="font-semibold">{{ __('acct.kind_investor') }}</p><p class="text-xs text-slate-500">{{ $d['investor']['ref'] ?? '' }}</p></li>@endif
            @if($d['applicant'])<li class="rounded-2xl border border-slate-200 dark:border-white/10 p-4 flex justify-between"><div><p class="font-semibold">{{ __('acct.kind_applicant') }}</p><p class="text-xs text-slate-500">{{ $d['applicant']['ref'] ?? '' }}</p></div><span class="rounded-full px-2 py-0.5 text-xs h-fit {{ $stl($d['applicant']['status']) }}">{{ $d['applicant']['status'] }}</span></li>@endif
            @foreach($d['managers'] as $m)<li class="rounded-2xl border border-slate-200 dark:border-white/10 p-4 flex justify-between"><div><p class="font-semibold">{{ __('acct.kind_manager') }} · {{ $m['macro_slug'] }}</p><p class="text-xs text-slate-500">{{ $m['ref'] }}</p></div><span class="rounded-full px-2 py-0.5 text-xs h-fit {{ $stl($m['status']) }}">{{ $m['status'] }}</span></li>@endforeach
          </ul>
        @endif

      @elseif($panel === 'notifications')
        @if(!$d['notes'])<p class="mt-6 text-slate-600 dark:text-slate-300">{{ __('acct.none_notes') }}</p>
        @else<ul class="mt-6 space-y-2">@foreach($d['notes'] as $n)<li class="rounded-xl border border-slate-200 dark:border-white/10 p-3"><p class="font-semibold text-sm">{{ $n['title'] }}</p><p class="text-sm text-slate-600 dark:text-slate-300">{{ $n['body'] }}</p><p class="text-xs text-slate-500 mt-1">{{ $n['created_at'] }}@if(!empty($n['href'])) · <a class="btn-link" href="{{ url($n['href']) }}">{{ __('cta.open') }}</a>@endif</p></li>@endforeach</ul>@endif

      @elseif($panel === 'profile')
        <form method="post" action="{{ url('/account/profile/') }}" class="mt-6 max-w-xl space-y-4" novalidate>
          {!! csrf_field() !!}<input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
          <x-form-field name="name" :label="__('acct.name')" :required="true" :value="$u['name']" :errors="$errors" />
          <div><label class="block text-sm font-medium mb-1">{{ __('acct.email') }}</label><input class="field" value="{{ $u['email'] }}" disabled></div>
          <x-form-field name="phone" :label="__('acct.phone')" type="tel" :value="$u['phone'] ?? ''" :errors="$errors" />
          <x-form-field name="lang" :label="__('acct.language')" type="select" :options="\W5D\Lang::names()" :value="$u['lang'] ?? lang()" :errors="$errors" />
          <p class="text-xs text-slate-500">{{ __('acct.member_since') }}: {{ substr((string)$u['created_at'], 0, 10) }}</p>
          <button type="submit" class="btn-peak">{{ __('acct.save') }}</button>
        </form>

      @elseif($panel === 'security')
        <form method="post" action="{{ url('/account/security/') }}" class="mt-6 max-w-xl space-y-4" novalidate>
          {!! csrf_field() !!}<input type="hidden" name="do" value="password"><input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
          <h2 class="font-display text-lg font-semibold">{{ __('acct.sec_password') }}</h2>
          <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('acct.sec_tip') }}</p>
          @if(!empty($u['password_hash']))<x-form-field name="current" :label="__('acct.current')" type="password" :required="true" :errors="$errors" />@endif
          <x-form-field name="password" :label="__('acct.new_password')" type="password" autocomplete="new-password" :required="true" :hint="__('acct.pw_hint')" :errors="$errors" />
          <x-form-field name="password2" :label="__('acct.password2')" type="password" autocomplete="new-password" :required="true" :errors="$errors" />
          <button type="submit" class="btn-peak">{{ __('acct.save') }}</button>
        </form>
        <form method="post" action="{{ url('/account/security/') }}" class="mt-8 max-w-xl">
          {!! csrf_field() !!}<input type="hidden" name="do" value="signout-all"><input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
          <h2 class="font-display text-lg font-semibold">{{ __('acct.sec_sessions') }}</h2>
          <button type="submit" class="btn-ghost mt-3">{{ __('acct.sign_out_all') }}</button>
        </form>
      @endif
    </div>
  </div>
</section>
@endsection

@extends('layouts.app')
@section('title', $title . ' · W5D')
@section('description', __('acct.pay_steps'))
@section('robots', 'noindex,nofollow')
@section('content')
@php
  $stl = match($p['status']) { 'verified' => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300', 'rejected' => 'bg-red-500/15 text-red-600 dark:text-red-300', default => 'bg-amber-500/15 text-amber-700 dark:text-amber-300' };
  $stx = match($p['status']) { 'verified' => __('acct.st_verified'), 'rejected' => __('acct.st_rejected'), default => __('acct.st_pending') };
@endphp
<section class="py-14 sm:py-20">
  <div class="mx-auto max-w-xl px-4 sm:px-6">
    <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-6 sm:p-8">
      <p class="eyebrow">{{ $o['ref'] }} · {{ $p['ref'] }}</p>
      <h1 class="font-display text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">{{ $online ? __('acct.pay_online_title') : ($m === 'bank' ? __('acct.pay_bank_title') : __('acct.pay_title')) }} · {{ $title }}</h1>
      <p class="mt-3 text-slate-600 dark:text-slate-300">{{ $online ? __('acct.pay_online_steps') : __('acct.pay_steps_generic') }}</p>
      @if(!empty($flash))<p class="mt-4 rounded-lg p-3 text-sm bg-emerald-500/10 border border-emerald-500/40" role="status">{{ t($flash['msg']) }}</p>@endif
      @if(!empty($errors['_form']))<p class="mt-4 rounded-lg bg-red-500/10 border border-red-500/40 p-3 text-sm" role="alert">{{ t($errors['_form']) }}</p>@endif

      <dl class="mt-6 grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
        @if(!$online)
          @foreach($ins['rows'] as $row)
          <dt class="text-slate-500">{{ $row[0] }}</dt><dd class="font-semibold" dir="auto">{{ $row[1] }}@if($row[2]) <button type="button" class="btn-link text-xs ms-2" data-copy-text="{{ $row[1] }}">{{ __('cta.copy') }}</button>@endif</dd>
          @endforeach
        @else
          <dt class="text-slate-500">{{ __('acct.pay_amount') }}</dt><dd class="font-semibold">{{ number_format((float)$p['amount'], 2) }} {{ $p['currency'] }}</dd>
        @endif
        <dt class="text-slate-500">{{ __('acct.status') }}</dt><dd><span class="rounded-full px-2 py-0.5 text-xs {{ $stl }}">{{ $stx }}</span>@if($p['status'] === 'rejected' && $p['note']) <span class="text-xs">{{ $p['note'] }}</span>@endif</dd>
        @if(!$online && $p['txn_ref'])<dt class="text-slate-500">{{ __('acct.pay_txn') }}</dt><dd class="font-mono">{{ $p['txn_ref'] }}</dd>@endif
      </dl>
      @if(!$online && !empty($ins['note']))<p class="mt-4 text-xs text-slate-500">{{ $ins['note'] }}</p>@endif

      @if($p['status'] !== 'verified' && $online)
      <form method="post" action="{{ url('/pay/' . $o['ref'] . '/') . '?s=' . $sig }}" class="mt-6 space-y-3" novalidate>
        {!! csrf_field() !!}<input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
        @if(!empty($waiting))<p class="rounded-lg p-3 text-sm bg-amber-500/10 border border-amber-500/40" role="status">{{ __('acct.pay_checking') }}</p>@endif
        <button type="submit" class="btn-peak w-full">{{ icon('lock', 'w-4 h-4') }} {{ $p['status'] === 'rejected' ? __('acct.pay_retry') : __('acct.pay_open') }} · {{ $title }}</button>
        @if($p['provider_ref'] && $p['status'] === 'pending')<button type="submit" name="do" value="check" class="btn-ghost w-full">{{ __('acct.pay_recheck') }}</button>@endif
      </form>
      @elseif($p['status'] !== 'verified')
      <form method="post" action="{{ url('/pay/' . $o['ref'] . '/') . '?s=' . $sig }}" class="mt-6 space-y-4" novalidate>
        {!! csrf_field() !!}<input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
        <x-form-field name="sender_gsm" :label="$m === 'd17' ? __('acct.pay_sender') : tr3('Sender name or account', 'Nom ou compte de l’émetteur', 'اسم أو حساب المرسل')" :type="$m === 'd17' ? 'tel' : 'text'" :required="true" :value="$p['sender_gsm'] ?? ($m === 'd17' ? ($o['phone'] ?? '') : '')" :errors="$errors" />
        <x-form-field name="txn_ref" :label="__('acct.pay_txn')" :required="true" :value="$p['txn_ref'] ?? ''" :errors="$errors" />
        <button type="submit" class="btn-peak w-full">{{ __('acct.pay_submit') }}</button>
      </form>
      @endif
      <p class="mt-6 text-xs text-slate-500">{{ __('limits.returns') }}</p>
    </div>
  </div>
</section>
@endsection

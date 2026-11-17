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
      <h1 class="font-display text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">{{ __('acct.pay_title') }}</h1>
      <p class="mt-3 text-slate-600 dark:text-slate-300">{{ __('acct.pay_steps') }}</p>
      @if(!empty($flash))<p class="mt-4 rounded-lg p-3 text-sm bg-emerald-500/10 border border-emerald-500/40" role="status">{{ t($flash['msg']) }}</p>@endif
      @if(!empty($errors['_form']))<p class="mt-4 rounded-lg bg-red-500/10 border border-red-500/40 p-3 text-sm" role="alert">{{ t($errors['_form']) }}</p>@endif

      <dl class="mt-6 grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
        <dt class="text-slate-500">{{ __('acct.pay_to') }}</dt><dd class="font-mono font-semibold" dir="ltr">{{ $d17['gsm'] }} <button type="button" class="btn-link text-xs ms-2" data-copy-text="{{ $d17['gsm'] }}">{{ __('cta.copy') }}</button></dd>
        <dt class="text-slate-500">{{ __('acct.pay_holder') }}</dt><dd>{{ $d17['name'] }}</dd>
        <dt class="text-slate-500">{{ __('acct.pay_amount') }}</dt><dd class="font-semibold">{{ number_format((float)$d17['amount'], 2) }} {{ $p['currency'] }}</dd>
        <dt class="text-slate-500">{{ __('acct.status') }}</dt><dd><span class="rounded-full px-2 py-0.5 text-xs {{ $stl }}">{{ $stx }}</span>@if($p['status'] === 'rejected' && $p['note']) <span class="text-xs">{{ $p['note'] }}</span>@endif</dd>
        @if($p['txn_ref'])<dt class="text-slate-500">{{ __('acct.pay_txn') }}</dt><dd class="font-mono">{{ $p['txn_ref'] }}</dd>@endif
      </dl>
      @if(!empty($d17['note']))<p class="mt-4 text-xs text-slate-500">{{ $d17['note'] }}</p>@endif

      @if($p['status'] !== 'verified')
      <form method="post" action="{{ url('/pay/' . $o['ref'] . '/') . '?s=' . $sig }}" class="mt-6 space-y-4" novalidate>
        {!! csrf_field() !!}<input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
        <x-form-field name="sender_gsm" :label="__('acct.pay_sender')" type="tel" :required="true" :value="$p['sender_gsm'] ?? ($o['phone'] ?? '')" :errors="$errors" />
        <x-form-field name="txn_ref" :label="__('acct.pay_txn')" :required="true" :value="$p['txn_ref'] ?? ''" :errors="$errors" />
        <button type="submit" class="btn-peak w-full">{{ __('acct.pay_submit') }}</button>
      </form>
      @endif
      <p class="mt-6 text-xs text-slate-500">{{ __('limits.returns') }}</p>
    </div>
  </div>
</section>
@endsection

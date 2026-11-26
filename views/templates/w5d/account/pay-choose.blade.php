@extends('layouts.app')
@section('title', $title . ' · W5D')
@section('description', __('acct.pay_steps'))
@section('robots', 'noindex,nofollow')
@section('chromeless', '1')
@section('content')
<section class="py-14 sm:py-20">
  <div class="mx-auto max-w-xl px-4 sm:px-6">
    <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-6 sm:p-8">
      <p class="eyebrow">{{ $inv['number'] }}</p>
      <h1 class="font-display text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">{{ $title }}</h1>
      @if(!empty($errors['_form']))<p class="mt-4 rounded-lg bg-red-500/10 border border-red-500/40 p-3 text-sm" role="alert">{{ t($errors['_form']) }}</p>@endif
      <ul class="mt-5 divide-y divide-slate-200 dark:divide-white/10 text-sm">
        @foreach($lines as $l)<li class="py-2 flex justify-between gap-4"><span dir="auto">{{ \W5D\Billing::desc((string)$l['description']) }}</span><span class="tabular-nums whitespace-nowrap">{{ number_format((float)$l['amount'], 2) }} {{ $inv['currency'] }}</span></li>@endforeach
        <li class="py-2 flex justify-between gap-4 font-semibold"><span>{{ tr3('Total', 'Total', 'المجموع') }}</span><span class="tabular-nums">{{ number_format((float)$inv['amount'], 2) }} {{ $inv['currency'] }}</span></li>
      </ul>
      <form method="post" action="{{ url('/pay/' . $ref . '/') . '?s=' . $sig }}" class="mt-6 space-y-3" novalidate>
        {!! csrf_field() !!}<input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
        <input type="hidden" name="do" value="choose">
        <fieldset data-paybox class="space-y-2"><legend class="text-sm font-semibold mb-2">{{ tr3('Pay with', 'Payer avec', 'ادفع عبر') }}</legend>
          @foreach($methods as $i => $mt)
          <label class="pm-card"><input type="radio" name="method" value="{{ $mt['id'] }}" @if(($current ?: $methods[0]['id']) === $mt['id']) checked @endif>
            <span class="min-w-0"><span class="flex items-center gap-2 font-semibold">{{ icon($mt['icon'], 'w-4 h-4') }} {{ $mt['name'] }} <span class="tier-badge">{{ $mt['online'] ? tr3('online', 'en ligne', 'عبر الإنترنت') : tr3('checked by a person', 'vérifié par une personne', 'يتحقق منه شخص') }}</span></span>
            @if($mt['line'] !== '')<span class="block text-xs text-slate-600 dark:text-slate-300 mt-1">{{ $mt['line'] }}</span>@endif</span></label>
          @endforeach
        </fieldset>
        <button type="submit" class="btn-peak w-full">{{ tr3('Continue', 'Continuer', 'متابعة') }}</button>
      </form>
      <p class="mt-6 text-xs text-slate-600 dark:text-slate-400">{{ __('limits.returns') }}</p>
    </div>
  </div>
</section>
@endsection

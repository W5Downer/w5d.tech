@extends('layouts.app')
@section('title', $title . ' · W5D')
@section('description', __('acct.guest_lead'))
@section('robots', 'noindex,nofollow')
@section('content')
@php
  $providers = $providers ?? [];
  $nextQ = !empty($next) ? '?next=' . rawurlencode($next) : '';
  $fieldErr = fn($n) => $errors[$n] ?? null;
@endphp
<section class="py-14 sm:py-20">
  <div class="mx-auto max-w-md px-4 sm:px-6">
    <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-6 sm:p-8">
      <h1 class="font-display text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">{{ $title }}</h1>

      @if(!empty($flash))
        <p class="mt-4 rounded-lg p-3 text-sm {{ $flash['kind'] === 'ok' ? 'bg-emerald-500/10 border border-emerald-500/40' : ($flash['kind'] === 'info' ? 'bg-violet-500/10 border border-violet-500/40' : 'bg-red-500/10 border border-red-500/40') }}" role="status">{{ t($flash['msg']) }}</p>
      @endif
      @if(!empty($errors['_form']))
        <p class="mt-4 rounded-lg bg-red-500/10 border border-red-500/40 p-3 text-sm" role="alert">{{ t($errors['_form']) }}</p>
      @endif
      @if(!empty($devLink))
        <p class="mt-4 rounded-lg bg-amber-500/10 border border-amber-500/40 p-3 text-xs break-all" role="note">DEV ONLY (MAIL_DRIVER=log): <a class="underline" href="{{ $devLink }}">{{ $devLink }}</a></p>
      @endif

      @if($mode === 'notice')
        <p class="mt-4 text-slate-600 dark:text-slate-300">{{ t($text) }}</p>
        <p class="mt-6"><a class="btn-peak" href="{{ url('/account/login/') . $nextQ }}">{{ __('acct.login') }}</a></p>

      @elseif($mode === 'login')
        <form method="post" action="{{ url('/account/login/') . $nextQ }}" class="mt-6 space-y-4" novalidate>
          {!! csrf_field() !!}
          <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
          <x-form-field name="identifier" :label="__('acct.id_label')" type="text" :required="true" :errors="$errors" />
          <x-form-field name="password" :label="__('acct.password')" type="password" autocomplete="current-password" :required="true" :errors="$errors" />
          <x-form-field name="remember" :label="__('acct.remember')" type="checkbox" :errors="$errors" />
          <button type="submit" class="btn-peak w-full">{{ __('acct.login') }}</button>
          <p class="text-sm"><a class="btn-link" href="{{ url('/account/forgot/') }}">{{ __('acct.forgot_link') }}</a></p>
        </form>
        @if(!empty($resend))
          <form method="post" action="{{ url('/account/resend/') }}" class="mt-4 rounded-lg border border-slate-200 dark:border-white/10 p-3 text-sm">
            {!! csrf_field() !!}<input type="hidden" name="email" value="{{ $resend }}">
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
            <button type="submit" class="btn-ghost">{{ __('acct.resend') }}</button>
          </form>
        @endif
        @if($providers)
          <div class="mt-6">
            <p class="text-xs uppercase tracking-wide text-slate-500 text-center">{{ __('acct.continue_with') }}</p>
            <div class="mt-3 grid grid-cols-2 gap-2">
              @foreach($providers as $k => $p)
                <a class="btn-ghost justify-center" href="{{ url('/account/social/' . $k . '/') . $nextQ }}">{{ brand($k, 'w-4 h-4') }} {{ $p['label'] }}</a>
              @endforeach
            </div>
          </div>
        @endif
        <p class="mt-6 text-sm text-slate-600 dark:text-slate-400">{{ __('acct.no_account') }} <a class="btn-link" href="{{ url('/account/register/') . $nextQ }}">{{ __('acct.register') }}</a></p>

      @elseif($mode === 'register')
        <form method="post" action="{{ url('/account/register/') . $nextQ }}" class="mt-6 space-y-4" novalidate>
          {!! csrf_field() !!}
          <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
          <x-form-field name="name" :label="__('acct.name')" :required="true" :errors="$errors" />
          <x-form-field name="email" :label="__('acct.email')" type="email" :required="true" :errors="$errors" />
          <x-form-field name="role" :label="__('acct.role')" type="select" :options="['user' => __('acct.role_user'), 'investor' => __('acct.role_investor'), 'customer' => __('acct.role_customer')]" :errors="$errors" />
          <x-form-field name="password" :label="__('acct.password')" type="password" autocomplete="new-password" :required="true" :hint="__('acct.pw_hint')" :errors="$errors" />
          <x-form-field name="password2" :label="__('acct.password2')" type="password" autocomplete="new-password" :required="true" :errors="$errors" />
          <x-form-field name="accept" :label="__('acct.accept') . ' ([' . __('cta.read_limits') . '](/trust/statement/))'" type="checkbox" :required="true" :errors="$errors" />
          <button type="submit" class="btn-peak w-full">{{ __('acct.register') }}</button>
        </form>
        @if($providers)
          <div class="mt-6">
            <p class="text-xs uppercase tracking-wide text-slate-500 text-center">{{ __('acct.continue_with') }}</p>
            <div class="mt-3 grid grid-cols-2 gap-2">
              @foreach($providers as $k => $p)
                <a class="btn-ghost justify-center" href="{{ url('/account/social/' . $k . '/') }}">{{ brand($k, 'w-4 h-4') }} {{ $p['label'] }}</a>
              @endforeach
            </div>
          </div>
        @endif
        <p class="mt-6 text-sm text-slate-600 dark:text-slate-400">{{ __('acct.have_account') }} <a class="btn-link" href="{{ url('/account/login/') . $nextQ }}">{{ __('acct.login') }}</a></p>

      @elseif($mode === 'forgot')
        <form method="post" action="{{ url('/account/forgot/') }}" class="mt-6 space-y-4" novalidate>
          {!! csrf_field() !!}
          <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
          <x-form-field name="email" :label="__('acct.email')" type="email" :required="true" :errors="$errors" />
          <button type="submit" class="btn-peak w-full">{{ __('acct.send_link') }}</button>
        </form>
        <p class="mt-6 text-sm"><a class="btn-link" href="{{ url('/account/login/') . $nextQ }}">{{ __('acct.login') }}</a></p>

      @elseif($mode === 'reset')
        <form method="post" action="{{ url('/account/reset/' . $token . '/') }}" class="mt-6 space-y-4" novalidate>
          {!! csrf_field() !!}
          <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
          <x-form-field name="password" :label="__('acct.new_password')" type="password" autocomplete="new-password" :required="true" :hint="__('acct.pw_hint')" :errors="$errors" />
          <x-form-field name="password2" :label="__('acct.password2')" type="password" autocomplete="new-password" :required="true" :errors="$errors" />
          <button type="submit" class="btn-peak w-full">{{ __('acct.reset') }}</button>
        </form>
      @endif
    </div>
  </div>
</section>
@endsection

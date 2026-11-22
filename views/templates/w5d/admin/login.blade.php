@extends('admin.layout')
@section('title', __('admin.login'))
@section('content')
<div class="min-h-screen flex items-center justify-center p-4">
  <form method="post" action="{{ admin_url('login') }}" class="w-full max-w-sm rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-ink-900/70 p-8 space-y-4">
    {!! csrf_field() !!}
    <div class="flex items-center gap-3"><img src="{{ asset('images/logo.svg') }}" alt="" width="40" height="19" class="h-9 w-auto shrink-0" decoding="async"><h1 class="font-display text-xl font-bold text-slate-900 dark:text-white">{{ __('admin.title') }}</h1></div>
    @if(!empty($error))<p class="rounded-lg bg-red-500/10 border border-red-500/40 p-3 text-sm" role="alert">{{ $error }}</p>@endif
    <x-form-field name="username" :label="__('admin.user')" :required="true" />
    <div><label for="f-password" class="block text-sm font-medium mb-1">{{ __('admin.pass') }} <span aria-hidden="true">*</span></label><input class="field" id="f-password" name="password" type="password" required autocomplete="current-password"></div>
    {!! \W5D\Integrations::field(true) !!}
    <button class="btn-peak w-full" type="submit">{{ __('admin.login') }}</button>
    <p class="text-xs text-slate-500">Rate-limited. Every sign-in is checked against an Argon2id hash.</p>
  </form>
</div>
@endsection

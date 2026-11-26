@extends('layouts.app')
@section('title', __('lbl.manage') . ' — W5D')
@section('robots', 'noindex,follow')
@section('chromeless', '1')
@section('content')
  @foreach($sections as $s)@widget($s['type'], $s)@endforeach
  <x-section :d="['id' => 'mng-form', 'tier' => 'i', 'focus' => 3, 'title' => ['en' => 'Your subscription', 'fr' => 'Votre abonnement', 'ar' => 'اشتراكك']]">
    @if(!empty($msg))<p class="mb-4 rounded-lg bg-red-500/10 border border-red-500/40 p-3 text-sm" role="alert">{{ t($msg) }}</p>@endif
    @if(!empty($flash))<p class="mb-4 rounded-lg bg-emerald-500/10 border border-emerald-500/40 p-3 text-sm" role="status">{{ __('lbl.status') }}: {{ $flash }}</p>@endif
    @if(empty($sub))
      <form method="post" action="{{ url('/subscription/manage/') }}" class="max-w-xl rounded-2xl border border-slate-200 dark:border-white/10 bg-white/60 dark:bg-white/5 p-5 sm:p-8 space-y-4">
        {!! csrf_field() !!}
        <x-form-field name="ref" :label="__('lbl.ref')" :required="true" :value="$ref" />
        <x-form-field name="key" :label="__('lbl.token')" :required="true" />
        {!! \W5D\Integrations::field() !!}
        <button class="btn-focus" type="submit">{{ __('cta.open') }}</button>
      </form>
    @else
      <div class="max-w-2xl rounded-2xl border border-slate-200 dark:border-white/10 bg-white/60 dark:bg-white/5 p-5 sm:p-8">
        <dl class="grid sm:grid-cols-2 gap-4 text-sm">
          <div><dt class="text-slate-600 dark:text-slate-400">{{ __('lbl.ref') }}</dt><dd class="font-mono font-semibold">{{ $sub['order_ref'] }}</dd></div>
          <div><dt class="text-slate-600 dark:text-slate-400">{{ __('lbl.status') }}</dt><dd><span class="tier-badge">{{ $sub['status'] }}</span></dd></div>
          <div><dt class="text-slate-600 dark:text-slate-400">{{ __('lbl.parts') }}</dt><dd class="font-semibold">{{ $sub['parts_per_day'] }}</dd></div>
          <div><dt class="text-slate-600 dark:text-slate-400">{{ __('lbl.start') }}</dt><dd class="font-semibold">{{ $sub['start_on'] }}</dd></div>
        </dl>
        @if($sub['status'] !== 'cancelled')
        <form method="post" action="{{ url('/subscription/action/') }}" class="mt-6 flex flex-wrap gap-3">
          {!! csrf_field() !!}
          <input type="hidden" name="ref" value="{{ $sub['order_ref'] }}"><input type="hidden" name="key" value="{{ $_POST['key'] ?? '' }}">
          @if($sub['status'] === 'active')<button class="btn-focus" name="do" value="pause">{{ __('lbl.pause') }}</button>@endif
          @if($sub['status'] === 'paused')<button class="btn-focus" name="do" value="resume">{{ __('lbl.resume') }}</button>@endif
          <button class="btn-ghost" name="do" value="cancel">{{ __('lbl.cancel') }}</button>
        </form>
        @endif
      </div>
    @endif
  </x-section>
@endsection

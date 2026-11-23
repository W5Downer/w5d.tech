@extends('admin.layout')
@section('title', __('admin.login'))
@section('content')
<div class="min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-sm rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-ink-900/70 p-6 sm:p-8 space-y-4" data-staff-login data-step="{{ $step }}">
    <div class="flex items-center gap-3"><img src="{{ asset('images/logo.svg') }}" alt="" width="40" height="19" class="h-9 w-auto shrink-0" decoding="async"><h1 class="font-display text-xl font-bold text-slate-900 dark:text-white">{{ tr3('Team sign-in', 'Connexion équipe', 'دخول الفريق') }}</h1></div>
    @if(!empty($expired))<p class="rounded-lg bg-amber-500/10 border border-amber-500/40 p-3 text-sm" role="status">{{ tr3('You were signed out after a period without activity. Sign in again.', 'Vous avez été déconnecté après une période d’inactivité. Reconnectez-vous.', 'تم تسجيل خروجك بعد فترة بلا نشاط. سجّل الدخول من جديد.') }}</p>@endif
    @if(!empty($error))<p class="rounded-lg bg-red-500/10 border border-red-500/40 p-3 text-sm" role="alert">{{ $error }}</p>@endif
    @if($step === 'email')
      <p class="text-sm text-slate-600 dark:text-slate-400">{{ tr3('Owner, admins, leaders, managers and team members: enter your team e-mail address. We send a 6-digit code to it. There is no password.', 'Propriétaire, admins, leaders, managers et membres : saisissez votre adresse e-mail d’équipe. Nous y envoyons un code à 6 chiffres. Il n’y a pas de mot de passe.', 'المالك والمسؤولون والقادة والمديرون وأعضاء الفريق: أدخل بريد الفريق. نرسل إليه رمزا من 6 أرقام. لا توجد كلمة مرور.') }}</p>
      <form method="post" action="{{ admin_url('login') }}" class="space-y-4" data-once>
        {!! csrf_field() !!}<input type="hidden" name="do" value="request">
        <div><label for="f-email" class="block text-sm font-medium mb-1">{{ tr3('Team e-mail', 'E-mail d’équipe', 'بريد الفريق') }} <span aria-hidden="true">*</span></label>
        <input class="field" id="f-email" name="email" type="email" required autocomplete="username" inputmode="email" autofocus placeholder="name@{{ \W5D\StaffAuth::domain() }}" value="{{ $email }}"></div>
        {!! \W5D\Integrations::field(true) !!}
        <button class="btn-peak w-full" type="submit">{{ tr3('Send me a code', 'Envoyer un code', 'أرسل لي رمزا') }}</button>
      </form>
    @else
      <p class="text-sm text-slate-600 dark:text-slate-400">{{ tr3('If this address belongs to the team, a code was sent to', 'Si cette adresse appartient à l’équipe, un code a été envoyé à', 'إن كان هذا العنوان للفريق فقد أُرسل رمز إلى') }} <strong dir="ltr">{{ $masked }}</strong>. {{ tr3('It is valid for', 'Il est valable', 'صالح لمدة') }} {{ $ttl }} {{ tr3('minutes.', 'minutes.', 'دقائق.') }}</p>
      <form method="post" action="{{ admin_url('login') }}" class="space-y-4" data-once>
        {!! csrf_field() !!}<input type="hidden" name="do" value="verify">
        <div><label for="f-code" class="block text-sm font-medium mb-1">{{ tr3('6-digit code', 'Code à 6 chiffres', 'الرمز المكوّن من 6 أرقام') }} <span aria-hidden="true">*</span></label>
        <input class="field text-center tracking-[.4em] text-lg tabular-nums" id="f-code" name="code" type="text" required inputmode="numeric" pattern="[0-9 ]{6,8}" maxlength="8" autocomplete="one-time-code" autofocus dir="ltr"></div>
        <button class="btn-peak w-full" type="submit">{{ __('admin.login') }}</button>
      </form>
      <form method="post" action="{{ admin_url('login') }}" class="flex flex-wrap items-center justify-between gap-2 text-sm" data-once>
        {!! csrf_field() !!}<input type="hidden" name="do" value="reset">
        <button class="btn-link" type="submit">{{ tr3('Use another address / send a new code', 'Autre adresse / nouveau code', 'عنوان آخر / رمز جديد') }}</button>
      </form>
    @endif
    <p class="text-xs text-slate-500">{{ tr3('Codes are single-use and expire. Sessions end after inactivity.', 'Les codes sont à usage unique et expirent. Les sessions se ferment après inactivité.', 'الرموز لمرة واحدة وتنتهي صلاحيتها. تنتهي الجلسات بعد عدم النشاط.') }}</p>
  </div>
</div>
@endsection

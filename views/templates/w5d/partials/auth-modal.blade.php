@php
  $providers = $providers ?? [];
  $nextQ = !empty($next) ? '?next=' . rawurlencode($next) : '';
  $isLogin = ($mode ?? 'login') === 'login';
  $F = function (string $name, string $label, string $type = 'text', string $auto = '', string $hint = '') {
      $id = 'am-' . $name;
      return '<div data-field="' . $name . '"><label for="' . $id . '" class="block text-sm font-medium mb-1">' . e($label) . ' <span aria-hidden="true">*</span></label>'
        . '<input class="field" id="' . $id . '" name="' . $name . '" type="' . $type . '" required' . ($auto ? ' autocomplete="' . $auto . '"' : '')
        . ' aria-describedby="' . $id . '-err"' . '>'
        . ($hint !== '' ? '<p class="text-xs mt-1 text-slate-500 dark:text-slate-400">' . e($hint) . '</p>' : '')
        . '<p id="' . $id . '-err" class="text-xs mt-1 text-red-500" role="alert" data-err="' . $name . '"></p></div>';
  };
@endphp
<div data-auth-pane="{{ $isLogin ? 'login' : 'register' }}">
  <h2 id="authTitle" class="font-display text-xl sm:text-2xl font-bold text-slate-900 dark:text-white">{{ $isLogin ? __('ux.auth_title_login') : __('ux.auth_title_register') }}</h2>
  <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $isLogin ? __('ux.auth_lead_login') : __('ux.auth_lead_register') }}</p>
  @if(!empty($flash))
    <p class="mt-3 rounded-lg p-3 text-sm {{ $flash['kind'] === 'ok' ? 'bg-emerald-500/10 border border-emerald-500/40' : 'bg-red-500/10 border border-red-500/40' }}" role="status">{{ t($flash['msg']) }}</p>
  @endif
  <p class="mt-3 rounded-lg bg-red-500/10 border border-red-500/40 p-3 text-sm hidden" role="alert" data-form-error></p>
  <p class="mt-3 rounded-lg bg-emerald-500/10 border border-emerald-500/40 p-3 text-sm hidden" role="status" data-form-ok></p>

  @if($isLogin)
    <form method="post" action="{{ url('/account/login/') . $nextQ }}" class="mt-4 space-y-4" data-auth-form novalidate>
      {!! csrf_field() !!}
      <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
      {!! \W5D\Integrations::field() !!}
      {!! $F('identifier', __('acct.id_label'), 'text', 'username') !!}
      {!! $F('password', __('acct.password'), 'password', 'current-password') !!}
      <label class="flex items-start gap-3 text-sm" for="am-remember"><input type="checkbox" id="am-remember" name="remember" value="1" class="mt-1"><span>{{ __('acct.remember') }}</span></label>
      <button type="submit" class="btn-peak w-full" data-submit>{{ __('acct.login') }}</button>
      <p class="text-sm"><a class="btn-link" href="{{ url('/account/forgot/') }}">{{ __('acct.forgot_link') }}</a></p>
    </form>
  @else
    <form method="post" action="{{ url('/account/register/') . $nextQ }}" class="mt-4 space-y-4" data-auth-form novalidate>
      {!! csrf_field() !!}
      <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
      {!! \W5D\Integrations::field() !!}
      {!! $F('name', __('acct.name'), 'text', 'name') !!}
      {!! $F('email', __('acct.email'), 'email', 'email') !!}
      <div data-field="role"><label for="am-role" class="block text-sm font-medium mb-1">{{ __('acct.role') }}</label>
        <select class="field" id="am-role" name="role"><option value="user">{{ __('acct.role_user') }}</option><option value="investor">{{ __('acct.role_investor') }}</option><option value="customer">{{ __('acct.role_customer') }}</option></select></div>
      {!! $F('password', __('acct.password'), 'password', 'new-password', __('acct.pw_hint')) !!}
      {!! $F('password2', __('acct.password2'), 'password', 'new-password') !!}
      <div data-field="accept"><label class="flex items-start gap-3 text-sm" for="am-accept"><input type="checkbox" id="am-accept" name="accept" value="1" class="mt-1" required><span>{{ __('acct.accept') }} (<a class="btn-link" href="{{ url('/trust/statement/') }}">{{ __('cta.read_limits') }}</a>)</span></label>
        <p class="text-xs mt-1 text-red-500" role="alert" data-err="accept"></p></div>
      <button type="submit" class="btn-peak w-full" data-submit>{{ __('acct.register') }}</button>
    </form>
  @endif

  @if($providers)
    <div class="mt-5">
      <p class="text-xs uppercase tracking-wide text-slate-500 text-center">{{ __('acct.continue_with') }}</p>
      <div class="mt-2 grid grid-cols-2 gap-2">
        @foreach($providers as $k => $p)<a class="btn-ghost justify-center" href="{{ url('/account/social/' . $k . '/') . $nextQ }}">{{ brand($k, 'w-4 h-4') }} {{ $p['label'] }}</a>@endforeach
      </div>
    </div>
  @endif

  <p class="mt-5 text-sm text-slate-600 dark:text-slate-400">
    @if($isLogin){{ __('acct.no_account') }} <a class="btn-link" href="{{ url('/account/register/') . $nextQ }}" data-auth-open="register" @if(!empty($next)) data-next="{{ $next }}" @endif>{{ __('acct.register') }}</a>
    @else{{ __('acct.have_account') }} <a class="btn-link" href="{{ url('/account/login/') . $nextQ }}" data-auth-open="login" @if(!empty($next)) data-next="{{ $next }}" @endif>{{ __('acct.login') }}</a>@endif
    <span class="mx-1 text-slate-400" aria-hidden="true">·</span>
    <a class="btn-link" href="{{ url($isLogin ? '/account/login/' : '/account/register/') . $nextQ }}">{{ __('ux.auth_full') }}</a>
  </p>
</div>

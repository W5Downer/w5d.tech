@if(!\W5D\Account::signedIn())
{{-- Sign-in / register modal. Progressive enhancement: every trigger is a real link to the full page; site.js upgrades it. --}}
<dialog id="authDialog" class="w5-dialog" aria-labelledby="authTitle" data-modal-url="{{ url('/account/modal/') }}" data-loading="{{ __('ux.auth_loading') }}" data-sending="{{ __('ux.auth_sending') }}" data-error="{{ __('ux.auth_error') }}" data-redirect="{{ __('ux.auth_ok_redirect') }}">
  <div class="w5-dialog-card">
    <button type="button" class="btn-ghost rounded-lg w-9 h-9 inline-flex items-center justify-center w5-dialog-x" data-auth-close aria-label="{{ __('ux.close') }}">{{ icon('x', 'w-5 h-5') }}</button>
    <div data-auth-body aria-live="polite"><p class="text-sm text-slate-500">{{ __('ux.auth_loading') }}</p></div>
  </div>
</dialog>
@endif

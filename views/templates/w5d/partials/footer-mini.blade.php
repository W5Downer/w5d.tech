<footer class="mx-auto max-w-5xl px-4 py-8 text-center text-xs text-slate-500 space-x-3 rtl:space-x-reverse">
  <a class="btn-link" href="{{ url('/trust/privacy/') }}">{{ __('ux.mini_privacy') }}</a>
  <a class="btn-link" href="{{ url('/trust/terms/') }}">{{ __('ux.mini_terms') }}</a>
  <a class="btn-link" href="{{ url('/contact/') }}">{{ __('ux.mini_help') }}</a>
  @if(!empty((\W5D\Integrations::pageConfig() ?? [])['track']))<button type="button" class="btn-link" data-consent-open>{{ __('consent.settings') }}</button>@endif
</footer>

<footer class="mx-auto max-w-5xl px-4 py-8 text-center text-xs text-slate-600 dark:text-slate-400 space-x-3 rtl:space-x-reverse">
  <a class="btn-link" href="{{ url('/trust/privacy/') }}">{{ __('ux.mini_privacy') }}</a>
  <a class="btn-link" href="{{ url('/trust/terms/') }}">{{ __('ux.mini_terms') }}</a>
  <a class="btn-link" href="{{ url('/contact/') }}">{{ __('ux.mini_help') }}</a>
  <a class="btn-link" href="{{ url('/trust/cookies/') }}">{{ __('footer.cookies') }}</a>
  <button type="button" class="btn-link" data-cookie-open>{{ __('consent.settings') }}</button>
</footer>

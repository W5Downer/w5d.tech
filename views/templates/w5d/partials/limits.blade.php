<aside class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-5 text-sm" aria-label="{{ __('limits.title') }}">
  <p class="font-display font-semibold text-amber-600 dark:text-amber-300 flex items-center gap-2">{{ icon('scale', 'w-4 h-4') }} {{ __('limits.title') }}</p>
  <ul class="mt-2 space-y-1 text-slate-600 dark:text-slate-300">
    <li>{{ __('limits.jobs') }}</li>
    <li>{{ __('limits.design') }}</li>
    <li>{{ __('limits.returns') }} <a class="underline" href="{{ url(__('limits.link')) }}">{{ __('cta.read_limits') }}</a></li>
  </ul>
</aside>

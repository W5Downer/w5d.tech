@php $__track = !empty((\W5D\Integrations::pageConfig() ?? [])['track']); @endphp
<div class="w5-consent w5-cookies" data-cookie-notice @if($__track)data-track="1"@endif data-saved="{{ __('consent.saved') }}" hidden role="dialog" aria-modal="false" aria-labelledby="ck-title" aria-describedby="ck-body">
  <p id="ck-title" class="font-display font-semibold text-sm flex items-center gap-2">{{ icon('cookie', 'w-4 h-4 text-brand-500') }} {{ __('consent.title') }}</p>
  <p id="ck-body" class="text-xs mt-1.5 leading-relaxed">{{ rich($__track ? __('consent.body_track') : __('consent.body')) }}</p>
  <details class="mt-2 text-xs">
    <summary class="cursor-pointer font-semibold select-none">{{ __('consent.details') }}</summary>
    <ul class="mt-2 space-y-2.5">
      <li class="flex items-start justify-between gap-3"><span><strong>{{ __('consent.necessary_t') }}</strong><br><span class="opacity-80">{{ __('consent.necessary_d') }}</span></span><span class="shrink-0 rounded-full bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 px-2 py-0.5 font-semibold">{{ __('consent.always') }}</span></li>
      <li class="flex items-start justify-between gap-3"><span><strong>{{ __('consent.prefs_t') }}</strong><br><span class="opacity-80">{{ __('consent.prefs_d') }}</span></span><span class="shrink-0 rounded-full bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 px-2 py-0.5 font-semibold">{{ __('consent.always') }}</span></li>
      <li class="flex items-start justify-between gap-3"><span><strong>{{ __('consent.referral_t') }}</strong><br><span class="opacity-80">{{ __('consent.referral_d') }}</span></span><span class="shrink-0 rounded-full bg-slate-500/15 px-2 py-0.5 font-semibold">{{ __('consent.link_only') }}</span></li>
      @if($__track)
      <li class="flex items-start justify-between gap-3"><span><strong>{{ __('consent.measure_t') }}</strong><br><span class="opacity-80">{{ __('consent.measure_d') }}</span></span>
        <label class="shrink-0 inline-flex items-center gap-2 font-semibold cursor-pointer"><input type="checkbox" data-cookie-measure class="w-4 h-4 accent-violet-600"> <span>{{ __('consent.optional') }}</span></label></li>
      @else
      <li class="opacity-80">{{ __('consent.none_optional') }}</li>
      @endif
    </ul>
    <p class="mt-2"><a class="underline" href="{{ url('/trust/cookies/') }}">{{ __('consent.policy') }}</a> · <a class="underline" href="{{ url('/trust/privacy/') }}">{{ __('consent.privacy') }}</a></p>
    @if($__track)<button type="button" class="btn-focus text-sm mt-3" data-cookie-save>{{ __('consent.save') }}</button>@endif
  </details>
  <div class="mt-3 flex flex-wrap gap-2">
    @if($__track)
      <button type="button" class="btn-focus text-sm" data-cookie-all>{{ __('consent.accept') }}</button>
      <button type="button" class="btn-ghost text-sm" data-cookie-essential>{{ __('consent.refuse') }}</button>
    @else
      <button type="button" class="btn-focus text-sm" data-cookie-essential>{{ __('consent.ok') }}</button>
    @endif
  </div>
</div>
<p class="sr-only" role="status" aria-live="polite" data-cookie-live></p>
<script src="{{ asset('js/cookies.js') }}" defer></script>

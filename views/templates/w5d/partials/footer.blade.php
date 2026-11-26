@php $nav = \W5D\Content::get('nav', 'footer') ?? ['columns' => []]; @endphp
<footer class="border-t border-slate-200/60 dark:border-white/5 mt-10">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 py-14">
    @include('partials.limits')
    <div class="grid gap-10 sm:grid-cols-3 lg:grid-cols-6 mt-12">
      <div class="lg:col-span-1">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-2">
          <img src="{{ asset('images/logo.svg') }}" alt="" width="40" height="19" class="h-8 w-auto shrink-0" decoding="async">
          <span class="font-display font-bold text-lg text-slate-900 dark:text-white">W5D</span>
        </a>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-3">{{ __('brand.tagline') }}</p>
        <p class="text-xs text-slate-600 dark:text-slate-400 mt-3">{{ \W5D\Variables::get('investor.contact') }}</p>
      </div>
      @foreach($nav['columns'] as $col)
        <div>
          <p class="font-display font-semibold text-slate-900 dark:text-white text-sm mb-3">{{ t($col['heading']) }}</p>
          <ul class="space-y-2 text-sm text-slate-600 dark:text-slate-400">
            @foreach($col['items'] as $l)
              <li><a class="hover:text-brand-500" href="{{ link_to($l['href']) }}">{{ t($l['label']) }}</a></li>
            @endforeach
          </ul>
        </div>
      @endforeach
    </div>
    @php
      $lf = (string)\W5D\Variables::get('company.legal_form');
      $lf = in_array($lf, ['pending', 'auto_entrepreneur', 'company'], true) ? $lf : 'pending';
      $mail = (string)\W5D\Variables::get('company.privacy_contact');
      $legal = [
        ['/trust/privacy/', 'footer.privacy'], ['/trust/terms/', 'footer.terms'], ['/trust/cookies/', 'footer.cookies'], ['/trust/data-rights/', 'footer.data_rights'],
        ['/trust/refunds/', 'footer.refunds'], ['/trust/security/', 'footer.security'], ['/trust/accessibility/', 'footer.accessibility'], ['/trust/editorial/', 'footer.editorial'],
        ['/trust/ads-policy/', 'footer.ads'], ['/trust/imprint/', 'footer.imprint'], ['/status/', 'nav.status'], ['/sitemap/', 'footer.sitemap'],
      ];
    @endphp
    <div class="mt-12 pt-8 border-t border-slate-200/60 dark:border-white/5 grid gap-8 lg:grid-cols-12 text-sm" data-rvi="up">
      <div class="lg:col-span-5">
        <p class="font-display font-semibold text-slate-900 dark:text-white">{{ \W5D\Variables::get('company.brand') }}</p>
        <p class="mt-2 text-slate-600 dark:text-slate-400 max-w-prose">{{ __('footer.about') }}</p>
        <p class="mt-3 text-xs text-slate-600 dark:text-slate-400 max-w-prose">{{ rich(__('footer.status_' . $lf)) }}</p>
      </div>
      <div class="lg:col-span-3">
        <p class="font-display font-semibold text-slate-900 dark:text-white">{{ __('footer.contact') }}</p>
        <ul class="mt-2 space-y-1.5 text-slate-600 dark:text-slate-400">
          <li class="flex items-center gap-2">{{ icon('mail', 'w-4 h-4 text-brand-500') }} <a class="link-underline hover:text-brand-500" href="mailto:{{ $mail }}">{{ $mail }}</a></li>
          <li class="flex items-center gap-2">{{ icon('map-pin', 'w-4 h-4 text-brand-500') }} {{ \W5D\Variables::get('company.country') }}</li>
          <li class="flex items-center gap-2">{{ icon('clock', 'w-4 h-4 text-brand-500') }} {{ \W5D\Variables::get('company.hours') }}</li>
          <li class="text-xs text-slate-600 dark:text-slate-400">{{ __('footer.reply') }} <a class="underline" href="{{ url('/contact/') }}">{{ __('nav.contact') }}</a></li>
        </ul>
      </div>
      <nav class="lg:col-span-4" aria-label="{{ __('footer.legal_title') }}">
        <p class="font-display font-semibold text-slate-900 dark:text-white">{{ __('footer.legal_title') }}</p>
        <ul class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1.5 text-slate-600 dark:text-slate-400">
          @foreach($legal as $l)<li><a class="link-underline hover:text-brand-500" href="{{ url($l[0]) }}">{{ __($l[1]) }}</a></li>@endforeach
        </ul>
      </nav>
    </div>
    <div class="mt-8 pt-6 border-t border-slate-200/60 dark:border-white/5 text-xs text-slate-600 dark:text-slate-400 space-y-1.5">
      <p class="text-slate-700 dark:text-slate-300 font-medium">{{ __('footer.copyright') }}</p>
      <p>{{ __('footer.marks') }}</p>
      <p>{{ rich(__('footer.prices')) }}</p>
      <p>{{ __('footer.law') }}</p>
      <p>{{ __('footer.license') }}</p>
      <p class="flex flex-wrap items-center gap-x-5 gap-y-2 pt-2">
        <button type="button" class="btn-link text-xs" data-cookie-open>{{ icon('cookie', 'w-3.5 h-3.5') }} {{ __('consent.settings') }}</button>
        <button type="button" class="btn-link text-xs" data-motion-toggle hidden data-label-full="{{ __('ux.motion_full') }}" data-label-auto="{{ __('ux.motion_auto') }}"></button>
        <a class="btn-link text-xs" href="#main">{{ icon('arrow-up', 'w-3.5 h-3.5') }} {{ __('footer.top') }}</a>
      </p>
    </div>
  </div>
</footer>

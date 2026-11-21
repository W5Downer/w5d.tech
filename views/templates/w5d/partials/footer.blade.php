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
        <p class="text-sm text-slate-500 mt-3">{{ __('brand.tagline') }}</p>
        <p class="text-xs text-slate-500 mt-3">{{ \W5D\Variables::get('investor.contact') }}</p>
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
    <div class="mt-12 pt-6 border-t border-slate-200/60 dark:border-white/5 text-xs text-slate-500 space-y-1">
      <p>{{ __('footer.rights') }}</p>
      <p>{{ __('footer.license') }}</p>
      @if(!empty((\W5D\Integrations::pageConfig() ?? [])['track']))<p><button type="button" class="btn-link text-xs" data-consent-open>{{ __('consent.settings') }}</button></p>@endif
    </div>
  </div>
</footer>

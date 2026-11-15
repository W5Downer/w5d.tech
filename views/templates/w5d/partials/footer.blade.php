@php $nav = \W5D\Content::get('nav', 'footer') ?? ['columns' => []]; @endphp
<footer class="border-t border-slate-200/60 dark:border-white/5 mt-10">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 py-14">
    @include('partials.limits')
    <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-5 mt-12">
      <div class="lg:col-span-1">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-2">
          <span class="inline-flex w-9 h-9 rounded-xl bg-gradient-to-br from-brand-500 via-indigo-500 to-cyan-400 items-center justify-center"><span class="font-display font-bold text-white text-sm">W5</span></span>
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
    </div>
  </div>
</footer>

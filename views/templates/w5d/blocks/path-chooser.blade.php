@php $paths = \W5D\QuickPaths::paths(); @endphp
<x-section :d="$d">
  <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-path-chooser>
    @foreach($paths as $p)
      <li data-rvi="up">
        <a href="{{ $p['href'] }}" data-path="{{ $p['key'] }}" class="group spot shine h-full rounded-2xl border border-slate-200 dark:border-white/10 bg-white/60 dark:bg-white/5 p-5 flex items-start gap-4 transition hover:-translate-y-0.5 hover:border-brand-500/60">
          <span class="relative inline-flex w-11 h-11 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500 overflow-hidden chip-fx">{{ icon($p['icon'], 'w-5 h-5') }}</span>
          <span class="min-w-0"><span class="block font-display font-semibold text-slate-900 dark:text-white">{{ $p['label'] }}</span><span class="block text-sm text-slate-600 dark:text-slate-400 mt-0.5">{{ $p['desc'] }}</span></span>
          <span class="ms-auto self-center text-brand-500 opacity-0 group-hover:opacity-100 transition">{{ icon('arrow-right', 'w-4 h-4 rtl:rotate-180') }}</span>
        </a>
      </li>
    @endforeach
  </ul>
  <p class="mt-5 text-sm text-slate-600 dark:text-slate-400">{{ __('ux.find_hint') }} · <button type="button" class="btn-link" data-quick-open>{{ icon('search', 'w-4 h-4 inline') }} {{ __('ux.find_open') }}</button></p>
</x-section>

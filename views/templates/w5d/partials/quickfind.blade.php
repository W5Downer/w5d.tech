@php $qf = \W5D\QuickPaths::index(); $qp = \W5D\QuickPaths::paths(); @endphp
{{-- Quick find: press / or Ctrl+K, or tap the search button. Works as a plain list of links without JS (inside <noscript>-safe dialog). --}}
<dialog id="quickDialog" class="w5-dialog" aria-label="{{ __('ux.find') }}">
  <div class="w5-dialog-card">
    <button type="button" class="btn-ghost rounded-lg w-9 h-9 inline-flex items-center justify-center w5-dialog-x" data-quick-close aria-label="{{ __('ux.close') }}">{{ icon('x', 'w-5 h-5') }}</button>
    <h2 class="font-display text-xl font-bold text-slate-900 dark:text-white pe-10">{{ __('ux.find') }}</h2>
    <label class="sr-only" for="quickInput">{{ __('ux.find') }}</label>
    <input id="quickInput" type="search" class="field mt-3" placeholder="{{ __('ux.find_ph') }}" autocomplete="off" data-quick-input>
    <p class="text-xs text-slate-500 mt-2">{{ __('ux.find_hint') }}</p>
    <ul class="mt-3 max-h-[52vh] overflow-y-auto grid gap-1" data-quick-list>
      @foreach($qf as $row)
        <li data-quick-item="{{ mb_strtolower($row['label'] . ' ' . $row['hint']) }}"><a class="flex items-center justify-between gap-3 rounded-xl px-3 py-2 hover:bg-brand-500/10 focus:bg-brand-500/10" href="{{ $row['href'] }}"><span class="font-semibold text-slate-900 dark:text-white">{{ $row['label'] }}</span><span class="text-xs text-slate-500 truncate">{{ $row['hint'] }}</span></a></li>
      @endforeach
    </ul>
    <p class="text-sm text-slate-500 mt-3 hidden" data-quick-empty>{{ __('ux.find_empty') }}</p>
  </div>
</dialog>

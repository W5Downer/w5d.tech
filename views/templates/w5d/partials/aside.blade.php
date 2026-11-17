<div class="w5d-aside-backdrop" aria-hidden="true"></div>
<aside id="aside-lang" class="w5d-aside" aria-hidden="true" aria-label="{{ __('nav.language') }}">
  <header>
    <h3>{{ __('nav.language') }}</h3>
    <button type="button" data-aside-close class="btn-ghost rounded-lg w-9 h-9 inline-flex items-center justify-center" aria-label="Close">{{ icon('x', 'w-5 h-5') }}</button>
  </header>
  <div class="aside-body">
    <div class="lang-grid">
      @foreach(\W5D\Lang::names() as $code => $name)
        <a class="lang-item" href="{{ url('/language/' . $code) }}?r={{ rawurlencode($canonicalPath ?? '/') }}" aria-selected="{{ $code === lang() ? 'true' : 'false' }}" hreflang="{{ $code }}" lang="{{ $code }}">
          <span class="font-bold uppercase text-xs">{{ $code }}</span><span>{{ $name }}</span>
        </a>
      @endforeach
    </div>
    <p class="text-xs text-slate-500 mt-5">{{ __('nav.language') }}: cookie · no ?lang= URLs.</p>
  </div>
</aside>

@php
  $tbHere = rtrim($canonicalPath ?? '/', '/') ?: '/';
  $tbIn = \W5D\Account::signedIn() || \W5D\Auth::check();
  $tbAcct = \W5D\Account::check() ? url('/account/') : (\W5D\Auth::check() ? admin_url() : url('/account/login/'));
  $tbItems = [
    ['home', 'house', url('/'), __('ux.tab_home'), $tbHere === '/'],
    ['invest', 'coins', url('/checkout/'), __('ux.tab_invest'), (bool) preg_match('#^/(checkout|pricing|invest)#', $tbHere)],
  ];
@endphp
<nav class="w5-tabbar lg:hidden" aria-label="{{ __('ux.tab_label') }}" data-tabbar>
  @foreach($tbItems as [$k, $ic, $href, $label, $on])
    <a href="{{ $href }}" @if($on) aria-current="page" @endif>{{ icon($ic, 'w-5 h-5') }}<span>{{ $label }}</span></a>
  @endforeach
  <button type="button" data-quick-open>{{ icon('search', 'w-5 h-5') }}<span>{{ __('ux.tab_find') }}</span></button>
  <a href="{{ $tbAcct }}" @if(!$tbIn) data-auth-open="login" @endif @if(str_starts_with($tbHere, '/account')) aria-current="page" @endif>{{ icon($tbIn ? 'circle-user' : 'user-round', 'w-5 h-5') }}<span>{{ __('ux.tab_account') }}</span></a>
  <button type="button" data-tab-menu aria-controls="mobileMenu">{{ icon('menu', 'w-5 h-5') }}<span>{{ __('ux.tab_menu') }}</span></button>
</nav>

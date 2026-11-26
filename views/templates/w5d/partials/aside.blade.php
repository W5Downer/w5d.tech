<div class="w5d-aside-backdrop" aria-hidden="true"></div>
<aside id="aside-lang" class="w5d-aside" aria-hidden="true" aria-label="{{ __('nav.language') }}">
  <header>
    <h2>{{ __('nav.language') }}</h2>
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
    <p class="text-xs text-slate-600 dark:text-slate-400 mt-5">{{ __('nav.language') }}: cookie · no ?lang= URLs.</p>
  </div>
</aside>

@php
  $shUrl = \W5D\Affiliate::tagUrl(\W5D\Env::baseUrl() . url($canonicalPath ?? '/'));
  $shText = html_entity_decode(strip_tags($__env->yieldContent('title', 'W5D')), ENT_QUOTES);
  $shLinks = \W5D\Social::enabled() ? \W5D\Social::links($shUrl, $shText) : [];
  $shIds = \W5D\Social::ids();
  $uA = \W5D\Account::user();
  $uO = \W5D\Auth::check() ? \W5D\Auth::user() : null;
  $uRow = $uA ? \W5D\Account::row() : null;
  $uRole = $uO ? 'owner' : (string)($uA['role'] ?? 'guest');
  $roleLabel = ['owner' => tr3('Owner', 'Propriétaire', 'المالك'), 'admin' => tr3('Administrator', 'Administrateur', 'مسؤول'), 'leader' => tr3('Team leader', 'Chef d’équipe', 'قائد فريق'), 'manager' => tr3('Team leader', 'Chef d’équipe', 'قائد فريق'),
    'member' => tr3('Member', 'Membre', 'عضو'), 'investor' => __('acct.role_investor'), 'customer' => __('acct.role_customer'), 'user' => __('acct.role_user')][$uRole] ?? '';
  $audKey = $uRole === 'manager' ? 'leader' : $uRole;
  $audKeys = \W5D\Controllers\Pages::visibleAudiences();
  $audName = ['owner' => tr3('Owner', 'Propriétaire', 'المالك'), 'admin' => tr3('Admin', 'Admin', 'المسؤول'), 'leader' => tr3('Leader', 'Leader', 'القائد'), 'member' => tr3('Member', 'Membre', 'العضو'),
    'investor' => tr3('Investor', 'Investisseur', 'المستثمر'), 'customer' => tr3('Customer', 'Client', 'العميل'), 'user' => tr3('User', 'Utilisateur', 'المستخدم')];
  $pNote = $uA ? \W5D\Account::unread() : 0;
@endphp
@if(\W5D\Social::enabled())
<aside id="aside-share" class="w5d-aside" aria-hidden="true" aria-label="{{ __('share.title') }}">
  <header>
    <h2>{{ __('share.title') }}</h2>
    <button type="button" data-aside-close class="btn-ghost rounded-lg w-9 h-9 inline-flex items-center justify-center" aria-label="Close">{{ icon('x', 'w-5 h-5') }}</button>
  </header>
  <div class="aside-body">
    <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('share.lead') }}</p>
    <p class="mt-3 text-xs rounded-lg bg-slate-100 dark:bg-white/5 px-3 py-2 break-all" dir="ltr" data-share-url-view>{{ $shUrl }}</p>
    <ul class="share-list mt-4">
      @foreach($shIds as $id)
        @if($id === 'native')
          <li><button type="button" class="share-item" data-share-native data-share-url="{{ $shUrl }}" data-share-title="{{ $shText }}" data-share-text="{{ __('share.text') }}">{{ icon('share-2', 'w-5 h-5') }}<span>{{ __('share.native') }}</span></button></li>
        @elseif($id === 'copy')
          <li><button type="button" class="share-item" data-share-copy="{{ $shUrl }}" data-copied="{{ __('share.copied') }}">{{ icon('link', 'w-5 h-5') }}<span data-share-label>{{ __('share.copy') }}</span></button></li>
        @elseif(isset($shLinks[$id]))
          <li><a class="share-item" href="{{ $shLinks[$id]['href'] }}" target="_blank" rel="noopener noreferrer nofollow">{{ $id === 'email' ? icon('mail', 'w-5 h-5') : brand($id, 'w-5 h-5') }}<span>{{ $shLinks[$id]['label'] }}</span></a></li>
        @endif
      @endforeach
    </ul>
  </div>
</aside>
@endif

<aside id="aside-user" class="w5d-aside" aria-hidden="true" aria-label="{{ __('acct.user_aside') }}">
  <header>
    <h2>{{ __('acct.user_aside') }}</h2>
    <button type="button" data-aside-close class="btn-ghost rounded-lg w-9 h-9 inline-flex items-center justify-center" aria-label="Close">{{ icon('x', 'w-5 h-5') }}</button>
  </header>
  <div class="aside-body">
  @if($uA || $uO)
    @php
      $uName = $uO ? (string)($uO['name'] ?? 'owner') : (string)($uRow['name'] ?? $uA['name'] ?? '');
      $uMail = $uO ? '' : (string)($uA['email'] ?? '');
      $verified = $uO ? true : !empty($uRow['email_verified_at']);
      $staff = $uO || in_array($uRole, ['admin', 'leader', 'manager'], true);
    @endphp
    <div class="flex items-center gap-3">
      <span class="inline-flex w-12 h-12 rounded-2xl bg-gradient-to-br from-brand-500 via-indigo-500 to-cyan-400 text-white items-center justify-center font-display font-bold text-lg">{{ mb_strtoupper(mb_substr($uName !== '' ? $uName : 'W', 0, 1)) }}</span>
      <div class="min-w-0">
        <p class="font-display font-semibold text-slate-900 dark:text-white truncate">{{ $uName }}</p>
        @if($uMail !== '')<p class="text-xs text-slate-600 dark:text-slate-400 truncate" dir="ltr">{{ $uMail }}</p>@endif
        <p class="mt-1 flex flex-wrap gap-1.5 text-[0.7rem]"><span class="rounded-full bg-brand-500/15 text-brand-600 dark:text-brand-300 px-2 py-0.5">{{ $roleLabel }}</span>
          <span class="rounded-full px-2 py-0.5 {{ $verified ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300' : 'bg-amber-500/15 text-amber-700 dark:text-amber-300' }}">{{ $verified ? __('acct.verified') : __('acct.not_verified') }}</span></p>
      </div>
    </div>
    @if(!$verified && $uA)
      <form method="post" action="{{ url('/account/resend/') }}" class="mt-3">{!! csrf_field() !!}<button class="btn-link text-xs" type="submit">{{ __('acct.resend_btn') }}</button></form>
    @endif
    <div class="mt-4 rounded-xl border border-slate-200 dark:border-white/10 p-3 text-sm flex items-center justify-between">
      <span class="text-slate-600 dark:text-slate-400">{{ __('acct.today_price') }}</span><strong>{{ money((float)\W5D\Variables::get('price.day')) }}</strong>
    </div>
    <nav class="mt-4 grid gap-1" aria-label="{{ __('acct.menu') }}">
      @if($uA)
        <a class="aside-link" href="{{ url('/account/') }}">{{ icon('layout-dashboard', 'w-5 h-5') }}<span>{{ __('acct.dashboard') }}</span></a>
        <a class="aside-link" href="{{ url('/account/orders/') }}">{{ icon('coins', 'w-5 h-5') }}<span>{{ __('acct.orders') }}</span></a>
        <a class="aside-link" href="{{ url('/account/payments/') }}">{{ icon('smartphone', 'w-5 h-5') }}<span>{{ __('acct.payments') }}</span></a>
        <a class="aside-link" href="{{ url('/account/applications/') }}">{{ icon('badge-check', 'w-5 h-5') }}<span>{{ __('acct.applications') }}</span></a>
        <a class="aside-link" href="{{ url('/account/notifications/') }}">{{ icon('bell', 'w-5 h-5') }}<span>{{ __('acct.notifications') }}</span>@if($pNote > 0)<em class="aside-badge">{{ $pNote }}</em>@endif</a>
        <a class="aside-link" href="{{ url('/account/profile/') }}">{{ icon('user-round', 'w-5 h-5') }}<span>{{ __('acct.profile') }}</span></a>
        <a class="aside-link" href="{{ url('/account/security/') }}">{{ icon('shield-check', 'w-5 h-5') }}<span>{{ __('acct.security') }}</span></a>
      @endif
      @if($staff)<a class="aside-link" href="{{ admin_url() }}">{{ icon('layout-dashboard', 'w-5 h-5') }}<span>{{ __('acct.admin_portal') }}</span></a>@endif
      @if(in_array($audKey, $audKeys, true))<a class="aside-link" href="{{ url('/for/' . $audKey . '/') }}">{{ icon('compass', 'w-5 h-5') }}<span>{{ tr3('My W5D home', 'Mon accueil W5D', 'صفحتي في W5D') }}</span></a>@endif
      <a class="aside-link" href="{{ url('/checkout/') }}">{{ icon('coins', 'w-5 h-5') }}<span>{{ __('cta.invest') }}</span></a>
    </nav>
    <form method="post" action="{{ $uO && !$uA ? admin_url('logout') : url('/account/logout/') }}" class="mt-5">{!! csrf_field() !!}<button class="btn-ghost w-full justify-center" type="submit">{{ icon('log-out', 'w-4 h-4') }} {{ __('acct.sign_out') }}</button></form>
  @else
    <div class="text-center">
      <span class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-br from-brand-500 via-indigo-500 to-cyan-400 text-white items-center justify-center">{{ icon('user-round', 'w-7 h-7') }}</span>
      <p class="font-display font-bold text-lg mt-3 text-slate-900 dark:text-white">{{ __('acct.guest_title') }}</p>
      <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('acct.guest_lead') }}</p>
    </div>
    <div class="mt-4 grid gap-2">
      <a class="btn-focus justify-center" href="{{ url('/account/login/') }}" data-auth-open="login">{{ icon('log-in', 'w-4 h-4') }} {{ __('acct.login') }}</a>
      <a class="btn-ghost justify-center" href="{{ url('/account/register/') }}" data-auth-open="register">{{ __('acct.register') }}</a>
    </div>
    @php $oauth = \W5D\OAuth::enabled(); @endphp
    @if($oauth)
      <p class="text-xs text-slate-600 dark:text-slate-400 text-center mt-4">{{ __('acct.or') }}</p>
      <div class="mt-2 grid gap-2">
        @foreach($oauth as $k => $pv)<a class="btn-ghost justify-center" href="{{ url('/account/social/' . $k . '/') }}">{{ brand($k, 'w-4 h-4') }} {{ __('acct.continue_with') }} {{ $pv['label'] }}</a>@endforeach
      </div>
    @endif
    <div class="mt-5 rounded-xl border border-slate-200 dark:border-white/10 p-3 text-sm">
      <p class="text-slate-600 dark:text-slate-400 text-xs">{{ __('acct.today_price') }}</p>
      <p class="font-display font-bold text-xl text-slate-900 dark:text-white">{{ money((float)\W5D\Variables::get('price.day')) }} <span class="text-xs font-normal text-slate-600 dark:text-slate-400">{{ __('price.per_day') }}</span></p>
      <div class="mt-2 flex items-center justify-between text-xs text-slate-600 dark:text-slate-400"><span>{{ __('acct.language') }}</span><span class="uppercase font-bold">{{ lang() }}</span></div>
    </div>
    <p class="text-[0.68rem] uppercase tracking-[0.18em] text-slate-600 dark:text-slate-400 mt-5 mb-1 font-semibold">{{ __('nav.audiences') }}</p>
    <nav class="grid grid-cols-2 gap-1" aria-label="{{ __('nav.audiences') }}">
      @foreach($audKeys as $k)<a class="aside-link !py-1.5" href="{{ url('/for/' . $k . '/') }}"><span>{{ $audName[$k] }}</span></a>@endforeach
    </nav>
    <nav class="mt-4 grid gap-1" aria-label="{{ __('acct.menu') }}">
      <a class="aside-link" href="{{ url('/checkout/') }}">{{ icon('coins', 'w-5 h-5') }}<span>{{ __('cta.invest') }}</span></a>
      <a class="aside-link" href="{{ url('/join/eligibility/') }}">{{ icon('graduation-cap', 'w-5 h-5') }}<span>{{ __('cta.eligibility') }}</span></a>
      <a class="aside-link" href="{{ url('/contact/') }}">{{ icon('mail', 'w-5 h-5') }}<span>{{ __('nav.contact') }}</span></a>
    </nav>
  @endif
  </div>
</aside>

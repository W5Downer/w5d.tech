@php
  $k = (string)($d['aud'] ?? 'user');
  $sp = \W5D\Dash::spec($k);
  $me = \W5D\Dash::current();
  $mine = $me === $k;
@endphp
<x-section :d="$d" pad="py-12">
  <div class="grid gap-6 lg:grid-cols-[1fr_20rem] items-start" data-dash-preview="{{ $k }}">
    <ul class="grid gap-4 sm:grid-cols-2" role="list">
      @foreach($sp['widgets'] as [$wi, $wl, $wb])
        <li class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-4">
          <p class="flex items-center gap-2 font-semibold text-slate-900 dark:text-white">{{ icon($wi, 'w-5 h-5 text-violet-500') }} {{ t($wl) }}</p>
          <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ t($wb) }}</p>
        </li>
      @endforeach
    </ul>
    <aside class="rounded-2xl border border-violet-500/30 bg-violet-500/5 p-5 text-sm" aria-label="{{ tr3('Where it lives', 'Où ça se trouve', 'أين تجده') }}">
      <p class="font-display font-semibold">{{ \W5D\Dash::inPortal($k) ? tr3('Inside the admin portal', 'Dans le portail d’administration', 'داخل بوابة الإدارة') : tr3('Inside your account', 'Dans votre compte', 'داخل حسابك') }}</p>
      <p class="mt-2 text-slate-600 dark:text-slate-300">{{ tr3('Each dashboard ends with an API and documentation page written for this role.', 'Chaque tableau de bord se termine par une page API et documentation écrite pour ce rôle.', 'تنتهي كل لوحة بصفحة API ووثائق مكتوبة لهذا الدور.') }}</p>
      <p class="mt-4 flex flex-wrap gap-2">
        @if($mine)
          <a class="btn-peak" href="{{ \W5D\Dash::home($k) }}">{{ tr3('Open my dashboard', 'Ouvrir mon tableau de bord', 'افتح لوحتي') }}</a><a class="btn-ghost" href="{{ \W5D\Dash::api($k) }}">{{ icon('code', 'w-4 h-4') }} API</a>
        @else
          <a class="btn-ghost" href="{{ url('/account/login/') }}">{{ tr3('Sign in to open it', 'Connectez-vous pour l’ouvrir', 'سجّل الدخول لفتحها') }}</a>
        @endif
      </p>
    </aside>
  </div>
</x-section>

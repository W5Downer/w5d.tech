@php
  /** Expects: $guide (ApiGuide::build), $tabs, $as, $tab_base, $role. Used by the admin portal and /account/api/. */
  $cls = 'rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5';
  $tabLabel = fn($k) => $k === 'developer' ? tr3('Developer reference', 'Référence développeur', 'مرجع المطور') : t(\W5D\Dash::spec($k)['label']);
  $mc = fn($m) => $m === 'GET' ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'bg-violet-500/15 text-violet-700 dark:text-violet-300';
  $act = fn($a) => __('ux.crud_' . $a . '_l');
  $toc = [];
  if (!empty($guide['quickstart'])) $toc['g-start'] = tr3('Quick start', 'Démarrage rapide', 'بداية سريعة');
  if (!empty($guide['screens'])) $toc['g-screens'] = tr3('Your screens', 'Vos écrans', 'شاشاتك');
  if (!empty($guide['crud'])) $toc['g-crud'] = tr3('What you can create, edit, delete', 'Ce que vous pouvez créer, modifier, supprimer', 'ما يمكنك إنشاؤه وتعديله وحذفه');
  if (!empty($guide['panels'])) $toc['g-panels'] = tr3('Your dashboard panels', 'Vos panneaux', 'لوحات حسابك');
  $toc['g-public'] = tr3('Public API', 'API publique', 'الواجهة العامة');
  foreach ($guide['groups'] as $i => $g) $toc['g-grp' . $i] = t($g['group']);
  $toc['g-gates'] = tr3('Sign-in gates', 'Accès par connexion', 'بوابات الدخول');
  $toc['g-notes'] = tr3('Conventions', 'Conventions', 'الاتفاقيات');
@endphp
<div class="space-y-8" data-api-guide="{{ $guide['aud'] }}">
  <nav class="flex flex-wrap gap-2" aria-label="{{ tr3('Guides', 'Guides', 'الأدلة') }}">
    @foreach($tabs as $k)
      <a href="{{ $tab_base }}{{ $k === $role ? '' : '?as=' . $k }}" @if($as === $k) aria-current="page" @endif class="rounded-full px-3 py-1.5 text-sm border {{ $as === $k ? 'bg-violet-500/15 border-violet-500/50 font-semibold' : 'border-slate-200 dark:border-white/10 hover:bg-slate-100 dark:hover:bg-white/5' }}">{{ $tabLabel($k) }}</a>
    @endforeach
  </nav>

  <section class="{{ $cls }} p-5">
    <p class="text-slate-700 dark:text-slate-300">{{ t($guide['intro']) }}</p>
    <p class="mt-3 text-xs text-slate-600 dark:text-slate-400">{{ icon('lock', 'w-3.5 h-3.5 inline') }} {{ __('ux.docs_private') }}</p>
    <nav class="mt-4 flex flex-wrap gap-2 text-xs" aria-label="{{ tr3('On this page', 'Sur cette page', 'في هذه الصفحة') }}">
      @foreach($toc as $id => $lab)<a class="rounded-full border border-slate-200 dark:border-white/10 px-3 py-1 hover:bg-slate-100 dark:hover:bg-white/5" href="#{{ $id }}">{{ $lab }}</a>@endforeach
    </nav>
    <p class="mt-4 flex flex-wrap gap-3 text-sm">
      <a class="btn-ghost" href="{{ $guide['links']['openapi'] }}">{{ icon('code', 'w-4 h-4') }} OpenAPI JSON</a>
      <a class="btn-ghost" href="{{ $guide['links']['developers'] }}">{{ icon('book-open', 'w-4 h-4') }} {{ tr3('Developer guide', 'Guide développeur', 'دليل المطور') }}</a>
      <a class="btn-ghost" href="{{ $guide['links']['agents'] }}">{{ icon('network', 'w-4 h-4') }} {{ tr3('AI agents', 'Agents IA', 'وكلاء الذكاء') }}</a>
    </p>
  </section>

  @if(!empty($guide['quickstart']))
  <section id="g-start" aria-labelledby="h-start" class="scroll-mt-20">
    <h2 id="h-start" class="font-display text-xl font-semibold">{{ $toc['g-start'] }}</h2>
    <ol class="mt-4 space-y-2 list-decimal ps-6 text-sm">@foreach($guide['quickstart'] as $s)<li>{{ t($s) }}</li>@endforeach</ol>
  </section>
  @endif

  @if(!empty($guide['screens']))
  <section id="g-screens" aria-labelledby="h-screens" class="scroll-mt-20">
    <h2 id="h-screens" class="font-display text-xl font-semibold">{{ $toc['g-screens'] }}</h2>
    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ tr3('Every menu of your sidebar: what it is for and what you can do there.', 'Chaque menu de votre barre latérale : à quoi il sert et ce que vous y faites.', 'كل قائمة في شريطك: لماذا هي وماذا تفعل فيها.') }}</p>
    <ul class="mt-4 grid gap-3 md:grid-cols-2">
      @foreach($guide['screens'] as $sc)
        <li class="{{ $cls }} p-4" data-screen="{{ $sc['slug'] }}">
          <div class="flex items-start gap-3">
            <span class="mt-0.5 text-brand-500">{{ icon($sc['icon'], 'w-5 h-5') }}</span>
            <div class="min-w-0">
              <p class="font-semibold">@if($sc['slug'] === 'command-center')<a class="btn-link" href="{{ admin_url() }}">{{ t($sc['label']) }}</a>@else<a class="btn-link" href="{{ admin_url($sc['slug'] . '/') }}">{{ t($sc['label']) }}</a>@endif</p>
              @if($sc['desc'])<p class="text-sm text-slate-600 dark:text-slate-300 mt-0.5">{{ t($sc['desc']) }}</p>@endif
              <p class="mt-2 flex flex-wrap items-center gap-1.5 text-xs">
                @if($sc['kind'] === 'crud')
                  @forelse($sc['can'] as $a)<span class="tier-badge">{{ $act($a) }}</span>@empty<span class="tier-badge">{{ __('ux.crud_readonly') }}</span>@endforelse
                  @if($sc['scope'])<span class="text-slate-600 dark:text-slate-400">{{ __('ux.crud_scope') }}</span>@endif
                @elseif($sc['kind'] === 'status')<span class="tier-badge">{{ tr3('Set status', 'Fixer le statut', 'ضبط الحالة') }}</span>
                @else<span class="tier-badge">{{ __('ux.crud_readonly') }}</span>@endif
              </p>
              @if($sc['how'])<p class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ t($sc['how']) }}</p>@endif
            </div>
          </div>
        </li>
      @endforeach
    </ul>
  </section>
  @endif

  @if(!empty($guide['crud']))
  <section id="g-crud" aria-labelledby="h-crud" class="scroll-mt-20">
    <h2 id="h-crud" class="font-display text-xl font-semibold">{{ $toc['g-crud'] }}</h2>
    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ tr3('Rights are checked on the server for every request: hiding a button is a courtesy, not the lock. Row scope limits a role to its own rows.', 'Les droits sont vérifiés côté serveur à chaque requête : masquer un bouton est une politesse, pas le verrou. La portée limite un rôle à ses propres lignes.', 'تُفحص الحقوق في الخادم عند كل طلب: إخفاء الزر مجاملة لا قفل. نطاق الصفوف يحصر الدور بصفوفه.') }}</p>
    <div class="mt-4 {{ $cls }} overflow-hidden">
      <table class="w5-table w5-stack">
        <thead><tr><th>{{ tr3('Screen', 'Écran', 'الشاشة') }}</th>@foreach(['create', 'update', 'delete'] as $a)<th>{{ $act($a) }}</th>@endforeach<th>{{ tr3('Row scope', 'Portée', 'نطاق الصفوف') }}</th></tr></thead>
        <tbody>
        @foreach($guide['crud'] as $c)
          <tr data-crud="{{ $c['slug'] }}">
            <td data-label="{{ tr3('Screen', 'Écran', 'الشاشة') }}"><a class="btn-link" href="{{ admin_url($c['slug'] . '/') }}">{{ t($c['title']) }}</a></td>
            @foreach(['create', 'update', 'delete'] as $a)
              <td data-label="{{ $act($a) }}">@if(in_array($a, $c['can'], true))<span class="text-emerald-600 dark:text-emerald-400" aria-label="{{ __('ux.crud_yes') }}">{{ icon('check', 'w-4 h-4') }}</span>@elseif(!in_array($a, $c['declared'], true))<span class="text-slate-400" title="{{ tr3('Not available for anybody', 'Disponible pour personne', 'غير متاح لأحد') }}">n/a</span>@else<span class="text-slate-400">—</span>@endif</td>
            @endforeach
            <td data-label="{{ tr3('Row scope', 'Portée', 'نطاق الصفوف') }}">{{ $c['scope'] ? __('ux.crud_own') : tr3('all rows', 'toutes les lignes', 'كل الصفوف') }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    <details class="mt-3 {{ $cls }} p-4 text-sm">
      <summary class="cursor-pointer font-semibold">{{ tr3('Fields of each form', 'Champs de chaque formulaire', 'حقول كل نموذج') }}</summary>
      <ul class="mt-3 space-y-3">
        @foreach($guide['crud'] as $c)
          <li><p class="font-medium">{{ t($c['title']) }} <span class="text-xs text-slate-600 dark:text-slate-400 font-mono">{{ $c['table'] }}</span></p>
            <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">@foreach($c['fields'] as $f){{ t($f['label']) }}@if($f['req']) *@endif <span class="font-mono">({{ $f['type'] }})</span>@if(!$loop->last) · @endif @endforeach</p></li>
        @endforeach
      </ul>
      <p class="mt-3 text-xs text-slate-600 dark:text-slate-400">{{ tr3('* required. Country is always set to Tunisia; passwords and tokens are never editable here.', '* obligatoire. Le pays est toujours la Tunisie ; mots de passe et jetons ne sont jamais modifiables ici.', '* مطلوب. الدولة دائما تونس؛ لا تُعدَّل كلمات المرور والرموز هنا.') }}</p>
    </details>
  </section>
  @endif

  @if(!empty($guide['panels']))
  <section id="g-panels" aria-labelledby="h-panels" class="scroll-mt-20">
    <h2 id="h-panels" class="font-display text-xl font-semibold">{{ $toc['g-panels'] }}</h2>
    <ul class="mt-4 grid gap-3 md:grid-cols-2">
      @foreach($guide['panels'] as $p)
        <li class="{{ $cls }} p-4" data-panel="{{ $p['panel'] }}">
          <p class="font-semibold"><a class="btn-link" href="{{ url($p['path']) }}">{{ $p['panel'] === 'api' ? __('ux.docs_title') : __('acct.' . $p['panel']) }}</a> <code class="text-xs text-slate-600 dark:text-slate-400">{{ $p['path'] }}</code></p>
          <p class="text-sm text-slate-600 dark:text-slate-300 mt-1">{{ t($p['desc']) }}</p>
          <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">{{ t($p['use']) }}</p>
        </li>
      @endforeach
    </ul>
  </section>
  @endif

  <section id="g-public" aria-labelledby="h-public" class="scroll-mt-20">
    <h2 id="h-public" class="font-display text-xl font-semibold">{{ $toc['g-public'] }}</h2>
    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ tr3('Open data endpoints, no key. Highlighted rows matter most for this audience. Generated from the OpenAPI source, so it cannot drift from the code. Each item: what it is, how to use it, what comes back, and ready-to-paste calls.', 'Points de données ouverts, sans clé. Les lignes en évidence comptent le plus pour ce public. Générés depuis la source OpenAPI : pas de dérive. Pour chaque point : ce que c’est, comment l’utiliser, ce qui revient, et des appels prêts à coller.', 'نقاط بيانات مفتوحة بلا مفتاح. الصفوف المميزة الأهم لهذا الجمهور. مولدة من مصدر OpenAPI فلا تنحرف. لكل بند: ما هو وكيف يُستخدم وما يعود وأمثلة جاهزة.') }}</p>
    <ul class="mt-4 space-y-3">
      @foreach($guide['public'] as $r)
        <li class="{{ $cls }} p-4 {{ $r['focus'] ? 'ring-1 ring-violet-500/40' : '' }}" data-op="{{ $r['id'] }}">
          <div class="flex flex-wrap items-center gap-2">
            <span class="rounded px-2 py-0.5 text-xs font-mono font-bold {{ $mc($r['method']) }}">{{ $r['method'] }}</span>
            <code class="text-sm break-all">{{ $r['path'] }}</code>
            <span class="ms-auto text-xs font-mono text-slate-600 dark:text-slate-400">{{ $r['id'] }}</span>
          </div>
          <p class="mt-2 text-sm font-medium text-slate-800 dark:text-slate-100">{{ t($r['desc']) }}</p>
          @if($r['use'])<p class="mt-1 text-sm text-slate-600 dark:text-slate-300"><span class="font-semibold">{{ tr3('How to use', 'Comment l’utiliser', 'كيف تستخدمه') }}:</span> {{ t($r['use']) }}</p>@endif
          @if($r['returns'])<p class="mt-1 text-xs text-slate-600 dark:text-slate-400"><span class="font-semibold">{{ tr3('Returns', 'Renvoie', 'يعيد') }}:</span> <code dir="ltr" class="break-all">{{ $r['returns'] }}</code></p>@endif
          <pre dir="ltr" class="mt-2 overflow-x-auto rounded-lg bg-slate-900 text-slate-100 text-xs p-3"><code>{{ $r['example'] }}</code></pre>
          <details class="mt-2 text-xs"><summary class="cursor-pointer text-slate-600 dark:text-slate-400">JavaScript · Python</summary>
            <pre dir="ltr" class="mt-2 overflow-x-auto rounded-lg bg-slate-900 text-slate-100 p-3"><code>{{ $r['js'] }}</code></pre>
            <pre dir="ltr" class="mt-2 overflow-x-auto rounded-lg bg-slate-900 text-slate-100 p-3"><code>{{ $r['py'] }}</code></pre>
          </details>
        </li>
      @endforeach
    </ul>
  </section>

  @foreach($guide['groups'] as $gi => $g)
    <section id="g-grp{{ $gi }}" class="scroll-mt-20">
      <h2 class="font-display text-xl font-semibold">{{ t($g['group']) }}</h2>
      <ul class="mt-4 space-y-3">
        @foreach($g['rows'] as $r)
          <li class="{{ $cls }} p-4 {{ $r['ok'] ? '' : 'opacity-60' }}" data-session="{{ $r['path'] }}">
            <div class="flex flex-wrap items-center gap-2">
              <span class="rounded px-2 py-0.5 text-xs font-mono font-bold {{ $mc($r['method']) }}">{{ $r['method'] }}</span>
              <code class="text-sm break-all">{{ $r['path'] }}</code>
              <span class="ms-auto text-xs">{{ $r['ok'] ? ($r['perm'] === 'public' ? tr3('public', 'public', 'عام') : (str_starts_with($r['perm'], 'gate:') ? tr3('signed in', 'connecté', 'مسجّل') : $r['perm'])) : tr3('not for your role', 'pas pour votre rôle', 'ليس لدورك') . ' (' . $r['perm'] . ')' }}</span>
            </div>
            <p class="mt-2 text-sm font-medium text-slate-800 dark:text-slate-100">{{ is_array($r['desc']) ? t($r['desc']) : $r['desc'] }}</p>
            @if($r['use'])<p class="mt-1 text-sm text-slate-600 dark:text-slate-300"><span class="font-semibold">{{ tr3('How to use', 'Comment l’utiliser', 'كيف تستخدمه') }}:</span> {{ t($r['use']) }}</p>@endif
            @if($r['fields'] !== '')<p class="mt-1 text-xs text-slate-600 dark:text-slate-400"><span class="font-semibold">{{ tr3('Fields', 'Champs', 'الحقول') }}:</span> <code dir="ltr" class="break-all">{{ $r['fields'] }}</code></p>@endif
          </li>
        @endforeach
      </ul>
    </section>
  @endforeach

  <section id="g-gates" class="{{ $cls }} p-5 scroll-mt-20">
    <h2 class="font-display text-lg font-semibold">{{ $toc['g-gates'] }}</h2>
    <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ tr3('Which website forms need a signed-in account right now. A guest is sent to sign in and comes back to the same page.', 'Quels formulaires du site exigent un compte connecté en ce moment. Un invité se connecte puis revient à la même page.', 'أي نماذج الموقع تتطلب حسابا مسجلا الآن. الزائر يدخل ثم يعود لنفس الصفحة.') }}</p>
    <ul class="mt-3 grid gap-2 sm:grid-cols-2 text-sm">
      @foreach($guide['gates'] as $form => $x)
        <li class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 dark:border-white/10 px-3 py-2"><span class="min-w-0 break-words"><code>{{ $form }}</code> <span class="text-xs text-slate-600 dark:text-slate-400">{{ $x['flag'] }}</span></span><span class="shrink-0 rounded-full px-2 py-0.5 text-xs {{ $x['on'] ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'bg-slate-500/15' }}">{{ $x['on'] ? tr3('requires sign-in', 'connexion requise', 'يتطلب الدخول') : tr3('open', 'ouvert', 'مفتوح') }}</span></li>
      @endforeach
    </ul>
    @if(\W5D\Auth::can('variables'))<p class="mt-3 text-xs">{{ tr3('You can switch these in', 'Vous pouvez les changer dans', 'يمكنك تبديلها في') }} <a class="btn-link" href="{{ admin_url('variables/') }}">{{ tr3('Variables', 'Variables', 'المتغيرات') }}</a>.</p>@endif
  </section>

  <section id="g-notes" class="rounded-2xl border border-amber-500/40 bg-amber-500/10 p-5 scroll-mt-20" role="note">
    <h2 class="font-display text-lg font-semibold">{{ $toc['g-notes'] }}</h2>
    <ul class="mt-3 list-disc ps-5 space-y-2 text-sm">@foreach($guide['notes'] as $n)<li>{{ t($n) }}</li>@endforeach</ul>
  </section>
</div>

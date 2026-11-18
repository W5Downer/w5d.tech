@php
  /** Expects: $guide (ApiGuide::build), $tabs, $as, $tab_base, $role. Used by the admin portal and /account/api/. */
  $cls = 'rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5';
  $tabLabel = fn($k) => $k === 'developer' ? tr3('Developer reference', 'Référence développeur', 'مرجع المطور') : t(\W5D\Dash::spec($k)['label']);
  $mc = fn($m) => $m === 'GET' ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'bg-violet-500/15 text-violet-700 dark:text-violet-300';
@endphp
<div class="space-y-8" data-api-guide="{{ $guide['aud'] }}">
  <nav class="flex flex-wrap gap-2" aria-label="{{ tr3('Guides', 'Guides', 'الأدلة') }}">
    @foreach($tabs as $k)
      <a href="{{ $tab_base }}{{ $k === $role ? '' : '?as=' . $k }}" @if($as === $k) aria-current="page" @endif class="rounded-full px-3 py-1.5 text-sm border {{ $as === $k ? 'bg-violet-500/15 border-violet-500/50 font-semibold' : 'border-slate-200 dark:border-white/10 hover:bg-slate-100 dark:hover:bg-white/5' }}">{{ $tabLabel($k) }}</a>
    @endforeach
  </nav>

  <section class="{{ $cls }} p-5">
    <p class="text-slate-700 dark:text-slate-300">{{ t($guide['intro']) }}</p>
    <p class="mt-4 flex flex-wrap gap-3 text-sm">
      <a class="btn-ghost" href="{{ $guide['links']['openapi'] }}">{{ icon('code', 'w-4 h-4') }} OpenAPI JSON</a>
      <a class="btn-ghost" href="{{ $guide['links']['developers'] }}">{{ icon('book-open', 'w-4 h-4') }} /developers/</a>
      <a class="btn-ghost" href="{{ $guide['links']['agents'] }}">{{ icon('network', 'w-4 h-4') }} /agents/</a>
    </p>
  </section>

  <section aria-labelledby="g-public">
    <h2 id="g-public" class="font-display text-xl font-semibold">{{ tr3('Public API (no key)', 'API publique (sans clé)', 'الواجهة العامة (بلا مفتاح)') }}</h2>
    <p class="mt-1 text-sm text-slate-500">{{ tr3('Highlighted rows are the ones most useful for this audience. Generated from the OpenAPI source, so it cannot drift from the code.', 'Les lignes en évidence sont les plus utiles pour ce public. Générées depuis la source OpenAPI : elles ne peuvent pas diverger du code.', 'الصفوف المميزة هي الأنفع لهذا الجمهور. مولّدة من مصدر OpenAPI فلا تنحرف عن الكود.') }}</p>
    <ul class="mt-4 space-y-3">
      @foreach($guide['public'] as $r)
        <li class="{{ $cls }} p-4 {{ $r['focus'] ? 'ring-1 ring-violet-500/40' : 'opacity-90' }}">
          <div class="flex flex-wrap items-center gap-2">
            <span class="rounded px-2 py-0.5 text-xs font-mono font-bold {{ $mc($r['method']) }}">{{ $r['method'] }}</span>
            <code class="text-sm break-all">{{ $r['path'] }}</code>
            <span class="ms-auto text-xs font-mono text-slate-500">{{ $r['id'] }}</span>
          </div>
          <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $r['summary'] }}</p>
          <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-900 text-slate-100 text-xs p-3"><code>{{ $r['example'] }}</code></pre>
        </li>
      @endforeach
    </ul>
  </section>

  @foreach($guide['groups'] as $g)
    <section>
      <h2 class="font-display text-xl font-semibold">{{ t($g['group']) }}</h2>
      <div class="mt-4 overflow-x-auto {{ $cls }}">
        <table class="w5-table">
          <thead><tr><th>{{ tr3('Method', 'Méthode', 'الطريقة') }}</th><th>{{ tr3('Path', 'Chemin', 'المسار') }}</th><th>{{ tr3('What it does', 'Ce que ça fait', 'ما تفعله') }}</th><th>{{ tr3('Fields', 'Champs', 'الحقول') }}</th><th>{{ tr3('Permission', 'Permission', 'الصلاحية') }}</th></tr></thead>
          <tbody>
            @foreach($g['rows'] as $r)
              <tr class="{{ $r['ok'] ? '' : 'opacity-50' }}">
                <td><span class="rounded px-2 py-0.5 text-xs font-mono font-bold {{ $mc($r['method']) }}">{{ $r['method'] }}</span></td>
                <td><code class="text-xs break-all">{{ $r['path'] }}</code></td>
                <td class="text-sm">{{ t($r['summary']) }}</td>
                <td class="text-xs font-mono text-slate-500">{{ $r['fields'] }}</td>
                <td class="text-xs">{{ $r['ok'] ? ($r['perm'] === 'public' ? tr3('public', 'public', 'عام') : (str_starts_with($r['perm'], 'gate:') ? tr3('signed in', 'connecté', 'مسجّل') : $r['perm'])) : tr3('not for your role', 'pas pour votre rôle', 'ليس لدورك') . ' (' . $r['perm'] . ')' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>
  @endforeach

  <section class="{{ $cls }} p-5">
    <h2 class="font-display text-lg font-semibold">{{ tr3('Sign-in gates right now', 'Accès par connexion en ce moment', 'بوابات الدخول الآن') }}</h2>
    <ul class="mt-3 grid gap-2 sm:grid-cols-2 text-sm">
      @foreach($guide['gates'] as $form => $x)
        <li class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 dark:border-white/10 px-3 py-2"><span><code>{{ $form }}</code> <span class="text-xs text-slate-500">{{ $x['flag'] }}</span></span><span class="rounded-full px-2 py-0.5 text-xs {{ $x['on'] ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'bg-slate-500/15' }}">{{ $x['on'] ? tr3('requires sign-in', 'connexion requise', 'يتطلب الدخول') : tr3('open', 'ouvert', 'مفتوح') }}</span></li>
      @endforeach
    </ul>
    @if(\W5D\Auth::can('variables'))<p class="mt-3 text-xs">{{ tr3('You can switch these in', 'Vous pouvez les changer dans', 'يمكنك تبديلها في') }} <a class="btn-link" href="{{ admin_url('variables/') }}">{{ tr3('Variables', 'Variables', 'المتغيرات') }}</a>.</p>@endif
  </section>

  <section class="rounded-2xl border border-amber-500/40 bg-amber-500/10 p-5" role="note">
    <h2 class="font-display text-lg font-semibold">{{ tr3('Conventions', 'Conventions', 'الاتفاقيات') }}</h2>
    <ul class="mt-3 list-disc ps-5 space-y-2 text-sm">@foreach($guide['notes'] as $n)<li>{{ t($n) }}</li>@endforeach</ul>
  </section>
</div>

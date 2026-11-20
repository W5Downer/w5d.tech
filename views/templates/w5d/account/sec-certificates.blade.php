<p class="mt-3 text-slate-600 dark:text-slate-300 max-w-3xl">{{ tr3('Each certificate has a public code. Anyone can verify it online; the verifier shows a masked name, the service, the level, the score and the dates, never your e-mail address.', 'Chaque certificat a un code public. Chacun peut le vérifier en ligne ; le vérificateur affiche un nom masqué, le service, le niveau, le score et les dates, jamais votre e-mail.', 'لكل شهادة رمز عام. يمكن لأي شخص التحقق منها عبر الإنترنت؛ تعرض صفحة التحقق اسما مقنّعا والخدمة والمستوى والنتيجة والتواريخ، ولا تعرض بريدك أبدا.') }}</p>
<p class="mt-2 text-sm"><span class="tier-badge">{{ tr3('Highest valid level', 'Niveau valide le plus élevé', 'أعلى مستوى صالح') }}: {{ $c_level ? 'L' . $c_level : '—' }}</span></p>
@if(!$c_rows)
  <p class="mt-6 rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-6 text-slate-600 dark:text-slate-300">{{ tr3('No certificate yet. Pass a certification round to get your first one.', 'Pas encore de certificat. Réussissez une session de certification pour obtenir le premier.', 'لا شهادة بعد. انجح في جولة اعتماد لتحصل على الأولى.') }}</p>
  <p class="mt-4"><a class="btn-peak" href="{{ url('/account/qcm/') }}">{{ icon('list-checks', 'w-4 h-4') }} {{ tr3('Go to QCM rounds', 'Aller aux sessions QCM', 'اذهب إلى جولات الاختبار') }}</a></p>
@else
  <ul class="mt-6 grid gap-4 md:grid-cols-2">
    @foreach($c_rows as $c)
      @php $st = \W5D\Certificate::state($c); @endphp
      <li class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-5" data-cert="{{ $c['code'] }}">
        <div class="flex items-start justify-between gap-3"><div><p class="eyebrow">L{{ $c['level'] }} · {{ \W5D\Certificate::levelLabel((int)$c['level']) }}</p><h3 class="font-display font-semibold">{{ \W5D\Certificate::serviceTitle((string)$c['service_slug']) }}</h3></div>
          <span class="rounded-full px-2 py-0.5 text-xs h-fit {{ $stl($st === 'valid' ? 'verified' : ($st === 'revoked' ? 'rejected' : 'pending')) }}">{{ $st }}</span></div>
        <p class="mt-2 font-mono text-sm" dir="ltr">{{ $c['code'] }} <button type="button" class="btn-link text-xs ms-2" data-copy-text="{{ $c['code'] }}">{{ __('cta.copy') }}</button></p>
        <p class="text-xs text-slate-500 mt-1">{{ substr((string)$c['issued_at'], 0, 10) }}@if($c['expires_at']) → {{ substr((string)$c['expires_at'], 0, 10) }}@endif @if($c['score'] !== null) · {{ round((float)$c['score']) }}%@endif</p>
        @if($st === 'revoked' && $c['revoke_reason'])<p class="text-xs mt-1 text-red-600 dark:text-red-300">{{ $c['revoke_reason'] }}</p>@endif
        <p class="mt-3 flex flex-wrap gap-3 text-sm"><a class="btn-ghost" href="{{ url('/certificate/' . $c['code'] . '/') }}">{{ icon('printer', 'w-4 h-4') }} {{ tr3('View and print', 'Voir et imprimer', 'عرض وطباعة') }}</a><a class="btn-link" href="{{ url('/certification/verify/') . '?code=' . $c['code'] }}">{{ icon('qr-code', 'w-4 h-4 inline') }} {{ tr3('Public check', 'Vérification publique', 'التحقق العام') }}</a></p>
      </li>
    @endforeach
  </ul>
@endif
<p class="mt-8 text-xs text-slate-500">{{ __('limits.returns') }}</p>

<p class="mt-3 text-slate-600 dark:text-slate-300 max-w-3xl">{{ tr3('Each certificate has a public code. Anyone can verify it online; the verifier shows a masked name, the service, the level, the score and the dates, never your e-mail address.', 'Chaque certificat a un code public. Chacun peut le vérifier en ligne ; le vérificateur affiche un nom masqué, le service, le niveau, le score et les dates, jamais votre e-mail.', 'لكل شهادة رمز عام. يمكن لأي شخص التحقق منها عبر الإنترنت؛ تعرض صفحة التحقق اسما مقنّعا والخدمة والمستوى والنتيجة والتواريخ، ولا تعرض بريدك أبدا.') }}</p>
<p class="mt-2 text-sm"><span class="tier-badge">{{ tr3('Highest valid level', 'Niveau valide le plus élevé', 'أعلى مستوى صالح') }}: {{ $c_level ? 'L' . $c_level : '—' }}</span></p>
@if(!empty($c_renew))
  <div class="mt-6 space-y-3" data-recert-prompts>
  @foreach($c_renew as $r)
    <div class="rounded-2xl border {{ $r['state'] === 'expired' ? 'border-red-500/40 bg-red-500/5' : 'border-amber-500/40 bg-amber-500/10' }} p-4" role="status" data-recert="{{ $r['code'] }}" data-recert-state="{{ $r['state'] }}">
      <p class="font-semibold">{{ \W5D\Certificate::serviceTitle((string)$r['service_slug']) }} · L{{ $r['level'] }}</p>
      <p class="mt-1 text-sm">@if($r['state'] === 'expired'){{ tr3('This certificate expired on', 'Ce certificat a expiré le', 'انتهت صلاحية هذه الشهادة في') }} {{ substr((string)$r['expires_at'], 0, 10) }}. {{ tr3('Anyone verifying the code now sees it as expired.', 'Toute personne qui vérifie le code le voit désormais comme expiré.', 'أي شخص يتحقق من الرمز الآن يراها منتهية.') }}@else{{ tr3('This certificate expires on', 'Ce certificat expire le', 'تنتهي صلاحية هذه الشهادة في') }} {{ substr((string)$r['expires_at'], 0, 10) }} ({{ $r['days'] }} {{ tr3('days left', 'jours restants', 'يوما متبقيا') }}).@endif {{ tr3('Renewing needs a new passed round: paying a fee only gives the attempt, never the result.', 'Le renouvellement exige une nouvelle session réussie : des frais ne donnent que la tentative, jamais le résultat.', 'التجديد يتطلب جولة جديدة ناجحة: الرسوم تمنح المحاولة فقط وليس النتيجة.') }}</p>
      <div class="mt-3 flex flex-wrap items-center gap-3">
        @if($r['fee'])
          <form method="post" action="{{ url('/account/fee/buy') }}">{!! csrf_field() !!}<input type="hidden" name="fee" value="{{ $r['fee']['id'] }}"><button class="btn-focus" type="submit">{{ tr3('Pay the re-certification fee', 'Payer les frais de recertification', 'ادفع رسوم إعادة الاعتماد') }} · {{ number_format((float)$r['fee']['amount'], 2) }} {{ \W5D\Billing::currency() }}</button></form>
        @endif
        <a class="btn-ghost" href="{{ url('/account/qcm/') }}">{{ icon('list-checks', 'w-4 h-4') }} {{ tr3('Go to QCM rounds', 'Aller aux sessions QCM', 'اذهب إلى جولات الاختبار') }}</a>
      </div>
    </div>
  @endforeach
  </div>
@endif
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
        <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">{{ substr((string)$c['issued_at'], 0, 10) }}@if($c['expires_at']) → {{ substr((string)$c['expires_at'], 0, 10) }}@endif @if($c['score'] !== null) · {{ round((float)$c['score']) }}%@endif</p>
        @if($st === 'revoked' && $c['revoke_reason'])<p class="text-xs mt-1 text-red-600 dark:text-red-300">{{ $c['revoke_reason'] }}</p>@endif
        <p class="mt-3 flex flex-wrap gap-3 text-sm"><a class="btn-ghost" href="{{ url('/certificate/' . $c['code'] . '/') }}">{{ icon('printer', 'w-4 h-4') }} {{ tr3('View and print', 'Voir et imprimer', 'عرض وطباعة') }}</a><a class="btn-link" href="{{ url('/certification/verify/') . '?code=' . $c['code'] }}">{{ icon('qr-code', 'w-4 h-4 inline') }} {{ tr3('Public check', 'Vérification publique', 'التحقق العام') }}</a></p>
      </li>
    @endforeach
  </ul>
@endif
@if(!empty($c_open))
  <p class="mt-6 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm" role="status">{{ tr3('A fee is waiting for payment:', 'Des frais attendent le paiement :', 'رسوم بانتظار الدفع:') }}
    @foreach($c_open as $o)<a class="btn-link ms-2" href="{{ url('/account/invoices/pay/' . $o['number'] . '/') }}">{{ $o['number'] }} · {{ number_format((float)$o['amount'], 2) }} {{ $o['currency'] }}</a>@endforeach</p>
@endif
@if(!empty($c_fees))
  <h2 class="mt-8 font-display text-lg font-semibold">{{ tr3('Assessment and certification fees', 'Frais d’évaluation et de certification', 'رسوم التقييم والاعتماد') }}</h2>
  <p class="mt-1 text-sm text-slate-600 dark:text-slate-300 max-w-3xl">{{ tr3('A fee gives you the attempt, never the result. A certificate is issued only after a passed round and it expires: re-certification is a separate fee.', 'Des frais donnent la tentative, jamais le résultat. Un certificat n’est délivré qu’après une session réussie et il expire : la recertification est un frais distinct.', 'الرسوم تمنح المحاولة لا النتيجة. لا تصدر الشهادة إلا بعد جولة ناجحة وهي تنتهي: إعادة الاعتماد رسم منفصل.') }}</p>
  <ul class="mt-3 grid gap-3 sm:grid-cols-2">
    @foreach($c_fees as $f)
      <li class="rounded-2xl border border-slate-200 dark:border-white/10 p-4 flex flex-wrap items-center justify-between gap-3" data-fee="{{ $f['id'] }}">
        <div><p class="eyebrow">{{ \W5D\Billing::kindLabel((string)$f['kind']) }}@if((int)$f['level'] > 0) · L{{ $f['level'] }}@endif</p><p class="font-semibold">{{ $f['name'] }}</p><p class="text-sm tabular-nums text-slate-600 dark:text-slate-300">{{ number_format((float)$f['amount'], 2) }} {{ \W5D\Billing::currency() }}</p></div>
        <form method="post" action="{{ url('/account/fee/buy') }}">{!! csrf_field() !!}<input type="hidden" name="fee" value="{{ $f['id'] }}"><button class="btn-focus" type="submit">{{ tr3('Pay this fee', 'Payer ces frais', 'ادفع هذه الرسوم') }}</button></form>
      </li>
    @endforeach
  </ul>
@endif
<p class="mt-8 text-xs text-slate-600 dark:text-slate-400">{{ __('limits.returns') }}</p>

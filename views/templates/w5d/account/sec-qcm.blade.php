@php
  $ck = fn($k) => match($k) {
    'level' => tr3('Certified level', 'Niveau certifié', 'المستوى المعتمد'), 'affiliates' => tr3('Affiliate sign-ups', 'Inscriptions d’affiliés', 'تسجيلات الإحالة'),
    'tasks' => tr3('Approved tasks', 'Tâches approuvées', 'مهام مقبولة'), 'practice' => tr3('Best practice score', 'Meilleur score d’entraînement', 'أفضل نتيجة تدريب'),
    'cooldown' => tr3('Wait since last round', 'Attente depuis la dernière session', 'الانتظار منذ آخر جولة'), 'daily' => tr3('Rounds today', 'Sessions aujourd’hui', 'جولات اليوم'),
    'fee' => tr3('Round fee', 'Frais de session', 'رسوم الجولة'), default => $k };
  $pl = fn($p) => match($p) { 'practice' => tr3('Practice', 'Entraînement', 'تدريب'), 'certification' => tr3('Certification', 'Certification', 'اعتماد'), default => tr3('Upgrade', 'Montée de niveau', 'ترقية') };
  $cur = (string)\W5D\Variables::get('money.currency', 'TND');
@endphp
@if(!$q_on)
  <p class="mt-6 rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-6 text-slate-600 dark:text-slate-300">{{ tr3('QCM rounds are paused for now. Please come back later.', 'Les sessions QCM sont suspendues pour le moment. Revenez plus tard.', 'جولات الاختبار متوقفة حاليا. عد لاحقا.') }}</p>
@else
  <p class="mt-3 text-slate-600 dark:text-slate-300 max-w-3xl">{{ tr3('Short timed rounds of multiple-choice questions on a random service. Practice is free and shows the answers. Certification and upgrade rounds issue a certificate you can verify online; each has minimum requirements set by the owner.', 'Courtes sessions chronométrées de questions à choix sur un service tiré au hasard. L’entraînement est gratuit et montre les réponses. Les sessions de certification et de montée de niveau délivrent un certificat vérifiable en ligne ; chacune a des conditions minimales fixées par le propriétaire.', 'جولات قصيرة محددة الوقت من أسئلة الاختيار على خدمة عشوائية. التدريب مجاني ويُظهر الإجابات. جولات الاعتماد والترقية تُصدر شهادة يمكن التحقق منها عبر الإنترنت، ولكل منها شروط دنيا يحددها المالك.') }}</p>
  <p class="mt-2 text-sm"><span class="tier-badge">{{ tr3('Your certified level', 'Votre niveau certifié', 'مستواك المعتمد') }}: {{ $q_level ? 'L' . $q_level : '—' }}</span></p>

  @if($q_open)
    <div class="mt-6 rounded-2xl border border-violet-500/40 bg-violet-500/10 p-5 flex flex-wrap items-center gap-4 justify-between" data-qcm-open>
      <div><p class="font-semibold">{{ tr3('A round is in progress', 'Une session est en cours', 'جولة قيد التنفيذ') }}</p><p class="text-sm text-slate-600 dark:text-slate-300">{{ tr3('The timer keeps running on the server.', 'Le chronomètre continue côté serveur.', 'يستمر المؤقت على الخادم.') }}</p></div>
      <a class="btn-peak" href="{{ url('/account/qcm/attempt/' . $q_open['id'] . '/') }}">{{ icon('play', 'w-4 h-4') }} {{ tr3('Resume the round', 'Reprendre la session', 'استئناف الجولة') }}</a>
    </div>
  @endif

  <div class="mt-6 grid gap-4 md:grid-cols-2" data-qcm-rules>
    @foreach($q_rules as $row)
      @php $r = $row['r']; $c = $row['c']; $pend = $row['pending']; @endphp
      <article class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-5 flex flex-col" data-rule="{{ $r['id'] }}">
        <div class="flex items-start justify-between gap-3">
          <div><p class="eyebrow">{{ $pl($r['purpose']) }} · L{{ $r['level'] }}</p><h3 class="font-display text-lg font-semibold">{{ $r['name'] }}</h3></div>
          <span class="tier-badge whitespace-nowrap">{{ $c['ok'] ? tr3('Ready', 'Prêt', 'جاهز') : tr3('Locked', 'Verrouillé', 'مقفل') }}</span>
        </div>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300 flex flex-wrap gap-x-4 gap-y-1">
          <span>{{ icon('list-checks', 'w-4 h-4 inline') }} {{ $r['question_count'] }} {{ tr3('questions', 'questions', 'سؤالا') }}</span>
          <span>{{ icon('timer', 'w-4 h-4 inline') }} {{ $r['minutes'] }} min</span>
          <span>{{ icon('circle-check', 'w-4 h-4 inline') }} {{ tr3('pass at', 'réussite à', 'النجاح عند') }} {{ $r['pass_pct'] }}%</span>
          <span>{{ $r['service_slug'] !== '' ? ($q_services[$r['service_slug']] ?? $r['service_slug']) : tr3('random service', 'service au hasard', 'خدمة عشوائية') }}</span>
        </p>
        @if($c['checks'])
          <ul class="mt-3 space-y-1 text-sm" aria-label="{{ tr3('Minimum requirements', 'Conditions minimales', 'الشروط الدنيا') }}">
            @foreach($c['checks'] as $k)
              <li class="flex items-center gap-2 {{ $k['ok'] ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300' }}">{{ $k['ok'] ? icon('circle-check', 'w-4 h-4 shrink-0') : icon('hourglass', 'w-4 h-4 shrink-0') }}<span>{{ $ck($k['key']) }}: <strong>{{ $k['have'] }}</strong> / {{ $k['need'] }}</span></li>
            @endforeach
          </ul>
        @else
          <p class="mt-3 text-sm text-slate-500">{{ tr3('No minimum requirement.', 'Aucune condition minimale.', 'لا شروط دنيا.') }}</p>
        @endif
        <div class="mt-4 pt-1 flex flex-wrap gap-3 mt-auto">
          @php $needFee = (float)$r['fee'] > 0 && empty($c['pass']); $feeOnly = $needFee && count(array_filter($c['checks'], fn($k) => !$k['ok'] && $k['key'] !== 'fee')) === 0; @endphp
          @if($c['ok'])
            <form method="post" action="{{ url('/account/qcm/start/') }}">{!! csrf_field() !!}<input type="hidden" name="rule" value="{{ $r['id'] }}"><button class="btn-peak" type="submit" @if($q_open) disabled @endif>{{ icon('play', 'w-4 h-4') }} {{ tr3('Start', 'Démarrer', 'ابدأ') }}</button></form>
          @elseif($feeOnly && $pend)
            <a class="btn-ghost" href="{{ \W5D\Qcm::passLink($pend['ref']) }}">{{ $pend['status'] === 'pending' ? tr3('Fee sent: waiting for review', 'Frais envoyés : en attente de vérification', 'أُرسلت الرسوم: بانتظار المراجعة') : tr3('Finish the payment', 'Terminer le paiement', 'أكمل الدفع') }}</a>
          @elseif($feeOnly)
            <form method="post" action="{{ url('/account/qcm/buy/') }}">{!! csrf_field() !!}<input type="hidden" name="rule" value="{{ $r['id'] }}"><button class="btn-focus" type="submit">{{ tr3('Pay the round fee', 'Payer les frais de session', 'ادفع رسوم الجولة') }} · {{ number_format((float)$r['fee'], 2) }} {{ $cur }}</button></form>
          @else
            <span class="text-xs text-slate-500">{{ tr3('Meet the requirements above to unlock this round.', 'Remplissez les conditions ci-dessus pour débloquer cette session.', 'حقّق الشروط أعلاه لفتح هذه الجولة.') }}</span>
          @endif
        </div>
      </article>
    @endforeach
  </div>

  <h2 class="mt-10 font-display text-lg font-semibold">{{ tr3('Submit a task', 'Soumettre une tâche', 'إرسال مهمة') }}</h2>
  <p class="mt-1 text-sm text-slate-600 dark:text-slate-300 max-w-3xl">{{ tr3('Some upgrade rounds ask for approved tasks: real work you did, with a place where a reviewer can see it.', 'Certaines montées de niveau demandent des tâches approuvées : un vrai travail que vous avez fait, avec un endroit où un relecteur peut le voir.', 'بعض جولات الترقية تطلب مهام مقبولة: عملا حقيقيا أنجزته مع مكان يراه فيه المراجع.') }}</p>
  <form method="post" action="{{ url('/account/qcm/task/') }}" class="mt-4 max-w-xl space-y-4" novalidate>
    {!! csrf_field() !!}
    <x-form-field name="title" :label="tr3('Task title', 'Titre de la tâche', 'عنوان المهمة')" :required="true" :errors="$errors" />
    <x-form-field name="service_slug" type="select" :label="tr3('Related service', 'Service concerné', 'الخدمة المعنية')" :options="['' => '—'] + $q_services" :errors="$errors" />
    <x-form-field name="evidence" type="textarea" :label="tr3('What you did and where to see it', 'Ce que vous avez fait et où le voir', 'ما أنجزته وأين يمكن رؤيته')" :required="true" :errors="$errors" />
    <button class="btn-ghost" type="submit">{{ tr3('Send for review', 'Envoyer pour relecture', 'أرسل للمراجعة') }}</button>
  </form>
  @if($q_tasks)
    <ul class="mt-4 space-y-2 max-w-xl">@foreach($q_tasks as $t)<li class="rounded-xl border border-slate-200 dark:border-white/10 p-3 text-sm flex justify-between gap-3"><span><strong>{{ $t['title'] }}</strong>@if($t['note'])<span class="block text-xs text-slate-500">{{ $t['note'] }}</span>@endif</span><span class="rounded-full px-2 py-0.5 text-xs h-fit {{ $stl($t['status'] === 'approved' ? 'verified' : ($t['status'] === 'rejected' ? 'rejected' : 'pending')) }}">{{ $t['status'] }}</span></li>@endforeach</ul>
  @endif

  <h2 class="mt-10 font-display text-lg font-semibold">{{ tr3('Your recent rounds', 'Vos dernières sessions', 'جولاتك الأخيرة') }}</h2>
  @if(!$q_hist)<p class="mt-2 text-sm text-slate-500">{{ tr3('No round yet. Start with a free practice round.', 'Aucune session pour le moment. Commencez par un entraînement gratuit.', 'لا جولات بعد. ابدأ بتدريب مجاني.') }}</p>
  @else
    <div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10"><table class="w-full text-sm">
      <thead class="text-xs uppercase text-slate-500"><tr><th class="p-3 text-start">{{ tr3('Date', 'Date', 'التاريخ') }}</th><th class="p-3 text-start">{{ tr3('Type', 'Type', 'النوع') }}</th><th class="p-3 text-start">{{ tr3('Level', 'Niveau', 'المستوى') }}</th><th class="p-3 text-start">{{ tr3('Score', 'Score', 'النتيجة') }}</th><th class="p-3"></th></tr></thead>
      <tbody>@foreach($q_hist as $h)<tr class="border-t border-slate-200 dark:border-white/10"><td class="p-3 tabular-nums">{{ substr((string)$h['started_at'], 0, 16) }}</td><td class="p-3">{{ $pl($h['purpose']) }}</td><td class="p-3">L{{ $h['level'] }}</td><td class="p-3">@if($h['status'] === 'submitted')<span class="rounded-full px-2 py-0.5 text-xs {{ $stl($h['passed'] ? 'verified' : 'rejected') }}">{{ round((float)$h['score_pct']) }}%</span>@else<span class="text-xs text-slate-500">{{ tr3('open', 'ouverte', 'مفتوحة') }}</span>@endif</td><td class="p-3 text-end"><a class="btn-link" href="{{ url('/account/qcm/attempt/' . $h['id'] . '/') }}">{{ tr3('Open', 'Ouvrir', 'فتح') }}</a></td></tr>@endforeach</tbody>
    </table></div>
  @endif
@endif
<p class="mt-8 text-xs text-slate-500">{{ __('limits.returns') }}</p>

@php
  $cur = \W5D\Billing::currency();
  $sellable = array_filter($pl_plans, fn($p) => \W5D\Billing::sellable($p, 'month') || \W5D\Billing::sellable($p, 'year'));
@endphp
<p class="mt-3 text-slate-600 dark:text-slate-300 max-w-3xl">{{ tr3('A plan gives access to the anonymous pipeline of certified people, the weekly review summary and extra seats for your colleagues. It renews by invoice: you decide each time, and nothing is charged by itself.', 'Une formule donne accès au pipeline anonyme des personnes certifiées, au résumé hebdomadaire des revues et à des sièges pour vos collègues. Elle se renouvelle par facture : vous décidez à chaque fois, rien n’est prélevé automatiquement.', 'تمنحك الباقة الوصول إلى مسار الأشخاص المعتمدين بشكل مجهول وملخص المراجعة الأسبوعي ومقاعد لزملائك. تتجدد بفاتورة: أنت تقرر في كل مرة ولا يُخصم شيء تلقائيا.') }}</p>

@foreach($pl_own as $s)
  <div class="mt-6 rounded-2xl border border-violet-500/40 bg-violet-500/5 p-5" data-sub="{{ $s['ref'] }}">
    <p class="flex flex-wrap items-center gap-2"><span class="font-display text-lg font-semibold">{{ \W5D\Billing::planName($s) }}</span><span class="tier-badge">{{ $s['status'] }}</span><span class="font-mono text-xs text-slate-600 dark:text-slate-300">{{ $s['ref'] }}</span></p>
    <p class="text-sm text-slate-600 dark:text-slate-300 mt-1 tabular-nums">{{ $s['seats'] }} {{ tr3('seat(s)', 'siège(s)', 'مقعد') }} · {{ number_format((float)$s['amount'], 2) }} {{ $cur }} / {{ $s['period'] === 'year' ? tr3('year', 'an', 'سنة') : tr3('month', 'mois', 'شهر') }}@if($s['current_period_end']) · {{ tr3('paid until', 'payée jusqu’au', 'مدفوعة حتى') }} {{ $s['current_period_end'] }}@endif</p>
    @if($s['status'] === 'active')
      <div class="mt-4 flex flex-wrap gap-3">
        <form method="post" action="{{ url('/account/plan/manage') }}">{!! csrf_field() !!}<input type="hidden" name="ref" value="{{ $s['ref'] }}"><input type="hidden" name="do" value="{{ $s['cancel_at_period_end'] ? 'resume' : 'cancel' }}">
          <button class="btn-ghost" type="submit">{{ $s['cancel_at_period_end'] ? tr3('Keep renewing', 'Continuer à renouveler', 'مواصلة التجديد') : tr3('Stop at the end of the period', 'Arrêter à la fin de la période', 'التوقف في نهاية الفترة') }}</button></form>
      </div>
      <h3 class="mt-5 text-sm font-semibold">{{ tr3('Seats', 'Sièges', 'المقاعد') }}</h3>
      <ul class="mt-2 text-sm space-y-1">
        @forelse($s['seat_rows'] as $st)<li class="flex items-center gap-3"><span dir="ltr">{{ $st['email'] }}</span><form method="post" action="{{ url('/account/plan/manage') }}">{!! csrf_field() !!}<input type="hidden" name="ref" value="{{ $s['ref'] }}"><input type="hidden" name="do" value="seat-del"><input type="hidden" name="email" value="{{ $st['email'] }}"><button class="btn-link text-xs" type="submit">{{ tr3('Remove', 'Retirer', 'إزالة') }}</button></form></li>@empty<li class="text-slate-600 dark:text-slate-300">{{ tr3('No colleague added yet.', 'Aucun collègue ajouté.', 'لم تضف زميلا بعد.') }}</li>@endforelse
      </ul>
      <form method="post" action="{{ url('/account/plan/manage') }}" class="mt-3 flex flex-wrap items-end gap-2">{!! csrf_field() !!}<input type="hidden" name="ref" value="{{ $s['ref'] }}"><input type="hidden" name="do" value="seat-add">
        <div><label class="block text-xs font-medium mb-1" for="seat-email">{{ tr3('Colleague e-mail', 'E-mail du collègue', 'بريد الزميل') }}</label><input id="seat-email" name="email" type="email" class="field" required autocomplete="off"></div>
        <button class="btn-focus" type="submit">{{ tr3('Add a seat', 'Ajouter un siège', 'أضف مقعدا') }}</button></form>
    @else
      <p class="mt-3 text-sm">{{ tr3('Waiting for the first payment. Find the invoice under Invoices.', 'En attente du premier paiement. Retrouvez la facture dans Factures.', 'بانتظار الدفعة الأولى. ستجد الفاتورة في الفواتير.') }} <a class="btn-link" href="{{ url('/account/invoices/') }}">{{ tr3('Open invoices', 'Ouvrir les factures', 'افتح الفواتير') }}</a></p>
    @endif
  </div>
@endforeach

@if($pl_ent['sub'] && !$pl_ent['owner'])<p class="mt-6 rounded-xl border border-slate-200 dark:border-white/10 p-4 text-sm">{{ tr3('You have a seat on a colleague’s plan: pipeline and reports are open to you.', 'Vous avez un siège sur la formule d’un collègue : pipeline et rapports vous sont ouverts.', 'لديك مقعد في باقة زميل: المسار والتقارير متاحة لك.') }}</p>@endif

@if(!$pl_own)
  <h2 class="mt-8 font-display text-lg font-semibold">{{ tr3('Choose a plan', 'Choisir une formule', 'اختر باقة') }}</h2>
  @if(!$pl_plans)
    <p class="mt-3 rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-6 text-slate-600 dark:text-slate-300">{{ tr3('Plans are not on sale yet.', 'Les formules ne sont pas encore en vente.', 'الباقات غير معروضة للبيع بعد.') }}</p>
  @else
  <div class="mt-4 grid gap-4 md:grid-cols-3">
    @foreach($pl_plans as $p)
      @php $m = \W5D\Billing::sellable($p, 'month'); $y = \W5D\Billing::sellable($p, 'year'); @endphp
      <article class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-5 flex flex-col">
        <h3 class="font-display text-lg font-semibold">{{ \W5D\Billing::planName($p) }}</h3>
        <ul class="mt-2 text-sm text-slate-600 dark:text-slate-300 space-y-1">
          <li>{{ $p['seats_included'] }} {{ tr3('seat(s) included', 'siège(s) inclus', 'مقعد مشمول') }}</li>
          @if($p['pipeline_access'])<li>{{ tr3('Pipeline of certified people (anonymous counts)', 'Pipeline des personnes certifiées (chiffres anonymes)', 'مسار الأشخاص المعتمدين (أرقام مجهولة)') }}</li>@endif
          @if($p['weekly_reports'])<li>{{ tr3('Weekly review summary', 'Résumé hebdomadaire des revues', 'ملخص المراجعة الأسبوعي') }}</li>@endif
        </ul>
        @if($m || $y)
          <form method="post" action="{{ url('/account/plan/buy') }}" class="mt-4 space-y-2">{!! csrf_field() !!}<input type="hidden" name="plan" value="{{ $p['id'] }}">
            <label class="block text-xs font-medium" for="pp{{ $p['id'] }}">{{ tr3('Billing period', 'Période', 'الفترة') }}</label>
            <select id="pp{{ $p['id'] }}" name="period" class="field">@if($m)<option value="month">{{ tr3('Monthly', 'Mensuelle', 'شهرية') }} · {{ number_format(\W5D\Billing::planPrice($p, 'month', (int)$p['seats_included']), 2) }} {{ $cur }}</option>@endif @if($y)<option value="year">{{ tr3('Yearly', 'Annuelle', 'سنوية') }} · {{ number_format(\W5D\Billing::planPrice($p, 'year', (int)$p['seats_included']), 2) }} {{ $cur }}</option>@endif</select>
            @if((float)$p['seat_price'] > 0)<label class="block text-xs font-medium" for="ps{{ $p['id'] }}">{{ tr3('Seats', 'Sièges', 'المقاعد') }}</label><input id="ps{{ $p['id'] }}" name="seats" type="number" min="{{ $p['seats_included'] }}" max="500" value="{{ $p['seats_included'] }}" class="field" inputmode="numeric">@else<input type="hidden" name="seats" value="{{ $p['seats_included'] }}">@endif
            <button class="btn-focus w-full" type="submit">{{ tr3('Get the invoice', 'Obtenir la facture', 'احصل على الفاتورة') }}</button></form>
        @elseif($p['quote_only'] && $p['active'])
          <p class="mt-4"><a class="btn-ghost w-full justify-center" href="{{ url('/contact/') }}">{{ tr3('Talk to the partners desk', 'Parler au bureau partenaires', 'تحدث إلى مكتب الشركاء') }}</a></p>
        @else
          <p class="mt-4 text-sm text-slate-600 dark:text-slate-300">{{ tr3('Not on sale yet.', 'Pas encore en vente.', 'غير معروضة للبيع بعد.') }}</p>
        @endif
      </article>
    @endforeach
  </div>
  @endif
@endif

@if($pl_ent['pipeline'])
  <h2 class="mt-10 font-display text-lg font-semibold">{{ tr3('Pipeline: certified people today', 'Pipeline : personnes certifiées aujourd’hui', 'المسار: الأشخاص المعتمدون اليوم') }}</h2>
  <p class="text-sm text-slate-600 dark:text-slate-300">{{ tr3('Counts only. No names, no contact details. A certificate proves a passed online round, not availability.', 'Des chiffres seulement. Pas de noms ni de coordonnées. Un certificat atteste d’une session réussie, pas d’une disponibilité.', 'أرقام فقط. بلا أسماء ولا بيانات اتصال. الشهادة تثبت اجتياز جولة وليست إتاحة للعمل.') }}</p>
  @if(!$pl_pipeline)<p class="mt-3 text-sm">{{ __('lbl.none') }}</p>@else
  <div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10"><table class="w-full text-sm"><thead><tr class="text-start text-xs uppercase text-slate-600 dark:text-slate-300"><th class="p-3 text-start" scope="col">{{ tr3('Service', 'Service', 'الخدمة') }}</th><th class="p-3 text-start" scope="col">{{ tr3('Level', 'Niveau', 'المستوى') }}</th><th class="p-3 text-end" scope="col">{{ tr3('People', 'Personnes', 'أشخاص') }}</th></tr></thead><tbody>
    @foreach($pl_pipeline as $r)<tr class="border-t border-slate-200 dark:border-white/10"><td class="p-3">{{ \W5D\Certificate::serviceTitle((string)$r['service_slug']) }}</td><td class="p-3">L{{ $r['level'] }}</td><td class="p-3 text-end tabular-nums">{{ $r['n'] }}</td></tr>@endforeach
  </tbody></table></div>@endif
@endif
@if($pl_ent['weekly'])
  <h2 class="mt-10 font-display text-lg font-semibold">{{ tr3('Weekly review summary', 'Résumé hebdomadaire des revues', 'ملخص المراجعة الأسبوعي') }}</h2>
  @if(!$pl_weekly)<p class="mt-3 text-sm">{{ __('lbl.none') }}</p>@else
  <div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10"><table class="w-full text-sm"><thead><tr class="text-xs uppercase text-slate-600 dark:text-slate-300"><th class="p-3 text-start" scope="col">{{ tr3('Week', 'Semaine', 'الأسبوع') }}</th><th class="p-3 text-start" scope="col">{{ tr3('Service', 'Service', 'الخدمة') }}</th><th class="p-3 text-end" scope="col">{{ tr3('Reports', 'Rapports', 'تقارير') }}</th><th class="p-3 text-end" scope="col">{{ tr3('Approved', 'Approuvés', 'معتمدة') }}</th></tr></thead><tbody>
    @foreach($pl_weekly as $r)<tr class="border-t border-slate-200 dark:border-white/10"><td class="p-3 tabular-nums">{{ $r['week_id'] }}</td><td class="p-3">{{ \W5D\Certificate::serviceTitle((string)$r['service_slug']) }}</td><td class="p-3 text-end tabular-nums">{{ $r['reports'] }}</td><td class="p-3 text-end tabular-nums">{{ $r['approved'] }}</td></tr>@endforeach
  </tbody></table></div>@endif
@endif
<p class="mt-8 text-xs text-slate-600 dark:text-slate-400">{{ __('limits.returns') }}</p>

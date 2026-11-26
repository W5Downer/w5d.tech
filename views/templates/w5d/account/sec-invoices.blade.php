@php
  $cur = \W5D\Billing::currency();
  $view = fn($n) => url('/invoice/' . $n . '/') . '?s=' . \W5D\Secret::sign('inv:' . $n);
  $kindL = fn($k) => match($k) { 'order' => tr3('Pledge', 'Engagement', 'التزام'), 'qcm_pass' => tr3('QCM fee', 'Frais QCM', 'رسوم اختبار'), 'plan' => tr3('Plan', 'Formule', 'باقة'), 'cert' => tr3('Certification fee', 'Frais de certification', 'رسوم اعتماد'), 'service' => tr3('Service milestone', 'Jalon de service', 'مرحلة خدمة'), 'placement' => tr3('Placement', 'Placement', 'توظيف'), default => tr3('Invoice', 'Facture', 'فاتورة') };
  $stT = fn($s) => match($s) { 'paid' => tr3('Paid', 'Payée', 'مدفوعة'), 'void' => tr3('Void', 'Annulée', 'ملغاة'), default => tr3('To pay', 'À payer', 'للدفع') };
  $open = array_filter($bill_rows, fn($r) => $r['status'] === 'sent');
@endphp
<p class="mt-3 text-slate-600 dark:text-slate-300 max-w-3xl">{{ tr3('Every payment on W5D has an invoice: your pledges, QCM and certification fees, plans, service milestones. Pay an open one with the same checkout, or print a paid one as a receipt.', 'Chaque paiement sur W5D a sa facture : engagements, frais QCM et de certification, formules, jalons de service. Payez une facture ouverte avec le même paiement, ou imprimez une facture payée comme reçu.', 'لكل دفعة في W5D فاتورة: الالتزامات ورسوم الاختبار والاعتماد والباقات ومراحل الخدمة. ادفع الفاتورة المفتوحة بنفس صفحة الدفع أو اطبع المدفوعة كإيصال.') }}</p>
@if($open)<p class="mt-3 inline-block rounded-full bg-amber-500/15 text-amber-800 dark:text-amber-300 px-3 py-1 text-sm" role="status">{{ count($open) }} {{ tr3('invoice(s) waiting for payment', 'facture(s) en attente de paiement', 'فاتورة بانتظار الدفع') }}</p>@endif
@if(!$bill_rows)
  <p class="mt-6 rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-6 text-slate-600 dark:text-slate-300">{{ tr3('No invoice yet.', 'Aucune facture pour le moment.', 'لا توجد فواتير بعد.') }}</p>
@else
  <ul class="mt-6 space-y-3">
  @foreach($bill_rows as $r)
    <li class="rounded-2xl border border-slate-200 dark:border-white/10 p-4 flex flex-wrap items-center gap-3 justify-between" data-invoice="{{ $r['number'] }}">
      <div><p class="font-mono text-sm">{{ $r['number'] }}</p><p class="text-xs text-slate-600 dark:text-slate-300">{{ $kindL($r['kind']) }} · {{ number_format((float)$r['amount'], 2) }} {{ $r['currency'] }} · {{ substr((string)$r['issued_at'], 0, 10) }}@if($r['status'] === 'sent' && $r['due_on']) · {{ tr3('due', 'échéance', 'الاستحقاق') }} {{ $r['due_on'] }}@endif</p></div>
      <div class="flex items-center gap-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $stl($r['status']) }}">{{ $stT($r['status']) }}</span>
        <a class="btn-ghost text-sm" href="{{ $view($r['number']) }}">{{ tr3('View', 'Voir', 'عرض') }}</a>
        @if($r['status'] === 'sent')<a class="btn-focus text-sm" href="{{ url('/account/invoices/pay/' . $r['number'] . '/') }}">{{ tr3('Pay', 'Payer', 'ادفع') }}</a>@endif</div>
    </li>
  @endforeach
  </ul>
@endif

@if($bill_eng)
  <h2 class="mt-10 font-display text-lg font-semibold">{{ tr3('Your service projects', 'Vos projets de service', 'مشاريع الخدمة الخاصة بك') }}</h2>
  <p class="mt-1 text-sm text-slate-600 dark:text-slate-300 max-w-3xl">{{ tr3('A scoped project is paid by milestone. You pay a milestone, W5D delivers it, and a second reviewer accepts it before it is closed. Work starts on a milestone once its payment is verified.', 'Un projet cadré se paie par jalon. Vous payez un jalon, W5D le livre, et un second relecteur l’accepte avant clôture. Le travail démarre sur un jalon une fois son paiement vérifié.', 'يُدفع المشروع المحدد النطاق بالمراحل. تدفع مرحلة، ثم تسلّمها W5D، ويقبلها مراجع ثان قبل الإغلاق. يبدأ العمل في المرحلة بعد التحقق من دفعها.') }}</p>
  @foreach($bill_eng as $e)
    <div class="mt-4 rounded-2xl border border-slate-200 dark:border-white/10 p-4">
      <p class="flex flex-wrap items-center gap-2"><span class="font-semibold">{{ $e['title'] }}</span><span class="font-mono text-xs text-slate-600 dark:text-slate-300">{{ $e['ref'] }}</span><span class="tier-badge">{{ $e['status'] }}</span></p>
      @if($e['scope'])<p class="mt-1 text-sm text-slate-600 dark:text-slate-300" dir="auto">{{ $e['scope'] }}</p>@endif
      @if($e['ms'])<ol class="mt-3 divide-y divide-slate-200 dark:divide-white/10 text-sm">
        @foreach($e['ms'] as $m)<li class="py-2 flex flex-wrap items-center justify-between gap-2"><span dir="auto">{{ $m['title'] }} <span class="text-xs text-slate-600 dark:text-slate-300">· {{ number_format((float)$m['amount'], 2) }} {{ $cur }}</span></span><span class="flex items-center gap-2">@if($m['inv_number'])<a class="btn-link text-xs" href="{{ $view($m['inv_number']) }}">{{ $m['inv_number'] }}</a>@endif<span class="tier-badge">{{ $m['status'] }}</span></span></li>@endforeach
      </ol>@endif
    </div>
  @endforeach
@endif
<p class="mt-8 text-xs text-slate-600 dark:text-slate-400">{{ __('limits.returns') }}</p>

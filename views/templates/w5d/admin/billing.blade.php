@php
  $k = $v['kind'];
  $act = admin_url('billing/act');
  $cur = \W5D\Billing::currency();
  $stc = fn($s) => match($s) { 'paid', 'funded', 'released', 'active' => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300', 'void', 'cancelled', 'disputed' => 'bg-red-500/15 text-red-700 dark:text-red-300', default => 'bg-amber-500/15 text-amber-800 dark:text-amber-300' };
  $mine = fn($n) => url('/invoice/' . $n . '/') . '?s=' . \W5D\Secret::sign('inv:' . $n);
@endphp

@if($k === 'bill-invoices')
  <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-4"><p class="text-xs text-slate-600 dark:text-slate-300">{{ tr3('Open invoices', 'Factures ouvertes', 'فواتير مفتوحة') }}</p><p class="font-display text-2xl font-bold tabular-nums">{{ $v['stats']['open_n'] }}</p><p class="text-xs tabular-nums text-slate-600 dark:text-slate-300">{{ number_format($v['stats']['open_sum'], 2) }} {{ $cur }}</p></div>
    <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-4"><p class="text-xs text-slate-600 dark:text-slate-300">{{ tr3('Paid this month', 'Payées ce mois-ci', 'مدفوعة هذا الشهر') }}</p><p class="font-display text-2xl font-bold tabular-nums">{{ $v['stats']['paid_n'] }}</p><p class="text-xs tabular-nums text-slate-600 dark:text-slate-300">{{ number_format($v['stats']['paid_sum'], 2) }} {{ $cur }}</p></div>
    <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-4"><p class="text-xs text-slate-600 dark:text-slate-300">{{ tr3('Active subscriptions', 'Abonnements actifs', 'اشتراكات نشطة') }}</p><p class="font-display text-2xl font-bold tabular-nums">{{ $v['stats']['subs'] }}</p></div>
  </div>
  @if($v['can'])
  <details class="mb-5 rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-4 sm:p-5">
    <summary class="cursor-pointer font-semibold">{{ tr3('Create an invoice by hand', 'Créer une facture à la main', 'أنشئ فاتورة يدويا') }}</summary>
    <form method="post" action="{{ $act }}" class="mt-4 grid gap-3 sm:grid-cols-2" novalidate>
      {!! csrf_field() !!}<input type="hidden" name="do" value="invoice-new">
      <div><label class="block text-sm font-medium mb-1" for="in-email">{{ tr3('Client e-mail', 'E-mail du client', 'بريد العميل') }}</label><input id="in-email" name="email" type="email" class="field" required autocomplete="off"></div>
      <div><label class="block text-sm font-medium mb-1" for="in-name">{{ tr3('Client name (optional)', 'Nom du client (facultatif)', 'اسم العميل (اختياري)') }}</label><input id="in-name" name="name" class="field" maxlength="120"></div>
      <div class="sm:col-span-2"><label class="block text-sm font-medium mb-1" for="in-desc">{{ tr3('What is it for', 'Objet de la facture', 'سبب الفاتورة') }}</label><input id="in-desc" name="desc" class="field" maxlength="200" required></div>
      <div><label class="block text-sm font-medium mb-1" for="in-amt">{{ tr3('Amount', 'Montant', 'المبلغ') }} ({{ $cur }})</label><input id="in-amt" name="amount" inputmode="decimal" class="field" required></div>
      <div><label class="block text-sm font-medium mb-1" for="in-note">{{ tr3('Note on the invoice (optional)', 'Note sur la facture (facultatif)', 'ملاحظة على الفاتورة (اختياري)') }}</label><input id="in-note" name="note" class="field" maxlength="300"></div>
      <div class="sm:col-span-2"><button class="btn-peak" type="submit">{{ tr3('Create and e-mail it', 'Créer et envoyer par e-mail', 'أنشئها وأرسلها بالبريد') }}</button> <span class="text-xs text-slate-600 dark:text-slate-300 ms-2">{{ tr3('The client pays it with any enabled payment method.', 'Le client la règle avec n’importe quel moyen de paiement activé.', 'يدفعها العميل بأي وسيلة دفع مفعّلة.') }}</span></div>
    </form>
  </details>
  @endif
  @if(empty($v['rows']))
    <p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-8 text-center text-slate-600 dark:text-slate-300">{{ __('lbl.none') }}</p>
  @else
  <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5">
    <table class="w5-table"><thead><tr><th scope="col">{{ tr3('Invoice', 'Facture', 'الفاتورة') }}</th><th scope="col">{{ tr3('Kind', 'Type', 'النوع') }}</th><th scope="col">{{ tr3('Client', 'Client', 'العميل') }}</th><th scope="col" class="text-end">{{ tr3('Amount', 'Montant', 'المبلغ') }}</th><th scope="col">{{ tr3('Due / paid', 'Échéance / payée', 'الاستحقاق / الدفع') }}</th><th scope="col">{{ tr3('Status', 'Statut', 'الحالة') }}</th><th scope="col">{{ tr3('Action', 'Action', 'إجراء') }}</th></tr></thead><tbody>
    @foreach($v['rows'] as $r)
      <tr>
        <td class="font-mono text-xs"><a class="btn-link" href="{{ $mine($r['number']) }}">{{ $r['number'] }}</a><br><span class="text-slate-600 dark:text-slate-300">{{ $r['source_ref'] }}</span></td>
        <td><span class="tier-badge">{{ $r['kind'] }}</span></td>
        <td>{{ $r['name'] ?: '—' }}<br><span class="text-xs text-slate-600 dark:text-slate-300">{{ $r['email'] }}</span></td>
        <td class="text-end tabular-nums">{{ number_format((float)$r['amount'], 2) }} {{ $r['currency'] }}</td>
        <td class="tabular-nums text-xs">{{ $r['status'] === 'paid' ? $r['paid_at'] : $r['due_on'] }}@if($r['method'])<br>{{ $r['method'] }}@endif</td>
        <td><span class="rounded-full px-2 py-0.5 text-xs {{ $stc($r['status']) }}">{{ $r['status'] }}</span></td>
        <td>
          @if($v['can'] && $r['status'] === 'sent')
          <form method="post" action="{{ $act }}" class="flex flex-col gap-1 min-w-[11rem]">
            {!! csrf_field() !!}<input type="hidden" name="id" value="{{ $r['id'] }}">
            <label class="sr-only" for="n{{ $r['id'] }}">{{ tr3('Reason or note', 'Raison ou note', 'السبب أو ملاحظة') }}</label><input id="n{{ $r['id'] }}" class="field !py-1 !text-xs" name="note" maxlength="300" placeholder="{{ tr3('Reason / how it was received', 'Raison / comment reçu', 'السبب / كيف وصل المال') }}">
            <div class="flex gap-1 flex-wrap">
              <button class="btn-ghost !py-1 !px-2 text-xs" name="do" value="invoice-send" type="submit">{{ tr3('Remind', 'Relancer', 'تذكير') }}</button>
              <button class="btn-ghost !py-1 !px-2 text-xs" name="do" value="invoice-void" type="submit">{{ tr3('Void', 'Annuler', 'إلغاء') }}</button>
              @if(\W5D\Auth::can('billing.settle'))<button class="btn-focus !py-1 !px-2 text-xs" name="do" value="invoice-paid" type="submit" data-confirm="{{ tr3('Record this invoice as paid outside the platform?', 'Enregistrer cette facture comme payée hors de la plateforme ?', 'تسجيل هذه الفاتورة كمدفوعة خارج المنصة؟') }}">{{ tr3('Paid elsewhere', 'Payée ailleurs', 'مدفوعة في مكان آخر') }}</button>@endif
            </div>
          </form>
          @else — @endif
        </td>
      </tr>
    @endforeach
    </tbody></table>
  </div>
  <p class="text-xs text-slate-600 dark:text-slate-300 mt-2">{{ count($v['rows']) }} {{ __('admin.rows') }}</p>
  @endif

@elseif($k === 'bill-mine')
  @if(empty($v['rows']))
    <p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-8 text-center text-slate-600 dark:text-slate-300">{{ tr3('No invoices or bills yet. Everything you pay or are billed for on W5D appears here.', 'Aucune facture pour le moment. Tout ce que vous payez ou qui vous est facturé sur W5D apparaît ici.', 'لا توجد فواتير بعد. كل ما تدفعه أو يُفوتر لك في W5D يظهر هنا.') }}</p>
  @else
  <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5">
    <table class="w5-table"><thead><tr><th scope="col">{{ tr3('Invoice', 'Facture', 'الفاتورة') }}</th><th scope="col">{{ tr3('For', 'Objet', 'عن') }}</th><th scope="col" class="text-end">{{ tr3('Amount', 'Montant', 'المبلغ') }}</th><th scope="col">{{ tr3('Status', 'Statut', 'الحالة') }}</th><th scope="col">{{ tr3('Open', 'Ouvrir', 'فتح') }}</th></tr></thead><tbody>
    @foreach($v['rows'] as $r)
      <tr>
        <td class="font-mono text-xs">{{ $r['number'] }}<br><span class="text-slate-600 dark:text-slate-300">{{ $r['issued_at'] }}</span></td>
        <td><span class="tier-badge">{{ $r['kind'] }}</span></td>
        <td class="text-end tabular-nums">{{ number_format((float)$r['amount'], 2) }} {{ $r['currency'] }}</td>
        <td><span class="rounded-full px-2 py-0.5 text-xs {{ $stc($r['status']) }}">{{ $r['status'] }}</span></td>
        <td class="flex flex-wrap gap-1"><a class="btn-ghost !py-1 !px-2 text-xs" href="{{ $mine($r['number']) }}">{{ tr3('View', 'Voir', 'عرض') }}</a>@if($r['status'] === 'sent')<a class="btn-focus !py-1 !px-2 text-xs" href="{{ url('/account/invoices/pay/' . $r['number'] . '/') }}">{{ tr3('Pay', 'Payer', 'ادفع') }}</a>@endif</td>
      </tr>
    @endforeach
    </tbody></table>
  </div>
  @endif

@elseif($k === 'bill-subs')
  <p class="mb-4 max-w-3xl text-sm text-slate-600 dark:text-slate-300">{{ tr3('Plans (Individual / Team / Enterprise) are defined in ', 'Les formules (Individuelle / Équipe / Entreprise) se définissent dans ', 'تُعرَّف الباقات (فردية / فريق / مؤسسة) في ') }}<a class="btn-link" href="{{ admin_url('plans/') }}">{{ tr3('Plans', 'Formules', 'الباقات') }}</a>{{ tr3('. A plan renews by invoice: the buyer is e-mailed the renewal and pays it like any other bill; a plan not paid by the end of its period stops.', '. Une formule se renouvelle par facture : l’acheteur reçoit le renouvellement par e-mail et le règle comme n’importe quelle facture ; une formule non payée à la fin de sa période s’arrête.', '. تتجدد الباقة بفاتورة: يصل التجديد إلى المشتري بالبريد ويدفعه كأي فاتورة؛ والباقة غير المدفوعة عند نهاية فترتها تتوقف.') }}</p>
  @if(empty($v['rows']))
    <p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-8 text-center text-slate-600 dark:text-slate-300">{{ __('lbl.none') }}</p>
  @else
  <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5">
    <table class="w5-table"><thead><tr><th scope="col">{{ tr3('Ref', 'Réf.', 'المرجع') }}</th><th scope="col">{{ tr3('Buyer', 'Acheteur', 'المشتري') }}</th><th scope="col">{{ tr3('Plan', 'Formule', 'الباقة') }}</th><th scope="col" class="text-end">{{ tr3('Seats', 'Places', 'المقاعد') }}</th><th scope="col" class="text-end">{{ tr3('Amount', 'Montant', 'المبلغ') }}</th><th scope="col">{{ tr3('Period end', 'Fin de période', 'نهاية الفترة') }}</th><th scope="col">{{ tr3('Status', 'Statut', 'الحالة') }}</th><th scope="col">{{ tr3('Action', 'Action', 'إجراء') }}</th></tr></thead><tbody>
    @foreach($v['rows'] as $r)
      <tr>
        <td class="font-mono text-xs">{{ $r['ref'] }}</td>
        <td>{{ $r['name'] }}<br><span class="text-xs text-slate-600 dark:text-slate-300">{{ $r['email'] }}</span></td>
        <td>{{ $r['plan'] }} · {{ $r['period'] }}</td>
        <td class="text-end tabular-nums">{{ $r['seats'] }}</td>
        <td class="text-end tabular-nums">{{ number_format((float)$r['amount'], 2) }} {{ $cur }}</td>
        <td class="tabular-nums text-xs">{{ $r['ends'] ?: '—' }}@if($r['cancelling'])<br>{{ tr3('stops then', 's’arrête alors', 'تتوقف حينها') }} @endif</td>
        <td><span class="rounded-full px-2 py-0.5 text-xs {{ $stc($r['status']) }}">{{ $r['status'] }}</span></td>
        <td>
          @if($v['can'] && in_array($r['status'], ['active', 'pending'], true))
          <form method="post" action="{{ $act }}" class="flex flex-col gap-1 min-w-[10rem]">{!! csrf_field() !!}<input type="hidden" name="do" value="sub-cancel"><input type="hidden" name="id" value="{{ $r['id'] }}">
            <label class="sr-only" for="sc{{ $r['id'] }}">{{ tr3('Reason', 'Raison', 'السبب') }}</label><input id="sc{{ $r['id'] }}" class="field !py-1 !text-xs" name="note" maxlength="300" placeholder="{{ tr3('Reason', 'Raison', 'السبب') }}">
            <button class="btn-ghost !py-1 !px-2 text-xs" type="submit" data-confirm="{{ tr3('Cancel this subscription now?', 'Annuler cet abonnement maintenant ?', 'إلغاء هذا الاشتراك الآن؟') }}">{{ tr3('Cancel now', 'Annuler maintenant', 'إلغاء الآن') }}</button></form>
          @else — @endif
        </td>
      </tr>
    @endforeach
    </tbody></table>
  </div>
  @endif

@elseif($k === 'bill-ms')
  <p class="mb-4 max-w-3xl text-sm text-slate-600 dark:text-slate-300">{{ tr3('How a service-fee milestone moves: ', 'Le parcours d’un jalon de frais de service : ', 'مسار مرحلة رسوم الخدمة: ') }}<strong>{{ tr3('planned', 'planifié', 'مخطط') }}</strong>{{ tr3(' → you send the invoice → the client pays (', ' → vous envoyez la facture → le client paie (', ' ← ترسل الفاتورة ← يدفع العميل (') }}<strong>{{ tr3('funded', 'financé', 'ممول') }}</strong>{{ tr3(') → the team marks it ', ') → l’équipe le marque ', ') ← يعلّمها الفريق ') }}<strong>{{ tr3('delivered', 'livré', 'مسلَّم') }}</strong>{{ tr3(' → a second reviewer accepts it (', ' → un second relecteur l’accepte (', ' ← يقبلها مراجع ثان (') }}<strong>{{ tr3('released', 'validé', 'معتمد') }}</strong>{{ tr3(') or sends it back (', ') ou le renvoie (', ') أو يعيدها (') }}<strong>{{ tr3('disputed', 'contesté', 'متنازع عليه') }}</strong>{{ tr3('). The person who delivered cannot release it unless they are the owner. Work should start only on a funded milestone. Milestones are added inside each ', '). La personne qui a livré ne peut pas le valider, sauf si elle est le propriétaire. Le travail ne doit commencer que sur un jalon financé. Les jalons s’ajoutent dans chaque ', '). لا يستطيع من سلّم أن يعتمدها إلا إذا كان المالك. لا يبدأ العمل إلا على مرحلة ممولة. تُضاف المراحل داخل كل ') }}<a class="btn-link" href="{{ admin_url('engagements/') }}">{{ tr3('engagement', 'mission', 'مهمة') }}</a>{{ tr3('; the review window is ', '; la fenêtre de relecture est de ', '؛ مدة المراجعة ') }}{{ $v['hours'] }} {{ tr3('h', 'h', 'ساعة') }}.</p>
  @if(empty($v['rows']))
    <p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-8 text-center text-slate-600 dark:text-slate-300">{{ __('lbl.none') }}</p>
  @else
  <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5">
    <table class="w5-table"><thead><tr><th scope="col">{{ tr3('Milestone', 'Jalon', 'المرحلة') }}</th><th scope="col">{{ tr3('Engagement', 'Mission', 'المهمة') }}</th><th scope="col" class="text-end">{{ tr3('Amount', 'Montant', 'المبلغ') }}</th><th scope="col">{{ tr3('Invoice', 'Facture', 'الفاتورة') }}</th><th scope="col">{{ tr3('Status', 'Statut', 'الحالة') }}</th><th scope="col">{{ tr3('Action', 'Action', 'إجراء') }}</th></tr></thead><tbody>
    @foreach($v['rows'] as $r)
      <tr>
        <td><span class="font-mono text-xs">{{ \W5D\Billing::milestoneRef((int)$r['id']) }}</span><br>{{ $r['title'] }}</td>
        <td>{{ $r['etitle'] }}<br><span class="text-xs text-slate-600 dark:text-slate-300">{{ $r['client_email'] }}</span></td>
        <td class="text-end tabular-nums">{{ number_format((float)$r['amount'], 2) }} {{ $cur }}</td>
        <td class="font-mono text-xs">{{ $r['inv_number'] ?: '—' }}@if($r['inv_status'])<br>{{ $r['inv_status'] }}@endif</td>
        <td><span class="rounded-full px-2 py-0.5 text-xs {{ $stc($r['status']) }}">{{ $r['status'] }}</span>@if($r['delivery_note'])<br><span class="text-xs">{{ $r['delivery_note'] }}</span>@endif @if($r['review_note'])<br><span class="text-xs">↳ {{ $r['review_note'] }}</span>@endif</td>
        <td>
          <form method="post" action="{{ $act }}" class="flex flex-col gap-1 min-w-[12rem]">{!! csrf_field() !!}<input type="hidden" name="id" value="{{ $r['id'] }}">
            @if(in_array($r['status'], ['funded', 'delivered', 'disputed'], true))<label class="sr-only" for="m{{ $r['id'] }}">{{ tr3('Note', 'Note', 'ملاحظة') }}</label><input id="m{{ $r['id'] }}" class="field !py-1 !text-xs" name="note" maxlength="300" placeholder="{{ tr3('Note (what was delivered / what is missing)', 'Note (ce qui a été livré / ce qui manque)', 'ملاحظة (ما سُلّم / ما ينقص)') }}">@endif
            <div class="flex gap-1 flex-wrap">
              @if($v['can'] && in_array($r['status'], ['planned', 'invoiced'], true) && (float)$r['amount'] > 0)<button class="btn-focus !py-1 !px-2 text-xs" name="do" value="ms-invoice" type="submit">{{ $r['status'] === 'planned' ? tr3('Send invoice', 'Envoyer la facture', 'أرسل الفاتورة') : tr3('Re-issue', 'Réémettre', 'أعد الإصدار') }}</button>@endif
              @if($v['can'] && $r['status'] === 'funded')<button class="btn-focus !py-1 !px-2 text-xs" name="do" value="ms-deliver" type="submit">{{ tr3('Mark delivered', 'Marquer livré', 'وسم كمسلَّم') }}</button>@endif
              @if($v['review'] && $r['status'] === 'delivered')<button class="btn-focus !py-1 !px-2 text-xs" name="do" value="ms-release" type="submit">{{ tr3('Accept & release', 'Accepter et valider', 'اقبل واعتمد') }}</button><button class="btn-ghost !py-1 !px-2 text-xs" name="do" value="ms-dispute" type="submit">{{ tr3('Send back', 'Renvoyer', 'أعد للتعديل') }}</button>@endif
              @if($v['review'] && $r['status'] === 'disputed')<button class="btn-ghost !py-1 !px-2 text-xs" name="do" value="ms-reopen" type="submit">{{ tr3('Reopen', 'Rouvrir', 'أعد الفتح') }}</button>@endif
              @if(!in_array($r['status'], ['planned', 'invoiced', 'funded', 'delivered', 'disputed'], true))—@endif
            </div>
          </form>
        </td>
      </tr>
    @endforeach
    </tbody></table>
  </div>
  @endif

@elseif($k === 'bill-pl')
  <p class="mb-4 max-w-3xl text-sm text-slate-600 dark:text-slate-300">{{ tr3('A placement is matched supervised work or an enterprise talent contract. W5D’s fee is a percentage of the contract value: the default rate is the variable ', 'Un placement est un travail supervisé mis en relation ou un contrat de talent en entreprise. Les frais de W5D sont un pourcentage de la valeur du contrat : le taux par défaut est la variable ', 'التوظيف هو عمل مُشرف عليه تمت مطابقته أو عقد موهبة لمؤسسة. رسوم W5D نسبة من قيمة العقد: النسبة الافتراضية هي المتغير ') }}<code>billing.placement_rate_pct</code>{{ tr3(' (now ', ' (actuellement ', ' (حاليا ') }}{{ rtrim(rtrim(number_format($v['default_rate'], 2), '0'), '.') ?: '0' }} %){{ tr3(', or a rate set on the placement itself. The rate is frozen when the invoice is issued. Add placements with the New form of ', ', ou un taux fixé sur le placement lui-même. Le taux est figé à l’émission de la facture. Ajoutez des placements avec le formulaire Nouveau de ', '، أو نسبة تُحدد على التوظيف نفسه. تُثبَّت النسبة عند إصدار الفاتورة. أضف التوظيفات بنموذج جديد في ') }}<a class="btn-link" href="{{ admin_url('placements/new/') }}">{{ tr3('this resource', 'cette ressource', 'هذا المورد') }}</a>.</p>
  @if(empty($v['rows']))
    <p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-8 text-center text-slate-600 dark:text-slate-300">{{ __('lbl.none') }}</p>
  @else
  <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5">
    <table class="w5-table"><thead><tr><th scope="col">{{ tr3('Placement', 'Placement', 'التوظيف') }}</th><th scope="col">{{ tr3('Client', 'Client', 'العميل') }}</th><th scope="col" class="text-end">{{ tr3('Contract', 'Contrat', 'العقد') }}</th><th scope="col" class="text-end">{{ tr3('Rate', 'Taux', 'النسبة') }}</th><th scope="col" class="text-end">{{ tr3('Commission', 'Commission', 'العمولة') }}</th><th scope="col">{{ tr3('Invoice', 'Facture', 'الفاتورة') }}</th><th scope="col">{{ tr3('Status', 'Statut', 'الحالة') }}</th><th scope="col">{{ tr3('Action', 'Action', 'إجراء') }}</th></tr></thead><tbody>
    @foreach($v['rows'] as $r)
      <tr>
        <td><span class="font-mono text-xs">{{ $r['ref'] }}</span><br>{{ $r['title'] }}<br><span class="text-xs text-slate-600 dark:text-slate-300">{{ $r['kind'] }}</span></td>
        <td>{{ $r['client'] }}</td>
        <td class="text-end tabular-nums">{{ number_format((float)$r['contract_value'], 2) }}</td>
        <td class="text-end tabular-nums">{{ rtrim(rtrim(number_format((float)$r['rate'], 2), '0'), '.') ?: '0' }} %</td>
        <td class="text-end tabular-nums">{{ number_format((float)$r['fee'], 2) }} {{ $cur }}</td>
        <td class="font-mono text-xs">{{ $r['inv_number'] ?: '—' }}</td>
        <td><span class="rounded-full px-2 py-0.5 text-xs {{ $stc($r['status']) }}">{{ $r['status'] }}</span></td>
        <td>
          @if($v['can'] && $r['status'] === 'proposed')
          <form method="post" action="{{ $act }}" class="flex gap-1 flex-wrap">{!! csrf_field() !!}<input type="hidden" name="id" value="{{ $r['id'] }}">
            <button class="btn-focus !py-1 !px-2 text-xs" name="do" value="pl-invoice" type="submit" @if((float)$r['fee'] <= 0) disabled title="{{ tr3('Commission is 0: set a rate first', 'La commission est à 0 : fixez d’abord un taux', 'العمولة 0: حدد نسبة أولا') }}" @endif>{{ tr3('Invoice commission', 'Facturer la commission', 'فوتر العمولة') }}</button>
            <button class="btn-ghost !py-1 !px-2 text-xs" name="do" value="pl-cancel" type="submit" data-confirm="{{ tr3('Cancel this placement?', 'Annuler ce placement ?', 'إلغاء هذا التوظيف؟') }}">{{ tr3('Cancel', 'Annuler', 'إلغاء') }}</button></form>
          @else — @endif
        </td>
      </tr>
    @endforeach
    </tbody></table>
  </div>
  @endif
@endif

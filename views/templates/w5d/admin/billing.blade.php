@php
  $k = $v['kind'];
  $act = admin_url('billing/act');
  $cur = \W5D\Billing::currency();
  $stc = fn($s) => match($s) { 'paid', 'funded', 'released', 'active' => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300', 'void', 'cancelled', 'disputed' => 'bg-red-500/15 text-red-700 dark:text-red-300', default => 'bg-amber-500/15 text-amber-800 dark:text-amber-300' };
  $mine = fn($n) => url('/invoice/' . $n . '/') . '?s=' . \W5D\Secret::sign('inv:' . $n);
@endphp

@if($k === 'bill-invoices')
  <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-4"><p class="text-xs text-slate-600 dark:text-slate-300">Open invoices</p><p class="font-display text-2xl font-bold tabular-nums">{{ $v['stats']['open_n'] }}</p><p class="text-xs tabular-nums text-slate-600 dark:text-slate-300">{{ number_format($v['stats']['open_sum'], 2) }} {{ $cur }}</p></div>
    <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-4"><p class="text-xs text-slate-600 dark:text-slate-300">Paid this month</p><p class="font-display text-2xl font-bold tabular-nums">{{ $v['stats']['paid_n'] }}</p><p class="text-xs tabular-nums text-slate-600 dark:text-slate-300">{{ number_format($v['stats']['paid_sum'], 2) }} {{ $cur }}</p></div>
    <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-4"><p class="text-xs text-slate-600 dark:text-slate-300">Active subscriptions</p><p class="font-display text-2xl font-bold tabular-nums">{{ $v['stats']['subs'] }}</p></div>
  </div>
  @if($v['can'])
  <details class="mb-5 rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-4 sm:p-5">
    <summary class="cursor-pointer font-semibold">Create an invoice by hand</summary>
    <form method="post" action="{{ $act }}" class="mt-4 grid gap-3 sm:grid-cols-2" novalidate>
      {!! csrf_field() !!}<input type="hidden" name="do" value="invoice-new">
      <div><label class="block text-sm font-medium mb-1" for="in-email">Client e-mail</label><input id="in-email" name="email" type="email" class="field" required autocomplete="off"></div>
      <div><label class="block text-sm font-medium mb-1" for="in-name">Client name (optional)</label><input id="in-name" name="name" class="field" maxlength="120"></div>
      <div class="sm:col-span-2"><label class="block text-sm font-medium mb-1" for="in-desc">What is it for</label><input id="in-desc" name="desc" class="field" maxlength="200" required></div>
      <div><label class="block text-sm font-medium mb-1" for="in-amt">Amount ({{ $cur }})</label><input id="in-amt" name="amount" inputmode="decimal" class="field" required></div>
      <div><label class="block text-sm font-medium mb-1" for="in-note">Note on the invoice (optional)</label><input id="in-note" name="note" class="field" maxlength="300"></div>
      <div class="sm:col-span-2"><button class="btn-peak" type="submit">Create and e-mail it</button> <span class="text-xs text-slate-600 dark:text-slate-300 ms-2">The client pays it with any enabled payment method.</span></div>
    </form>
  </details>
  @endif
  @if(empty($v['rows']))
    <p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-8 text-center text-slate-600 dark:text-slate-300">{{ __('lbl.none') }}</p>
  @else
  <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5">
    <table class="w5-table"><thead><tr><th scope="col">Invoice</th><th scope="col">Kind</th><th scope="col">Client</th><th scope="col" class="text-end">Amount</th><th scope="col">Due / paid</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>
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
            <label class="sr-only" for="n{{ $r['id'] }}">Reason or note</label><input id="n{{ $r['id'] }}" class="field !py-1 !text-xs" name="note" maxlength="300" placeholder="Reason / how it was received">
            <div class="flex gap-1 flex-wrap">
              <button class="btn-ghost !py-1 !px-2 text-xs" name="do" value="invoice-send" type="submit">Remind</button>
              <button class="btn-ghost !py-1 !px-2 text-xs" name="do" value="invoice-void" type="submit">Void</button>
              @if(\W5D\Auth::can('billing.settle'))<button class="btn-focus !py-1 !px-2 text-xs" name="do" value="invoice-paid" type="submit" data-confirm="Record this invoice as paid outside the platform?">Paid elsewhere</button>@endif
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
  <p class="mb-4 max-w-3xl text-sm text-slate-600 dark:text-slate-300">Plans (Individual / Team / Enterprise) are defined in <a class="btn-link" href="{{ admin_url('plans/') }}">Plans</a>. A plan renews by invoice: the buyer is e-mailed the renewal and pays it like any other bill; a plan not paid by the end of its period stops.</p>
  @if(empty($v['rows']))
    <p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-8 text-center text-slate-600 dark:text-slate-300">{{ __('lbl.none') }}</p>
  @else
  <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5">
    <table class="w5-table"><thead><tr><th scope="col">Ref</th><th scope="col">Buyer</th><th scope="col">Plan</th><th scope="col" class="text-end">Seats</th><th scope="col" class="text-end">Amount</th><th scope="col">Period end</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>
    @foreach($v['rows'] as $r)
      <tr>
        <td class="font-mono text-xs">{{ $r['ref'] }}</td>
        <td>{{ $r['name'] }}<br><span class="text-xs text-slate-600 dark:text-slate-300">{{ $r['email'] }}</span></td>
        <td>{{ $r['plan'] }} · {{ $r['period'] }}</td>
        <td class="text-end tabular-nums">{{ $r['seats'] }}</td>
        <td class="text-end tabular-nums">{{ number_format((float)$r['amount'], 2) }} {{ $cur }}</td>
        <td class="tabular-nums text-xs">{{ $r['ends'] ?: '—' }}@if($r['cancelling'])<br>stops then @endif</td>
        <td><span class="rounded-full px-2 py-0.5 text-xs {{ $stc($r['status']) }}">{{ $r['status'] }}</span></td>
        <td>
          @if($v['can'] && in_array($r['status'], ['active', 'pending'], true))
          <form method="post" action="{{ $act }}" class="flex flex-col gap-1 min-w-[10rem]">{!! csrf_field() !!}<input type="hidden" name="do" value="sub-cancel"><input type="hidden" name="id" value="{{ $r['id'] }}">
            <label class="sr-only" for="sc{{ $r['id'] }}">Reason</label><input id="sc{{ $r['id'] }}" class="field !py-1 !text-xs" name="note" maxlength="300" placeholder="Reason">
            <button class="btn-ghost !py-1 !px-2 text-xs" type="submit" data-confirm="Cancel this subscription now?">Cancel now</button></form>
          @else — @endif
        </td>
      </tr>
    @endforeach
    </tbody></table>
  </div>
  @endif

@elseif($k === 'bill-ms')
  <p class="mb-4 max-w-3xl text-sm text-slate-600 dark:text-slate-300">How a service-fee milestone moves: <strong>planned</strong> → you send the invoice → the client pays (<strong>funded</strong>) → the team marks it <strong>delivered</strong> → a second reviewer accepts it (<strong>released</strong>) or sends it back (<strong>disputed</strong>). The person who delivered cannot release it unless they are the owner. Work should start only on a funded milestone. Milestones are added inside each <a class="btn-link" href="{{ admin_url('engagements/') }}">engagement</a>; the review window is {{ $v['hours'] }} h.</p>
  @if(empty($v['rows']))
    <p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-8 text-center text-slate-600 dark:text-slate-300">{{ __('lbl.none') }}</p>
  @else
  <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5">
    <table class="w5-table"><thead><tr><th scope="col">Milestone</th><th scope="col">Engagement</th><th scope="col" class="text-end">Amount</th><th scope="col">Invoice</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>
    @foreach($v['rows'] as $r)
      <tr>
        <td><span class="font-mono text-xs">{{ \W5D\Billing::milestoneRef((int)$r['id']) }}</span><br>{{ $r['title'] }}</td>
        <td>{{ $r['etitle'] }}<br><span class="text-xs text-slate-600 dark:text-slate-300">{{ $r['client_email'] }}</span></td>
        <td class="text-end tabular-nums">{{ number_format((float)$r['amount'], 2) }} {{ $cur }}</td>
        <td class="font-mono text-xs">{{ $r['inv_number'] ?: '—' }}@if($r['inv_status'])<br>{{ $r['inv_status'] }}@endif</td>
        <td><span class="rounded-full px-2 py-0.5 text-xs {{ $stc($r['status']) }}">{{ $r['status'] }}</span>@if($r['delivery_note'])<br><span class="text-xs">{{ $r['delivery_note'] }}</span>@endif @if($r['review_note'])<br><span class="text-xs">↳ {{ $r['review_note'] }}</span>@endif</td>
        <td>
          <form method="post" action="{{ $act }}" class="flex flex-col gap-1 min-w-[12rem]">{!! csrf_field() !!}<input type="hidden" name="id" value="{{ $r['id'] }}">
            @if(in_array($r['status'], ['funded', 'delivered', 'disputed'], true))<label class="sr-only" for="m{{ $r['id'] }}">Note</label><input id="m{{ $r['id'] }}" class="field !py-1 !text-xs" name="note" maxlength="300" placeholder="Note (what was delivered / what is missing)">@endif
            <div class="flex gap-1 flex-wrap">
              @if($v['can'] && in_array($r['status'], ['planned', 'invoiced'], true) && (float)$r['amount'] > 0)<button class="btn-focus !py-1 !px-2 text-xs" name="do" value="ms-invoice" type="submit">{{ $r['status'] === 'planned' ? 'Send invoice' : 'Re-issue' }}</button>@endif
              @if($v['can'] && $r['status'] === 'funded')<button class="btn-focus !py-1 !px-2 text-xs" name="do" value="ms-deliver" type="submit">Mark delivered</button>@endif
              @if($v['review'] && $r['status'] === 'delivered')<button class="btn-focus !py-1 !px-2 text-xs" name="do" value="ms-release" type="submit">Accept &amp; release</button><button class="btn-ghost !py-1 !px-2 text-xs" name="do" value="ms-dispute" type="submit">Send back</button>@endif
              @if($v['review'] && $r['status'] === 'disputed')<button class="btn-ghost !py-1 !px-2 text-xs" name="do" value="ms-reopen" type="submit">Reopen</button>@endif
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
  <p class="mb-4 max-w-3xl text-sm text-slate-600 dark:text-slate-300">A placement is matched supervised work or an enterprise talent contract. W5D’s fee is a percentage of the contract value: the default rate is the variable <code>billing.placement_rate_pct</code> (now {{ rtrim(rtrim(number_format($v['default_rate'], 2), '0'), '.') ?: '0' }} %), or a rate set on the placement itself. The rate is frozen when the invoice is issued. Add placements in this list’s <em>New</em> form on the client record screen of <a class="btn-link" href="{{ admin_url('placements/new/') }}">this resource</a>.</p>
  @if(empty($v['rows']))
    <p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-8 text-center text-slate-600 dark:text-slate-300">{{ __('lbl.none') }}</p>
  @else
  <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5">
    <table class="w5-table"><thead><tr><th scope="col">Placement</th><th scope="col">Client</th><th scope="col" class="text-end">Contract</th><th scope="col" class="text-end">Rate</th><th scope="col" class="text-end">Commission</th><th scope="col">Invoice</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>
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
            <button class="btn-focus !py-1 !px-2 text-xs" name="do" value="pl-invoice" type="submit" @if((float)$r['fee'] <= 0) disabled title="Commission is 0: set a rate first" @endif>Invoice commission</button>
            <button class="btn-ghost !py-1 !px-2 text-xs" name="do" value="pl-cancel" type="submit" data-confirm="Cancel this placement?">Cancel</button></form>
          @else — @endif
        </td>
      </tr>
    @endforeach
    </tbody></table>
  </div>
  @endif
@endif

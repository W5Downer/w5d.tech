@php
  $can = $v['can']; $t = $v['totals']; $tk = $v['tokens']; $b = admin_url('funding/'); $cur = \W5D\Variables::get('money.currency');
  $badge = fn($ok) => $ok ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200';
@endphp
<p class="mb-4 max-w-3xl text-sm text-slate-600 dark:text-slate-400">Mode <code>{{ $tk['mode'] }}</code>. Every switch and amount is a <code>.env</code> token (see <code>.env.example</code>). A channel is open only when every condition is proven; if a check cannot run, it stays closed. The legal and fiscal wording is research to verify with a Tunisian accountant and lawyer (<code>docs/LEGAL-REVIEW.md</code>).</p>
@if(!empty($flash))<p class="mb-4 rounded-lg border p-3 text-sm {{ $flash['kind'] === 'ok' ? 'border-emerald-500/40 bg-emerald-500/10' : 'border-red-500/40 bg-red-500/10' }}" role="status">{{ $flash['msg'] }}</p>@endif
@if($v['errors'])<div class="mb-6 rounded-2xl border border-red-500/40 bg-red-500/10 p-4 text-sm" role="alert" data-funding-errors><p class="font-semibold">Configuration errors: the build fails until these are fixed.</p><ul class="list-disc ps-5 mt-1">@foreach($v['errors'] as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<h2 class="font-display font-semibold mb-3">Registry activation fund: required vs achieved</h2>
<div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-5" data-funding-fund>
  <dl class="grid gap-4 sm:grid-cols-4 text-sm">
    <div><dt class="text-slate-500">Required (target)</dt><dd class="font-display text-2xl font-bold">{{ $tk['rne_budget_tnd'] }} {{ $cur }}</dd></div>
    <div><dt class="text-slate-500">Achieved (restricted F2 + completed F4)</dt><dd class="font-display text-2xl font-bold">{{ $tk['achieved_tnd'] }} {{ $cur }}</dd></div>
    <div><dt class="text-slate-500">Gap</dt><dd class="font-display text-2xl font-bold">{{ $tk['gap_tnd'] }} {{ $cur }}</dd></div>
    <div><dt class="text-slate-500">In escrow (not a sale)</dt><dd class="font-display text-2xl font-bold">{{ $tk['f4_escrowed_tnd'] }} {{ $cur }}</dd></div>
  </dl>
  <div class="mt-4 h-2 rounded-full bg-slate-200 dark:bg-white/10 overflow-hidden" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $t['pct'] }}"><i class="block h-full bg-gradient-to-r from-violet-500 to-cyan-500" style="width: {{ $t['pct'] }}%"></i></div>
  <p class="mt-2 text-xs text-slate-500">{{ $t['pct'] }}% of the target. Sponsors counted: {{ $t['sponsors'] }}. Activation fee token: {{ $tk['rne_fee_tnd'] }} {{ $cur }}. Minimum sponsorship: {{ $tk['f2_min_tnd'] }} {{ $cur }}.</p>
</div>

<h2 class="font-display font-semibold mt-10 mb-3">Channels</h2>
<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
@foreach($v['channels'] as $c)
  <div class="rounded-2xl border p-4 {{ $badge($c['available']) }}" data-channel="{{ $c['channel'] }}" data-available="{{ $c['available'] ? '1' : '0' }}">
    <p class="font-display font-semibold">{{ $c['channel'] }} · {{ $c['available'] ? 'available' : 'unavailable' }}</p>
    <p class="text-sm mt-1">{{ $c['reason_text'] }}</p><p class="text-xs mt-1 font-mono opacity-70">{{ $c['reason_code'] }}</p>
  </div>
@endforeach
</div>

<h2 class="font-display font-semibold mt-10 mb-3">F1 · Owner auto-entrepreneur registration</h2>
@if($v['ae'])<p class="text-sm mb-3">Recorded: <span class="font-mono">{{ $v['ae']['rvalue'] }}</span> on <strong>{{ $v['ae']['recorded_on'] }}</strong> by {{ $v['ae']['recorded_by'] }}.</p>@else<p class="text-sm mb-3 text-slate-500">Not recorded. F1 stays closed until the authority’s reference and its date are entered.</p>@endif
@if($can)
<form method="post" action="{{ $b . 'record' }}" class="flex flex-wrap gap-2 items-end">{!! csrf_field() !!}
  <label class="text-xs">Reference as issued<input class="field !py-1 text-sm block" name="reference" required maxlength="120"></label>
  <label class="text-xs">Date<input class="field !py-1 text-sm block" name="recorded_on" type="date" required></label>
  <label class="text-xs">Note<input class="field !py-1 text-sm block" name="note" maxlength="300"></label>
  <button class="btn-focus text-sm" type="submit">Record (append-only)</button>
</form>
@endif

<h2 class="font-display font-semibold mt-10 mb-3">F6 · Owner salary</h2>
<div class="rounded-2xl border p-4 {{ $badge($v['salary']['state'] === 'deferred') }}" data-salary-tile data-salary-state="{{ $v['salary']['state'] }}">
  <p class="font-display font-semibold">{{ $v['salary']['state'] === 'deferred' ? 'Deferred' : ($v['salary']['state'] === 'condition_met' ? 'Deferral condition met' : 'Not deferred') }}</p>
  <p class="text-sm mt-1">{{ $v['salary']['text'] }}</p>
</div>

<h2 class="font-display font-semibold mt-10 mb-3">F2 · Restricted sponsorships</h2>
<p class="text-sm mb-3 text-slate-600 dark:text-slate-400">Templates: <a class="btn-link" href="{{ $b . 'download/?kind=f2&pdf=1' }}">sponsorship agreement (PDF)</a>. Public form: <a class="btn-link" href="{{ url('/sponsor/') }}">/sponsor/</a>. A pledge is not money: it counts only after you record the signed agreement and the arrival date.</p>
@if(!$v['sponsorships'])<p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-5 text-sm text-slate-500">No pledge yet.</p>@else
<div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5"><table class="w5-table"><thead><tr><th>Ref</th><th>Sponsor</th><th class="text-end">Amount</th><th>Status</th><th>Action</th></tr></thead><tbody>
@foreach($v['sponsorships'] as $s)
  <tr><td class="font-mono text-xs">{{ $s['ref'] }}<br><span class="text-slate-500">{{ substr((string)$s['created_at'], 0, 10) }}</span></td><td>{{ $s['sponsor_name'] }}<br><span class="text-xs text-slate-500">{{ $s['email'] }}</span></td><td class="text-end tabular-nums">{{ number_format((float)$s['amount_tnd'], 2) }}</td><td><span class="tier-badge">{{ $s['status'] }}</span>@if($s['received_on'])<br><span class="text-xs">{{ $s['received_on'] }} · {{ $s['agreement_ref'] }}</span>@endif</td>
  <td>@if($can)
    @if($s['status'] === 'pledged')<form method="post" action="{{ $b . 'sponsorship' }}" class="flex flex-wrap gap-1.5 items-center">{!! csrf_field() !!}<input type="hidden" name="id" value="{{ $s['id'] }}"><input class="field !py-1 text-xs w-36" name="agreement_ref" placeholder="Signed agreement ref" aria-label="Agreement reference"><input class="field !py-1 text-xs" type="date" name="on" aria-label="Date received"><button class="btn-focus !py-1 !px-2 text-xs" name="status" value="received" type="submit">Mark received</button><button class="btn-ghost !py-1 !px-2 text-xs" name="status" value="cancelled" type="submit">Cancel</button></form>
    @elseif($s['status'] === 'received')<form method="post" action="{{ $b . 'sponsorship' }}" class="flex gap-1.5 items-center">{!! csrf_field() !!}<input type="hidden" name="id" value="{{ $s['id'] }}"><input class="field !py-1 text-xs" type="date" name="on" aria-label="Date refunded"><button class="btn-ghost !py-1 !px-2 text-xs" name="status" value="refunded" type="submit">Refund</button></form>@endif
    <a class="btn-link text-xs" href="{{ $b . 'download/?kind=f2&id=' . $s['id'] }}">Agreement (.txt)</a>
  @endif</td></tr>
@endforeach</tbody></table></div>
@endif

<h2 class="font-display font-semibold mt-10 mb-3">F4 · Pre-sold credits (escrow until “{{ $tk['f4_condition'] }}”)</h2>
<p class="text-sm mb-3 text-slate-600 dark:text-slate-400">Template: <a class="btn-link" href="{{ $b . 'download/?kind=f4&pdf=1' }}">pre-sale contract (PDF)</a>. Condition met now: <strong data-condition-met="{{ $v['condition_met'] ? '1' : '0' }}">{{ $v['condition_met'] ? 'yes' : 'no' }}</strong>. A pre-sale is never a completed sale before that.</p>
@if($can)
<form method="post" action="{{ $b . 'presale' }}" class="mb-4 flex flex-wrap gap-2 items-end">{!! csrf_field() !!}<input type="hidden" name="do" value="add">
  <label class="text-xs">Client<input class="field !py-1 text-sm block" name="client_name" required maxlength="120"></label>
  <label class="text-xs">E-mail<input class="field !py-1 text-sm block" name="email" type="email" required></label>
  <label class="text-xs">Amount<input class="field !py-1 text-sm block w-28" name="amount" required inputmode="decimal"></label>
  <label class="text-xs">Credits<input class="field !py-1 text-sm block w-20" name="credits" type="number" min="1" required></label>
  <button class="btn-focus text-sm" type="submit">Draft contract</button>
</form>
@endif
@if($v['presales'])
<div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5"><table class="w5-table"><thead><tr><th>Ref</th><th>Client</th><th class="text-end">Amount</th><th>Credits</th><th>Status</th><th>Action</th></tr></thead><tbody>
@foreach($v['presales'] as $p)
  <tr><td class="font-mono text-xs">{{ $p['ref'] }}</td><td>{{ $p['client_name'] }}<br><span class="text-xs text-slate-500">{{ $p['email'] }}</span></td><td class="text-end tabular-nums">{{ number_format((float)$p['amount_tnd'], 2) }}</td><td>{{ $p['credits'] }}</td><td><span class="tier-badge">{{ $p['status'] }}</span>@if($p['escrow_ref'])<br><span class="text-xs">{{ $p['escrow_ref'] }} · {{ $p['escrowed_on'] }}</span>@endif</td>
  <td>@if($can)<form method="post" action="{{ $b . 'presale' }}" class="flex flex-wrap gap-1.5 items-center">{!! csrf_field() !!}<input type="hidden" name="id" value="{{ $p['id'] }}"><input class="field !py-1 text-xs" type="date" name="on" aria-label="Date">@if($p['status'] === 'draft')<input class="field !py-1 text-xs w-32" name="escrow_ref" placeholder="Escrow ref" aria-label="Escrow reference"><button class="btn-focus !py-1 !px-2 text-xs" name="do" value="escrowed" type="submit">Escrowed</button>@endif @if($p['status'] === 'escrowed')<button class="btn-focus !py-1 !px-2 text-xs" name="do" value="completed" type="submit">Complete</button>@endif @if(in_array($p['status'], ['draft', 'escrowed'], true))<button class="btn-ghost !py-1 !px-2 text-xs" name="do" value="refunded" type="submit">Refund</button>@endif</form>
    <a class="btn-link text-xs" href="{{ $b . 'download/?kind=f4&id=' . $p['id'] }}">Contract (.txt)</a>@endif</td></tr>
@endforeach</tbody></table></div>
@endif

<h2 class="font-display font-semibold mt-10 mb-3">AdSense (accrued, never a dependency)</h2>
<div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-5 text-sm" data-ads-box>
  <dl class="grid gap-4 sm:grid-cols-4">
    <div><dt class="text-slate-500">Status</dt><dd class="font-display text-xl font-bold">{{ $v['ads']['status'] }}</dd></div>
    <div><dt class="text-slate-500">Accrued</dt><dd class="font-display text-xl font-bold">{{ $tk['ads_accrued_tnd'] }} {{ $cur }} <span class="text-xs font-normal">({{ $tk['ads_accrued_usd'] }} USD)</span></dd></div>
    <div><dt class="text-slate-500">Withdrawn</dt><dd class="font-display text-xl font-bold">{{ $tk['ads_withdrawn_tnd'] }} {{ $cur }}</dd></div>
    <div><dt class="text-slate-500">Withdrawal allowed</dt><dd class="font-display text-xl font-bold">{{ $v['ads']['can_withdraw'] ? 'yes' : 'no' }}</dd></div>
  </dl>
  <p class="mt-3" data-ads-gate="{{ $v['gate']['ok'] ? '1' : '0' }}"><strong>Can apply: {{ $v['gate']['ok'] ? 'yes' : 'no' }}.</strong> {{ $v['gate']['reason_text'] }} Substantive pages {{ $v['gate']['counted'] }} of {{ $v['gate']['needed'] }} (service {{ $v['gate']['by_kind']['service'] ?? 0 }}, macro {{ $v['gate']['by_kind']['macro'] ?? 0 }}, trust {{ $v['gate']['by_kind']['trust'] ?? 0 }}; each needs 5). Refresh with <code>php bin/ads-gate.php</code>{{ $v['gate']['computed_at'] ? ' (last ' . substr($v['gate']['computed_at'], 0, 16) . ')' : '' }}.</p>
</div>
<h3 class="font-display font-semibold mt-6 mb-2 text-sm">Rejection log (append-only)</h3>
@if($can)
<form method="post" action="{{ $b . 'ads-rejection' }}" class="mb-3 flex flex-wrap gap-2 items-end">{!! csrf_field() !!}
  <label class="text-xs">Date<input class="field !py-1 text-sm block" type="date" name="date" required></label>
  <label class="text-xs">Rejection type<input class="field !py-1 text-sm block" name="rejection_type" required maxlength="120"></label>
  <label class="text-xs grow">Corrective action<input class="field !py-1 text-sm block w-full" name="corrective_action" required maxlength="1000"></label>
  <button class="btn-focus text-sm" type="submit">Log</button>
</form>
@endif
@if($v['rejections'])
<div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5"><table class="w5-table"><thead><tr><th>Date</th><th>Type</th><th>Corrective action</th><th>Re-applied</th></tr></thead><tbody>
@foreach($v['rejections'] as $r)<tr><td class="font-mono text-xs">{{ $r['date'] }}</td><td>{{ $r['rejection_type'] }}</td><td class="max-w-md text-xs">{{ $r['corrective_action'] }}</td><td>@if($r['reapplied_at']){{ $r['reapplied_at'] }}@elseif($can)<form method="post" action="{{ $b . 'ads-reapplied' }}" class="flex gap-1.5">{!! csrf_field() !!}<input type="hidden" name="id" value="{{ $r['id'] }}"><input class="field !py-1 text-xs" type="date" name="on" aria-label="Re-applied on"><button class="btn-ghost !py-1 !px-2 text-xs" type="submit">Set</button></form>@else —@endif</td></tr>@endforeach
</tbody></table></div>
@else<p class="text-sm text-slate-500">No rejection logged.</p>@endif

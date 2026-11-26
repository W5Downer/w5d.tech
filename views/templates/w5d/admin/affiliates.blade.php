@php $can = $v['can']; $b = admin_url('affiliates/'); @endphp
<p class="mb-4 max-w-3xl text-sm text-slate-600 dark:text-slate-400">Programme {{ $v['enabled'] ? 'is ON' : 'is PAUSED' }}. Rates, hold days, minimum payout and auto-approval are in <a class="btn-link" href="{{ admin_url('variables/') }}">Variables</a> (group <code>affiliate</code>). Every commission line is approved by hand unless auto-approval is on; payouts are made by D17 and need the transaction reference.</p>

<h2 class="font-display font-semibold mb-3">Affiliates</h2>
@if(!$v['affiliates'])<p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-6 text-sm text-slate-600 dark:text-slate-400">No affiliate yet.</p>
@else
<div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5"><table class="w5-table"><thead><tr><th>Code</th><th>Person</th><th>Pitch</th><th class="text-end">Clicks</th><th class="text-end">Sign-ups</th><th class="text-end">Owed</th><th>Status</th><th>Change</th></tr></thead><tbody>
@foreach($v['affiliates'] as $a)
  <tr><td class="font-mono text-xs">{{ $a['code'] }}<br><span class="text-slate-600 dark:text-slate-400">{{ substr((string)$a['created_at'], 0, 10) }}</span></td><td>{{ $a['name'] }}<br><span class="text-xs text-slate-600 dark:text-slate-400">{{ $a['email'] }}</span></td><td class="max-w-xs text-xs">{{ $a['pitch'] }}</td><td class="text-end tabular-nums">{{ $a['clicks'] }}</td><td class="text-end tabular-nums">{{ $a['signups'] }}</td><td class="text-end tabular-nums">{{ number_format((float)$a['owed'], 2) }}</td><td><span class="tier-badge">{{ $a['status'] }}</span></td>
  <td>@if($can)<form method="post" action="{{ $b . 'status' }}" class="flex gap-1.5">{!! csrf_field() !!}<input type="hidden" name="id" value="{{ $a['id'] }}">@if($a['status'] !== 'active')<button class="btn-focus !py-1 !px-2 text-xs" name="status" value="active" type="submit">Approve</button>@endif @if($a['status'] !== 'suspended')<button class="btn-ghost !py-1 !px-2 text-xs" name="status" value="suspended" type="submit">Suspend</button>@endif</form>@endif</td></tr>
@endforeach</tbody></table></div>
@endif

<h2 class="font-display font-semibold mt-10 mb-3">Commission lines waiting for a decision</h2>
@if(!$v['refs'])<p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-6 text-sm text-slate-600 dark:text-slate-400">Nothing waiting.</p>
@else
<div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5"><table class="w5-table"><thead><tr><th>#</th><th>Affiliate</th><th>Kind</th><th class="text-end">Commission</th><th>Date</th><th>Decision</th></tr></thead><tbody>
@foreach($v['refs'] as $r)
  <tr><td>{{ $r['id'] }}</td><td class="font-mono text-xs">{{ $r['code'] }}</td><td>{{ $r['kind'] }}</td><td class="text-end tabular-nums">{{ number_format((float)$r['commission'], 2) }}</td><td class="text-xs">{{ $r['created_at'] }}</td>
  <td>@if($can)<form method="post" action="{{ $b . 'referral' }}" class="flex gap-1.5">{!! csrf_field() !!}<input type="hidden" name="id" value="{{ $r['id'] }}"><button class="btn-focus !py-1 !px-2 text-xs" name="decision" value="approve" type="submit">Approve</button><button class="btn-ghost !py-1 !px-2 text-xs" name="decision" value="reject" type="submit">Reject</button></form>@endif</td></tr>
@endforeach</tbody></table></div>
@endif

<h2 class="font-display font-semibold mt-10 mb-3">Payout requests</h2>
@if(!$v['payouts'])<p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-6 text-sm text-slate-600 dark:text-slate-400">No payout requested.</p>
@else
<div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5"><table class="w5-table"><thead><tr><th>#</th><th>Affiliate</th><th class="text-end">Amount</th><th>D17 number</th><th>Status</th><th>Settle</th></tr></thead><tbody>
@foreach($v['payouts'] as $p)
  <tr><td>{{ $p['id'] }}</td><td class="font-mono text-xs">{{ $p['code'] }}</td><td class="text-end tabular-nums">{{ number_format((float)$p['amount'], 2) }}</td><td class="font-mono text-xs">{{ $p['gsm'] }}</td><td><span class="tier-badge">{{ $p['status'] }}</span>@if($p['txn_ref'])<br><span class="font-mono text-xs">{{ $p['txn_ref'] }}</span>@endif @if($p['note'])<br><span class="text-xs">{{ $p['note'] }}</span>@endif</td>
  <td>@if($p['status'] === 'requested' && $can)<form method="post" action="{{ $b . 'payout' }}" class="flex flex-wrap gap-1.5 items-center">{!! csrf_field() !!}<input type="hidden" name="id" value="{{ $p['id'] }}"><input class="field !py-1 text-xs w-32" name="txn_ref" placeholder="D17 reference" aria-label="D17 reference"><input class="field !py-1 text-xs w-32" name="note" placeholder="Note (to reject)" aria-label="Note"><button class="btn-focus !py-1 !px-2 text-xs" name="decision" value="paid" type="submit">Mark paid</button><button class="btn-ghost !py-1 !px-2 text-xs" name="decision" value="reject" type="submit">Reject</button></form>@else<span class="text-xs text-slate-600 dark:text-slate-400">—</span>@endif</td></tr>
@endforeach</tbody></table></div>
@endif

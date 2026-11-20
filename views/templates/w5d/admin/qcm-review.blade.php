@php $st = $v['stats']; $can = $v['can']; $b = admin_url('qcm-review/'); @endphp
<p class="mb-5 flex flex-wrap gap-2 text-xs"><span class="tier-badge">Rounds: {{ $st['attempts'] }}</span><span class="tier-badge">Submitted: {{ $st['submitted'] }}</span><span class="tier-badge">Passed: {{ $st['passed'] }}</span><span class="tier-badge">Average: {{ round($st['avg']) }}%</span><span class="tier-badge">Fees to check: {{ $st['passes_pending'] }}</span><span class="tier-badge">Tasks to review: {{ $st['tasks_pending'] }}</span></p>

<h2 class="font-display font-semibold mb-3">Round fees (D17)</h2>
@if(!$v['passes'])<p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-6 text-sm text-slate-500">No fee submitted yet.</p>
@else
<div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5"><table class="w5-table"><thead><tr><th>Fee</th><th>Person</th><th>Round</th><th class="text-end">Amount</th><th>Sender</th><th>D17 txn</th><th>Status</th><th>Decision</th></tr></thead><tbody>
@foreach($v['passes'] as $p)
  <tr><td class="font-mono text-xs">{{ $p['ref'] }}<br><span class="text-slate-500">{{ $p['created_at'] }}</span></td><td>{{ $p['name'] }}<br><span class="text-xs text-slate-500">{{ $p['email'] }}</span></td><td>{{ $p['rule'] }}</td><td class="text-end tabular-nums">{{ number_format((float)$p['amount'], 2) }} {{ $p['currency'] }}</td><td class="font-mono text-xs">{{ $p['sender_gsm'] }}</td><td class="font-mono text-xs">{{ $p['txn_ref'] }}</td><td><span class="tier-badge">{{ $p['status'] }}</span>@if($p['note'])<br><span class="text-xs">{{ $p['note'] }}</span>@endif</td>
  <td>@if($p['status'] === 'pending' && $can)<form method="post" action="{{ $b . 'pass' }}" class="flex flex-wrap gap-1.5 items-center">{!! csrf_field() !!}<input type="hidden" name="id" value="{{ $p['id'] }}"><input class="field !py-1 text-xs w-36" name="note" placeholder="Note (needed to reject)" aria-label="Note"><button class="btn-focus !py-1 !px-2 text-xs" name="decision" value="verify" type="submit">Verify</button><button class="btn-ghost !py-1 !px-2 text-xs" name="decision" value="reject" type="submit">Reject</button></form>@else<span class="text-xs text-slate-500">{{ $p['status'] === 'pending' ? 'read-only' : '—' }}</span>@endif</td></tr>
@endforeach</tbody></table></div>
@endif

<h2 class="font-display font-semibold mt-10 mb-3">Tasks submitted for upgrade rounds</h2>
@if(!$v['tasks'])<p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-6 text-sm text-slate-500">No task yet.</p>
@else
<div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5"><table class="w5-table"><thead><tr><th>Task</th><th>Person</th><th>Evidence</th><th>Status</th><th>Decision</th></tr></thead><tbody>
@foreach($v['tasks'] as $t)
  <tr><td><strong>{{ $t['title'] }}</strong><br><span class="text-xs text-slate-500">{{ $t['service_slug'] ?: '—' }} · {{ $t['created_at'] }}</span></td><td>{{ $t['name'] }}<br><span class="text-xs text-slate-500">{{ $t['email'] }}</span></td><td class="max-w-sm text-sm whitespace-pre-line">{{ $t['evidence'] }}</td><td><span class="tier-badge">{{ $t['status'] }}</span>@if($t['note'])<br><span class="text-xs">{{ $t['note'] }}</span>@endif</td>
  <td>@if($t['status'] === 'submitted' && $can)<form method="post" action="{{ $b . 'task' }}" class="flex flex-wrap gap-1.5 items-center">{!! csrf_field() !!}<input type="hidden" name="id" value="{{ $t['id'] }}"><input class="field !py-1 text-xs w-36" name="note" placeholder="Note (needed to reject)" aria-label="Note"><button class="btn-focus !py-1 !px-2 text-xs" name="decision" value="approve" type="submit">Approve</button><button class="btn-ghost !py-1 !px-2 text-xs" name="decision" value="reject" type="submit">Reject</button></form>@else<span class="text-xs text-slate-500">—</span>@endif</td></tr>
@endforeach</tbody></table></div>
@endif

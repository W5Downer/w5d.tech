@php $can = $v['can']; $b = admin_url('certificates/'); @endphp
<p class="mb-4 text-sm text-slate-600 dark:text-slate-400">{{ $v['holders'] }} person(s) currently hold a valid certificate. Certificates are normally issued by passing a graded QCM round; the owner can also issue or revoke one by hand (always audited). The public check page shows a masked name only.</p>
@if($can)
<form method="post" action="{{ $b . 'issue' }}" class="mb-8 rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-5 grid gap-3 md:grid-cols-4 items-end" data-confirm="Issue this certificate?">
  {!! csrf_field() !!}
  <div class="md:col-span-2"><label class="block text-xs mb-1" for="ci-u">Person (verified accounts)</label><select class="field" id="ci-u" name="user_id">@foreach($v['users'] as $u)<option value="{{ $u['id'] }}">{{ $u['name'] }} · {{ $u['email'] }}</option>@endforeach</select></div>
  <div><label class="block text-xs mb-1" for="ci-l">Level</label><select class="field" id="ci-l" name="level">@foreach($v['levels'] as $i => $n)<option value="{{ $i }}">L{{ $i }} · {{ $n }}</option>@endforeach</select></div>
  <div class="md:col-span-3"><label class="block text-xs mb-1" for="ci-s">Service</label><select class="field" id="ci-s" name="service">@foreach($v['services'] as $k => $n)<option value="{{ $k }}">{{ $n }}</option>@endforeach</select></div>
  <button class="btn-focus" type="submit">{{ icon('award', 'w-4 h-4') }} Issue</button>
</form>
@endif
@if(!$v['rows'])<p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-6 text-sm text-slate-500">No certificate yet.</p>
@else
<div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5"><table class="w5-table"><thead><tr><th>Code</th><th>Person</th><th>Service</th><th>Level</th><th class="text-end">Score</th><th>Dates</th><th>State</th><th>Revoke</th></tr></thead><tbody>
@foreach($v['rows'] as $r)
  <tr><td class="font-mono text-xs"><a class="btn-link" href="{{ url('/certificate/' . $r['code'] . '/') }}">{{ $r['code'] }}</a><br><span class="text-slate-500">by {{ $r['issued_by'] }}</span></td><td>{{ $r['name'] }}<br><span class="text-xs text-slate-500">{{ $r['email'] }}</span></td><td class="text-xs">{{ $r['service_slug'] }}</td><td>L{{ $r['level'] }}</td><td class="text-end tabular-nums">{{ $r['score'] !== null ? round((float)$r['score']) . '%' : '—' }}</td><td class="text-xs tabular-nums">{{ substr((string)$r['issued_at'], 0, 10) }}<br>{{ $r['expires_at'] ? substr((string)$r['expires_at'], 0, 10) : '' }}</td><td><span class="tier-badge">{{ $r['state'] }}</span>@if($r['revoke_reason'])<br><span class="text-xs">{{ $r['revoke_reason'] }}</span>@endif</td>
  <td>@if($r['status'] === 'valid' && $can)<form method="post" action="{{ $b . 'revoke' }}" class="flex gap-1.5 items-center" data-confirm="Revoke {{ $r['code'] }}?">{!! csrf_field() !!}<input type="hidden" name="id" value="{{ $r['id'] }}"><input class="field !py-1 text-xs w-40" name="reason" placeholder="Reason (public)" aria-label="Reason" required><button class="btn-ghost !py-1 !px-2 text-xs" type="submit">Revoke</button></form>@endif</td></tr>
@endforeach</tbody></table></div>
@endif

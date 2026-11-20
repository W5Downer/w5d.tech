@extends('admin.layout')
@section('title', t($menu['label']))
@section('content')
<header class="mb-6"><p class="eyebrow">{{ __('admin.title') }}</p><h1 class="font-display text-3xl font-bold flex items-center gap-3 text-slate-900 dark:text-white">{{ icon($menu['icon'], 'w-7 h-7 text-brand-500') }} {{ t($menu['label']) }}</h1></header>

@if($v['kind'] === 'crud')
  @php $can = $v['can']; $slug = $v['slug']; $d = $v['def']; @endphp
  <div class="mb-4 flex flex-wrap items-center gap-3">
    @if(in_array('create', $can, true))<a class="btn-peak" href="{{ admin_url($slug . '/new/') }}">{{ icon('plus', 'w-4 h-4') }} {{ __('ux.crud_new') }}</a>@endif
    <p class="text-xs text-slate-500">
      {{ __('ux.crud_you_can') }}:
      @forelse($can as $a)<span class="tier-badge">{{ __('ux.crud_' . $a . '_l') }}</span> @empty<span class="tier-badge">{{ __('ux.crud_readonly') }}</span>@endforelse
      @if(!empty($v['scoped']))· {{ __('ux.crud_scope') }}@endif
    </p>
  </div>
  @if(!empty($v['error']))<p class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 p-3 text-sm">{{ $v['error'] }}</p>@endif
  @if(empty($v['rows']))
    <p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-8 text-center text-slate-500">{{ __('ux.crud_none') }}</p>
  @else
    @php $cols = array_keys($v['rows'][0]); $hasAct = count(array_intersect($can, ['update', 'delete'])) > 0; @endphp
    @if(count($v['rows']) > 12)<div class="mb-3"><label class="sr-only" for="adm-filter">{{ __('ux.crud_search') }}</label><input id="adm-filter" type="search" class="field max-w-sm" placeholder="{{ __('ux.crud_search') }}" data-filter="#adm-table"></div>@endif
    <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 overflow-hidden">
      <table class="w5-table w5-stack"><thead><tr>@foreach($cols as $c)<th scope="col">{{ $c }}</th>@endforeach @if($hasAct)<th scope="col">{{ __('ux.crud_actions') }}</th>@endif</tr></thead>
      <tbody id="adm-table">
        @foreach($v['rows'] as $row)
          <tr data-filter-item="{{ mb_strtolower(implode(' ', array_map('strval', $row))) }}">
            @foreach($row as $c => $cell)<td data-label="{{ $c }}" @if(is_numeric($cell)) class="tabular-nums" @endif>@if($c === 'status')<span class="tier-badge">{{ $cell }}</span>@else{{ is_float($cell) ? number_format($cell, 2) : $cell }}@endif</td>@endforeach
            @if($hasAct)
            <td data-label="{{ __('ux.crud_actions') }}"><div class="flex flex-wrap gap-1.5">
              @if(in_array('update', $can, true))<a class="btn-ghost !py-1 !px-2 text-xs" href="{{ admin_url($slug . '/' . $row['id'] . '/edit/') }}" aria-label="{{ __('ux.crud_edit') }} #{{ $row['id'] }}">{{ icon('pencil', 'w-3.5 h-3.5') }} {{ __('ux.crud_edit') }}</a>@endif
              @if(in_array('delete', $can, true))
                <form method="post" action="{{ admin_url($slug . '/delete') }}" data-confirm="{{ __('ux.crud_confirm') }}">{!! csrf_field() !!}<input type="hidden" name="id" value="{{ $row['id'] }}"><button class="btn-ghost !py-1 !px-2 text-xs text-red-600 dark:text-red-400" type="submit" aria-label="{{ __('ux.crud_delete') }} #{{ $row['id'] }}">{{ icon('trash-2', 'w-3.5 h-3.5') }} {{ __('ux.crud_delete') }}</button></form>
              @endif
            </div></td>
            @endif
          </tr>
        @endforeach
      </tbody></table>
    </div>
    <p class="text-xs text-slate-500 mt-2">{{ count($v['rows']) }} {{ __('ux.crud_rows') }}</p>
  @endif

@elseif($v['kind'] === 'table')
  @if(!empty($v['note']))<p class="mb-4 text-sm text-slate-500">{{ $v['note'] }}</p>@endif
  @if(!empty($v['error']))<p class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 p-3 text-sm">{{ $v['error'] }}</p>@endif
  @if(empty($v['rows']))
    <p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-8 text-center text-slate-500">{{ __('lbl.none') }}</p>
  @else
    @php $cols = array_keys($v['rows'][0]); $st = $v['status_table'] ?? null; @endphp
    @if(count($v['rows']) > 12)<div class="mb-3"><label class="sr-only" for="adm-filter">{{ __('lbl.search') }}</label><input id="adm-filter" type="search" class="field max-w-sm" placeholder="{{ __('lbl.search') }}" data-filter="#adm-table"></div>@endif
    <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5">
      <table class="w5-table"><thead><tr>@foreach($cols as $c)<th scope="col">{{ $c }}</th>@endforeach @if($st)<th scope="col">Set status</th>@endif</tr></thead>
      <tbody id="adm-table">
        @foreach($v['rows'] as $row)
          <tr data-filter-item="{{ mb_strtolower(implode(' ', array_map('strval', $row))) }}">
            @foreach($row as $c => $cell)<td @if(is_numeric($cell)) class="tabular-nums" @endif>@if($c === 'status')<span class="tier-badge">{{ $cell }}</span>@else{{ is_float($cell) ? number_format($cell, 2) : $cell }}@endif</td>@endforeach
            @if($st)
            <td><form method="post" action="{{ admin_url('status') }}" class="flex gap-1">{!! csrf_field() !!}<input type="hidden" name="table" value="{{ $st }}"><input type="hidden" name="id" value="{{ $row['id'] }}"><input type="hidden" name="back" value="{{ $back }}">
              <label class="sr-only" for="s{{ $row['id'] }}">Status</label><select id="s{{ $row['id'] }}" name="status" class="field !py-1 !text-xs">@foreach($v['statuses'] as $s)<option value="{{ $s }}" @if(($row['status'] ?? '') === $s) selected @endif>{{ $s }}</option>@endforeach</select><button class="btn-ghost !py-1 !px-2 text-xs" type="submit">{{ __('lbl.save') }}</button></form></td>
            @endif
          </tr>
        @endforeach
      </tbody></table>
    </div>
    <p class="text-xs text-slate-500 mt-2">{{ count($v['rows']) }} {{ __('admin.rows') }}</p>
  @endif

@elseif($v['kind'] === 'payments')
  <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">D17 (Tunisia only) · {{ $v['enabled'] ? 'enabled → ' . $v['gsm'] : 'DISABLED: set PAYMENT_D17_ENABLED=true and PAYMENT_D17_GSM in .env' }}. Check the transfer in your D17 app, then verify or reject. The buyer is e-mailed.</p>
  @if(!empty($v['error']))<p class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 p-3 text-sm">{{ $v['error'] }}</p>@endif
  @if(empty($v['rows']))<p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-8 text-center text-slate-500">{{ __('lbl.none') }}</p>
  @else
  <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5">
    <table class="w5-table"><thead><tr><th>Payment</th><th>Order</th><th>Buyer</th><th class="text-end">Amount</th><th>Sender GSM</th><th>D17 txn</th><th>Status</th><th>Action</th></tr></thead><tbody>
    @foreach($v['rows'] as $p)
      <tr>
        <td class="font-mono text-xs">{{ $p['ref'] }}<br><span class="text-slate-500">{{ $p['created_at'] }}</span></td>
        <td class="font-mono text-xs">{{ $p['order_ref'] }}</td>
        <td>{{ $p['full_name'] }}<br><span class="text-xs text-slate-500">{{ $p['email'] }}</span></td>
        <td class="text-end tabular-nums">{{ number_format((float)$p['amount'], 2) }} {{ $p['currency'] }}</td>
        <td dir="ltr">{{ $p['sender_gsm'] ?: '—' }}</td>
        <td class="font-mono text-xs">{{ $p['txn_ref'] ?: '—' }}</td>
        <td><span class="tier-badge">{{ $p['status'] }}</span>@if($p['note'])<br><span class="text-xs">{{ $p['note'] }}</span>@endif @if($p['verified_by'])<br><span class="text-xs text-slate-500">{{ $p['verified_by'] }}</span>@endif</td>
        <td>
          @if($p['status'] !== 'verified')
          <form method="post" action="{{ admin_url('payments/review') }}" class="flex flex-col gap-1 min-w-[10rem]">
            {!! csrf_field() !!}<input type="hidden" name="id" value="{{ $p['id'] }}">
            <input class="field !py-1 !text-xs" name="note" maxlength="300" placeholder="Note (optional, sent to buyer)" aria-label="Note">
            <div class="flex gap-1"><button class="btn-focus !py-1 !px-2 text-xs" name="do" value="verify" type="submit" @if(!$p['txn_ref']) disabled title="No transaction reference yet" @endif>Verify</button><button class="btn-ghost !py-1 !px-2 text-xs" name="do" value="reject" type="submit">Reject</button></div>
          </form>
          @else — @endif
        </td>
      </tr>
    @endforeach
    </tbody></table>
  </div>
  @endif

@elseif($v['kind'] === 'pricing')
  @php $k = $v['k']; $snap = $v['snap']; @endphp
  <div class="grid gap-6 lg:grid-cols-3">
    <form method="post" action="{{ admin_url('progressive-pricing/publish') }}" class="lg:col-span-2 rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-5 sm:p-8 space-y-4">
      {!! csrf_field() !!}
      <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('price.formula') }}. Publishing writes an audit row and goes live on the public site, the API and the checkout immediately.</p>
      <div class="grid gap-4 sm:grid-cols-2">
        <x-form-field name="alpha" type="number" label="α (progress weight)" :value="$k['alpha']" :required="true" hint="0 – 5" />
        <x-form-field name="beta" type="number" label="β (time weight)" :value="$k['beta']" :required="true" hint="0 – 5" />
        <x-form-field name="p_prog" type="number" label="k (progress exponent)" :value="$k['p_prog']" :required="true" hint="0.5 – 5" />
        <x-form-field name="p_time" type="number" label="m (time exponent)" :value="$k['p_time']" :required="true" hint="0.5 – 5" />
        <x-form-field name="cap" type="number" label="Cap (× base)" :value="$k['cap']" :required="true" hint="1 – 10" />
      </div>
      <x-form-field name="note" type="textarea" label="Reason (public audit trail)" :required="true" :rows="3" />
      <button class="btn-peak" type="submit">{{ __('admin.publish') }}</button>
    </form>
    <div class="space-y-6">
      <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-5"><p class="text-xs uppercase tracking-wider text-slate-500">Live now</p><p class="font-display text-4xl font-bold mt-2">{{ number_format($snap['day'], 2) }} <span class="text-base">{{ $snap['currency'] }}</span></p><p class="text-xs text-slate-500">week {{ number_format($snap['week'], 2) }} · p {{ number_format($snap['p'], 3) }} · t {{ number_format($snap['t'], 3) }}</p></div>
      <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-5 overflow-x-auto"><p class="text-xs uppercase tracking-wider text-slate-500 mb-2">Curve (p = t = x)</p><table class="w5-table"><thead><tr><th>x</th><th class="text-end">day</th><th class="text-end">week</th></tr></thead><tbody>@foreach($v['curve'] as $c)<tr><td>{{ number_format($c['x'] * 100) }}%</td><td class="text-end tabular-nums">{{ number_format($c['day'], 2) }}</td><td class="text-end tabular-nums">{{ number_format($c['week'], 2) }}</td></tr>@endforeach</tbody></table></div>
    </div>
  </div>
  <h2 class="font-display font-semibold mt-8 mb-3">Audit trail</h2>
  @if($v['hist'])<div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5"><table class="w5-table"><thead><tr><th>When</th><th>By</th><th>α</th><th>β</th><th>k</th><th>m</th><th>cap</th><th>Reason</th></tr></thead><tbody>@foreach($v['hist'] as $h)<tr><td class="font-mono text-xs">{{ $h['changed_at'] }}</td><td>{{ $h['changed_by'] }}</td><td>{{ $h['alpha'] }}</td><td>{{ $h['beta'] }}</td><td>{{ $h['p_prog'] }}</td><td>{{ $h['p_time'] }}</td><td>{{ $h['cap'] }}</td><td>{{ $h['note'] }}</td></tr>@endforeach</tbody></table></div>@else<p class="text-sm text-slate-500">No change published yet: defaults from config apply.</p>@endif

@elseif($v['kind'] === 'variables')
  @php
    $gl = ['money' => 'Money', 'dates' => 'Dates', 'experts' => 'Experts, spans and derivation', 'quality' => 'Quality and integrity', 'storage' => 'Storage budget (MB)', 'waves' => 'Wave cohorts', 'economics' => 'Unit economics (declared parameters)', 'gates' => 'Revenue gates and rules', 'cat' => 'Revenue catalogue prices (A1-J3, TND)', 'flags' => 'Behaviour switches', 'investor' => 'Investor limits', 'pricing' => 'Pricing function (published under Progressive Pricing)'];
  @endphp
  <p class="mb-4 max-w-3xl text-sm text-slate-600 dark:text-slate-400">Every number, date and switch the site prints comes from here. Change a value, give a reason, save: it goes live on every page, the API and the checkout, and is written to the change log below with your name and the date. <strong>Reset</strong> returns a value to the file default. Locked rows are records or are governed elsewhere.</p>
  @if(!empty($flash))<p class="mb-4 rounded-lg border p-3 text-sm {{ $flash['kind'] === 'ok' ? 'border-emerald-500/40 bg-emerald-500/10' : 'border-red-500/40 bg-red-500/10' }}" role="status">{{ $flash['msg'] }}</p>@endif
  <form method="post" action="{{ admin_url('variables/save') }}" class="space-y-6">
    {!! csrf_field() !!}
    <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-4 sm:p-5">
      <label for="v-note" class="block text-sm font-medium mb-1">Reason (required, public audit trail)</label>
      <input id="v-note" name="note" class="field" minlength="10" maxlength="500" placeholder="e.g. Wave 1 gate reviewed on the quarterly call" required>
      <button class="btn-peak mt-3" type="submit">Save changes</button>
    </div>
    @foreach($v['groups'] as $g => $rows)
    <details class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5" @if(in_array($g, ['money', 'dates', 'experts', 'flags'])) open @endif>
      <summary class="cursor-pointer select-none px-4 sm:px-5 py-3 font-display font-semibold flex items-center justify-between gap-3"><span>{{ $gl[$g] ?? ucfirst($g) }}</span><span class="text-xs font-normal text-slate-500">{{ count($rows) }}</span></summary>
      <div class="divide-y divide-slate-200 dark:divide-white/10 border-t border-slate-200 dark:border-white/10">
        @foreach($rows as $r)
          @php $key = str_replace('.', '__', $r['path']); @endphp
          <div class="grid gap-2 px-4 sm:px-5 py-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto] sm:items-center">
            <div class="min-w-0"><label for="v-{{ $key }}" class="block font-mono text-xs break-all text-slate-700 dark:text-slate-300">{{ $r['tok'] }}</label>
              <p class="text-xs text-slate-500">default {{ $r['default'] }}@if($r['overridden']) · <span class="text-amber-600 dark:text-amber-300">changed by {{ $r['by'] }}, {{ $r['at'] }}</span>@endif</p></div>
            @if($r['editable'])
              <input id="v-{{ $key }}" name="v[{{ $key }}]" value="{{ $r['value'] }}" class="field !py-1.5 text-sm" @if($r['type'] === 'date') type="date" @else type="text" inputmode="{{ $r['type'] === 'text' ? 'text' : 'decimal' }}" @endif>
              <label class="flex items-center gap-2 text-xs text-slate-500 whitespace-nowrap"><input type="checkbox" name="reset[{{ $key }}]" value="1" @if(!$r['overridden']) disabled @endif> Reset</label>
            @else
              <input value="{{ $r['value'] }}" class="field !py-1.5 text-sm opacity-60" disabled aria-label="{{ $r['path'] }} (locked)">
              <span class="text-xs text-slate-500 inline-flex items-center gap-1">{{ icon('lock', 'w-3.5 h-3.5') }} Locked</span>
            @endif
          </div>
        @endforeach
      </div>
    </details>
    @endforeach
  </form>

  <h2 class="font-display font-semibold mt-10 mb-3">Derived values (read-only, computed from the numbers above)</h2>
  <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5"><table class="w5-table"><tbody>@foreach($v['derived'] as $r)<tr><th scope="row" class="font-mono text-xs">{{ $r['token'] }}</th><td class="tabular-nums">{{ $r['value'] }}</td></tr>@endforeach</tbody></table></div>

  <h2 class="font-display font-semibold mt-10 mb-3">Change log</h2>
  @if($v['hist'])
  <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5"><table class="w5-table"><thead><tr><th>When</th><th>By</th><th>Variable</th><th>From</th><th>To</th><th>Reason</th></tr></thead><tbody>@foreach($v['hist'] as $h)<tr><td class="font-mono text-xs whitespace-nowrap">{{ $h['changed_at'] }}</td><td>{{ $h['changed_by'] }}</td><td class="font-mono text-xs">{{ $h['path'] }}</td><td>{{ $h['old_value'] }}</td><td>{{ $h['new_value'] }}</td><td>{{ $h['note'] }}</td></tr>@endforeach</tbody></table></div>
  @else<p class="text-sm text-slate-500">No change yet: every value is the file default.</p>@endif

@elseif($v['kind'] === 'qcm-bank')
  @include('admin.qcm-bank')

@elseif($v['kind'] === 'qcm-review')
  @include('admin.qcm-review')

@elseif($v['kind'] === 'affiliates')
  @include('admin.affiliates')

@elseif($v['kind'] === 'certificates')
  @include('admin.certificates')

@elseif($v['kind'] === 'api')
  @include('partials.api-guide', ['guide' => $v['guide'], 'tabs' => $v['tabs'], 'as' => $v['as'], 'tab_base' => $v['tab_base'], 'role' => $v['role']])

@elseif($v['kind'] === 'system')
  <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5"><table class="w5-table"><tbody>@foreach($v['rows'] as $r)<tr><th scope="row" class="w-56">{{ $r['check'] }}</th><td>{{ $r['value'] }}</td></tr>@endforeach</tbody></table></div>

@else
  <div class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-8 max-w-2xl">
    <p class="tier-badge">{{ __('admin.read_only') }}</p>
    <h2 class="font-display text-xl font-semibold mt-3">{{ t($menu['label']) }}</h2>
    <p class="text-slate-600 dark:text-slate-400 mt-2 text-sm">This menu is part of the 28-menu owner portal and is gated by permission <code>{{ $menu['perm'] }}</code>. Its data tables exist in the schema; the management screen ships with the next iteration. Nothing here changes public figures.</p>
    <a class="btn-focus mt-5 text-sm" href="{{ admin_url() }}">Command Center</a>
  </div>
@endif
@endsection

@extends('admin.layout')
@section('title', t($menu['label']))
@section('content')
<header class="mb-6"><p class="eyebrow">{{ __('admin.title') }}</p><h1 class="font-display text-3xl font-bold flex items-center gap-3 text-slate-900 dark:text-white">{{ icon($menu['icon'], 'w-7 h-7 text-brand-500') }} {{ t($menu['label']) }}</h1></header>

@if($v['kind'] === 'table')
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

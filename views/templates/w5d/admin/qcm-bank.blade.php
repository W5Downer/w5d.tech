@php
  $svcs = $v['services']; $slug = $v['slug']; $lv = $v['level']; $can = $v['can'];
  $base = admin_url('qcm-bank/');
  $q = fn(array $x = []) => $base . '?' . http_build_query(array_filter(['service' => $slug, 'level' => $lv ?: null, 'page' => null] + $x, fn($z) => $z !== null && $z !== ''));
  $ed = $v['edit']; $form = $v['new'] || $ed;
  $tx = fn($a, $k) => is_array($a) ? ($a[$k] ?? '') : '';
@endphp
<p class="mb-4 max-w-3xl text-sm text-slate-600 dark:text-slate-400">One JSON file per service in <code>data/qcm/</code>. Each service needs enough active questions at a level for a round to run (target {{ $v['target'] }} per service). Questions carry English, French and Arabic text; answers are never shown to people in graded rounds. Rounds already started keep their own copy.</p>

<form method="get" action="{{ $base }}" class="mb-4 flex flex-wrap items-end gap-3">
  <div class="grow min-w-[16rem]"><label for="bk-svc" class="block text-xs mb-1">Service</label><select id="bk-svc" name="service" class="field">@foreach($svcs as $k => $n)<option value="{{ $k }}" @if($k === $slug) selected @endif>{{ $n }}</option>@endforeach</select></div>
  <div><label for="bk-lv" class="block text-xs mb-1">Level</label><select id="bk-lv" name="level" class="field"><option value="0">All</option>@foreach([1,2,3,4] as $l)<option value="{{ $l }}" @if($lv === $l) selected @endif>L{{ $l }}</option>@endforeach</select></div>
  <button class="btn-focus" type="submit">Show</button>
  <a class="btn-ghost" href="{{ $base . 'export?service=' . rawurlencode($slug) }}">{{ icon('download', 'w-4 h-4') }} Export JSON</a>
  @if($can['create'])<a class="btn-peak" href="{{ $q(['new' => 1]) }}">{{ icon('plus', 'w-4 h-4') }} New question</a>@endif
</form>

<p class="mb-2 max-w-3xl text-xs text-slate-600 dark:text-slate-400">@if($v['generated'])Besides the stored questions below, each service level also draws from <strong>{{ number_format($v['generated']) }}</strong> generated questions ({{ number_format($v['generated'] * 4) }} per service, rebuilt on demand from the service sheet; they are not listed here). Change the number or switch them off in Variables → qcm (<code>generated_per_level</code>, <code>generated</code>). The number of questions per round is set in QCM rules.@else Generated questions are switched off: rounds draw only from the stored questions below.@endif</p>
<p class="mb-4 flex flex-wrap gap-2 text-xs" aria-label="Active questions per level">@foreach($v['per_level'] as $l => $n)<span class="tier-badge">L{{ $l }}: {{ $n }} stored @if($v['generated']) + {{ number_format($v['generated']) }} generated @elseif($n < 10) · too few for a 10-question round @endif</span>@endforeach</p>

@if($form && ($can['create'] || $can['update']))
<form method="post" action="{{ $base . 'save' }}" class="mb-8 rounded-2xl border border-violet-500/30 bg-white dark:bg-white/5 p-5 space-y-4" data-qcm-editor novalidate>
  {!! csrf_field() !!}<input type="hidden" name="service" value="{{ $slug }}"><input type="hidden" name="id" value="{{ $ed['id'] ?? '' }}">
  <h2 class="font-display text-lg font-semibold">{{ $ed ? 'Edit ' . $ed['id'] : 'New question' }}</h2>
  <div class="grid gap-3 sm:grid-cols-4">
    <div><label class="block text-xs mb-1" for="e-level">Level</label><select class="field" id="e-level" name="level" @if($ed) disabled @endif>@foreach([1,2,3,4] as $l)<option value="{{ $l }}" @if(($ed['level'] ?? $lv ?: 1) == $l) selected @endif>L{{ $l }}</option>@endforeach</select>@if($ed)<input type="hidden" name="level" value="{{ $ed['level'] }}">@endif</div>
    <div><label class="block text-xs mb-1" for="e-type">Type</label><select class="field" id="e-type" name="type"><option value="single" @if(($ed['type'] ?? 'single') === 'single') selected @endif>One right answer</option><option value="multiple" @if(($ed['type'] ?? '') === 'multiple') selected @endif>Several right answers</option></select></div>
    <div class="sm:col-span-2"><label class="block text-xs mb-1" for="e-tags">Tags (comma separated)</label><input class="field" id="e-tags" name="tags" value="{{ implode(', ', $ed['tags'] ?? []) }}"></div>
  </div>
  <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" @if(($ed['active'] ?? true)) checked @endif> Active (can be drawn in rounds)</label>
  @foreach(['en' => 'English', 'fr' => 'Français', 'ar' => 'العربية'] as $lc => $ln)
    <div><label class="block text-xs mb-1" for="e-q-{{ $lc }}">Question · {{ $ln }}</label><textarea class="field" id="e-q-{{ $lc }}" name="q_{{ $lc }}" rows="2" @if($lc === 'ar') dir="rtl" @endif>{{ $tx($ed['q'] ?? null, $lc) }}</textarea></div>
  @endforeach
  <fieldset class="space-y-3"><legend class="text-sm font-semibold">Choices (2 to 8: leave unused ones empty). Tick the right ones.</legend>
    @foreach(\W5D\Qcm::IDS as $ci)
      @php $c = null; foreach (($ed['choices'] ?? []) as $x) if ($x['id'] === $ci) $c = $x; @endphp
      <div class="rounded-xl border border-slate-200 dark:border-white/10 p-3" data-choice="{{ $ci }}">
        <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="answer[]" value="{{ $ci }}" @if($c && in_array($ci, $ed['answer'] ?? [], true)) checked @endif> Choice {{ strtoupper($ci) }} is right</label>
        <div class="mt-2 grid gap-2 md:grid-cols-3">@foreach(['en', 'fr', 'ar'] as $lc)<input class="field" name="c{{ $ci }}_{{ $lc }}" value="{{ $tx($c['t'] ?? null, $lc) }}" placeholder="{{ strtoupper($lc) }}" aria-label="Choice {{ strtoupper($ci) }} {{ strtoupper($lc) }}" @if($lc === 'ar') dir="rtl" @endif>@endforeach</div>
      </div>
    @endforeach
  </fieldset>
  @foreach(['en' => 'English', 'fr' => 'Français', 'ar' => 'العربية'] as $lc => $ln)
    <div><label class="block text-xs mb-1" for="e-x-{{ $lc }}">Explanation (shown after practice rounds) · {{ $ln }}</label><textarea class="field" id="e-x-{{ $lc }}" name="x_{{ $lc }}" rows="2" @if($lc === 'ar') dir="rtl" @endif>{{ $tx($ed['explain'] ?? null, $lc) }}</textarea></div>
  @endforeach
  <div class="flex gap-3"><button class="btn-peak" type="submit">Save question</button><a class="btn-ghost" href="{{ $q() }}">Cancel</a></div>
</form>
@endif

@if(empty($v['rows']))
  <p class="rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-8 text-center text-slate-500">No question for this selection.</p>
@else
  <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 overflow-hidden">
    <table class="w5-table w5-stack"><thead><tr><th>ID</th><th>Type</th><th>Question (EN)</th><th>Right</th><th>State</th><th>Actions</th></tr></thead><tbody>
    @foreach($v['rows'] as $r)
      <tr>
        <td data-label="ID" class="font-mono text-xs">{{ $r['id'] }}</td>
        <td data-label="Type">{{ $r['type'] === 'multiple' ? 'multi' : 'single' }}</td>
        <td data-label="Question">{{ mb_substr((string)($r['q']['en'] ?? ''), 0, 110) }}</td>
        <td data-label="Right" class="font-mono">{{ implode(',', $r['answer']) }}</td>
        <td data-label="State"><span class="tier-badge">{{ !empty($r['active']) ? 'active' : 'off' }}</span></td>
        <td data-label="Actions"><div class="flex flex-wrap gap-1.5">
          @if($can['update'])<a class="btn-ghost !py-1 !px-2 text-xs" href="{{ $q(['edit' => $r['id'], 'page' => $v['page']]) }}">{{ icon('pencil', 'w-3.5 h-3.5') }} Edit</a>
          <form method="post" action="{{ $base . 'toggle' }}">{!! csrf_field() !!}<input type="hidden" name="service" value="{{ $slug }}"><input type="hidden" name="id" value="{{ $r['id'] }}"><input type="hidden" name="level" value="{{ $lv }}"><input type="hidden" name="page" value="{{ $v['page'] }}"><button class="btn-ghost !py-1 !px-2 text-xs" type="submit">{{ !empty($r['active']) ? 'Turn off' : 'Turn on' }}</button></form>@endif
          @if($can['delete'])<form method="post" action="{{ $base . 'delete' }}" data-confirm="Delete {{ $r['id'] }}?">{!! csrf_field() !!}<input type="hidden" name="service" value="{{ $slug }}"><input type="hidden" name="id" value="{{ $r['id'] }}"><input type="hidden" name="level" value="{{ $lv }}"><button class="btn-ghost !py-1 !px-2 text-xs" type="submit">{{ icon('trash-2', 'w-3.5 h-3.5') }} Delete</button></form>@endif
        </div></td>
      </tr>
    @endforeach
    </tbody></table>
  </div>
  <p class="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-500">{{ $v['total'] }} question(s) · page {{ $v['page'] }} / {{ $v['pages'] }}
    @if($v['page'] > 1)<a class="btn-link" href="{{ $q(['page' => $v['page'] - 1]) }}">Previous</a>@endif @if($v['page'] < $v['pages'])<a class="btn-link" href="{{ $q(['page' => $v['page'] + 1]) }}">Next</a>@endif</p>
@endif

@if($can['create'])
<form method="post" action="{{ $base . 'import' }}" class="mt-10 rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-5 space-y-3" data-confirm="Import into this service?">
  {!! csrf_field() !!}<input type="hidden" name="service" value="{{ $slug }}">
  <h2 class="font-display text-lg font-semibold">Import JSON into {{ $svcs[$slug] ?? $slug }}</h2>
  <p class="text-sm text-slate-600 dark:text-slate-400">Paste a JSON array of questions (or an object with a <code>questions</code> array) following <code>docs/qcm-generation-prompt.md</code>. The import is all-or-nothing: one invalid question rejects the whole file and nothing is written. Ids must start with <code>{{ $slug }}.L</code>.</p>
  <label class="sr-only" for="imp-json">JSON</label><textarea id="imp-json" name="json" rows="8" class="field font-mono text-xs" dir="ltr" placeholder='[{"id":"{{ $slug }}.L1.101","level":1,"type":"single","active":true,"q":{"en":"…","fr":"…","ar":"…"},"choices":[…],"answer":["a"]}]'></textarea>
  <div class="flex flex-wrap items-center gap-4 text-sm"><label class="flex items-center gap-2"><input type="radio" name="mode" value="merge" checked> Merge (add new ids, update existing)</label>@if($can['delete'])<label class="flex items-center gap-2"><input type="radio" name="mode" value="replace"> Replace the whole bank</label>@endif<button class="btn-focus" type="submit">{{ icon('upload', 'w-4 h-4') }} Import</button></div>
</form>
@endif

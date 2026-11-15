@php $svc = $d['service'] ?? ''; @endphp
<x-section :d="$d">
  <div class="grid gap-8 lg:grid-cols-2 items-start">
    <ul class="space-y-3 text-sm">
      @foreach(($d['criteria'] ?? []) as $c)<li class="flex gap-3">{{ icon('check', 'w-5 h-5 text-emerald-500 shrink-0') }}<span>{{ rich($c) }}</span></li>@endforeach
    </ul>
    <form method="post" action="{{ url('/join/eligibility/') }}" class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white/60 dark:bg-white/5 p-5 space-y-3">
      {!! csrf_field() !!}<input type="hidden" name="service" value="{{ $svc }}">
      <x-form-field name="diploma" type="select" :label="__('lbl.diploma')" :options="['licence' => 'Licence / Bachelor', 'maitrise' => 'Maîtrise / Master', 'engineer' => 'Engineer', 'phd' => 'Doctorate', 'other' => 'Other']" />
      <x-form-field name="field" :label="__('lbl.field')" />
      <button class="btn-focus w-full" type="submit">{{ __('cta.eligibility') }}</button>
    </form>
  </div>
</x-section>

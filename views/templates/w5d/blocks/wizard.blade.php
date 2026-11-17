@php
  $mode = $d['mode'] ?? 'checkout';
  $snap = \W5D\Variables::get('price');
  $v = \W5D\Variables::get('investor');
  $macros = \W5D\Content::all('macros');
  $errors = $_SESSION['_errors'] ?? [];
  $tomorrow = (new DateTimeImmutable('tomorrow', new DateTimeZone('Africa/Tunis')))->format('Y-m-d');
  $macroSel = (string)($_GET['macro'] ?? '');
  $defDays = $mode === 'subscription' ? 30 : 1;
@endphp
<x-section :d="$d">
  <form method="post" action="{{ url($mode === 'subscription' ? '/subscription/' : '/checkout/') }}" class="max-w-4xl" data-wizard data-start="{{ !empty($errors) ? 0 : 0 }}" novalidate>
    {!! csrf_field() !!}
    <input type="hidden" name="mode" value="{{ $mode }}">
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
    <ol class="flex items-center gap-3 mb-8" aria-label="{{ __('lbl.step') }}">
      @foreach([1 => __('lbl.step_details'), 2 => __('lbl.step_parts'), 3 => __('lbl.step_review')] as $i => $lbl)
        <li class="flex items-center gap-2 text-sm"><span class="wz-dot" data-wz-dot>{{ $i }}</span><span class="hidden sm:inline">{{ $lbl }}</span></li>
      @endforeach
    </ol>

    <div class="wz-step space-y-4" data-step="1">
      <h3 class="font-display text-xl font-semibold">{{ __('lbl.step') }} 1 {{ __('lbl.of') }} 3</h3>
      <div class="grid gap-4 sm:grid-cols-2">
        <x-form-field name="full_name" :label="__('lbl.name')" :required="true" :errors="$errors" />
        <x-form-field name="email" type="email" :label="__('lbl.email')" :required="true" :errors="$errors" />
        <x-form-field name="phone" type="tel" :label="__('lbl.phone')" :errors="$errors" />
        <x-form-field name="kind" type="select" :label="__('lbl.kind')" :options="['individual' => __('lbl.individual'), 'company' => __('lbl.company')]" />
        <x-form-field name="organisation" :label="__('lbl.org')" :hint="__('lbl.optional')" class="sm:col-span-2" />
      </div>
      <div class="flex justify-end"><button type="button" class="btn-focus" data-wz-next>{{ __('cta.next') }} {{ icon('arrow-right', 'w-4 h-4 rtl:rotate-180') }}</button></div>
    </div>

    <div class="wz-step space-y-4" data-step="2" data-quote="{{ url('/api/quote') }}">
      <h3 class="font-display text-xl font-semibold">{{ __('lbl.step') }} 2 {{ __('lbl.of') }} 3</h3>
      <div class="grid gap-4 sm:grid-cols-2">
        <x-form-field name="cadence" type="select" :label="__('lbl.cadence')" :options="['daily' => __('lbl.daily'), 'weekly' => __('lbl.weekly')]" :value="$mode === 'subscription' ? 'daily' : 'daily'" />
        <x-form-field name="parts_per_day" type="number" :label="__('lbl.parts')" :required="true" :min="$v['min_parts_per_day']" :max="$v['max_parts_per_day']" :value="1" :errors="$errors" />
        <x-form-field name="days" type="number" :label="__('lbl.days')" :required="true" :min="1" :max="$v['max_days']" :value="$defDays" :errors="$errors" />
        <x-form-field name="start_on" type="date" :label="__('lbl.start')" :required="true" :value="$tomorrow" :min="date('Y-m-d')" :errors="$errors" />
        <x-form-field name="macro" type="select" :label="__('lbl.macro')" :options="['' => __('lbl.any')] + array_map(fn($m) => $m['name'], $macros)" :value="$macroSel" class="sm:col-span-2" />
      </div>
      <div class="rounded-2xl border border-brand-500/40 bg-brand-500/10 p-5 grid gap-4 sm:grid-cols-2" aria-live="polite">
        <div><p class="text-xs uppercase tracking-wider text-slate-500">{{ __('lbl.unit') }} ({{ __('price.today') }})</p><p class="font-display text-3xl font-bold" data-q-unit>{{ number_format($snap['day'], 2) }} {{ $snap['currency'] }}</p></div>
        <div><p class="text-xs uppercase tracking-wider text-slate-500">{{ __('lbl.total') }}</p><p class="font-display text-3xl font-bold" data-q-total>—</p></div>
        <p class="sm:col-span-2 text-xs text-slate-500 font-mono" data-q-note></p>
      </div>
      <div class="flex justify-between"><button type="button" class="btn-ghost" data-wz-prev>{{ icon('arrow-left', 'w-4 h-4 rtl:rotate-180') }} {{ __('cta.back') }}</button><button type="button" class="btn-focus" data-wz-next>{{ __('cta.next') }} {{ icon('arrow-right', 'w-4 h-4 rtl:rotate-180') }}</button></div>
    </div>

    <div class="wz-step space-y-4" data-step="3" data-quote="{{ url('/api/quote') }}">
      <h3 class="font-display text-xl font-semibold" data-review>{{ __('lbl.step') }} 3 {{ __('lbl.of') }} 3</h3>
      <dl class="grid gap-3 sm:grid-cols-2 text-sm rounded-2xl border border-slate-200 dark:border-white/10 p-5">
        @foreach(['full_name' => 'lbl.name', 'email' => 'lbl.email', 'parts_per_day' => 'lbl.parts', 'days' => 'lbl.days', 'start_on' => 'lbl.start', 'cadence' => 'lbl.cadence'] as $n => $k)
          <div><dt class="text-slate-500">{{ __($k) }}</dt><dd class="font-semibold" data-mirror="{{ $n }}">—</dd></div>
        @endforeach
        <div class="sm:col-span-2"><dt class="text-slate-500">{{ __('lbl.total') }}</dt><dd class="font-display text-2xl font-bold" data-q-total>—</dd></div>
      </dl>
      <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10 max-h-80">
        <table class="w5-table"><caption class="sr-only">{{ __('lbl.schedule') }}</caption><thead><tr><th>{{ __('lbl.date') }}</th><th class="text-end">{{ __('lbl.unit') }}</th><th class="text-end">{{ __('lbl.amount') }}</th></tr></thead><tbody data-q-rows><tr><td colspan="3">…</td></tr></tbody></table>
      </div>
      <aside class="rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm space-y-1" role="note">
        <p>{{ __('limits.jobs') }}</p><p>{{ __('limits.returns') }}</p><p class="font-semibold">{{ __('msg.demo') }}</p>
      </aside>
      @if(\W5D\Payments::enabled())
      <fieldset class="rounded-2xl border border-slate-200 dark:border-white/10 p-4 text-sm">
        <legend class="px-2 font-semibold">{{ __('acct.payments') }}</legend>
        <label class="flex items-start gap-3 py-1"><input type="radio" name="payment_method" value="" class="mt-1" @if(old('payment_method') === '') checked @endif @if(old('payment_method', 'x') === 'x') checked @endif><span>{{ __('msg.demo') }}</span></label>
        <label class="flex items-start gap-3 py-1"><input type="radio" name="payment_method" value="d17" class="mt-1" @if(old('payment_method') === 'd17') checked @endif><span><strong>D17</strong> · {{ __('acct.pay_title') }}<br><span class="text-xs text-slate-500">{{ __('acct.pay_steps') }}</span></span></label>
        @if(!empty($errors['payment_method']))<p class="text-xs mt-1 text-red-500" role="alert">{{ t($errors['payment_method']) }}</p>@endif
      </fieldset>
      @endif
      <x-form-field name="accept" type="checkbox" :label="'[' . __('lbl.accept') . '](' . __('limits.link') . ')'" :required="true" :errors="$errors" />
      <div class="flex justify-between items-center"><button type="button" class="btn-ghost" data-wz-prev>{{ icon('arrow-left', 'w-4 h-4 rtl:rotate-180') }} {{ __('cta.back') }}</button><button type="submit" class="btn-peak">{{ __('cta.confirm') }}</button></div>
    </div>
  </form>
  <noscript><p class="mt-4 text-sm text-slate-500">All three steps are shown above; submit the form at the end.</p></noscript>
</x-section>

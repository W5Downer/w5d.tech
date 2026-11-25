@php
  $mode = $d['mode'] ?? 'checkout';
  $snap = \W5D\Variables::get('price');
  $v = \W5D\Variables::get('investor');
  $macros = \W5D\Content::all('macros');
  $errors = $_SESSION['_errors'] ?? [];
  $tomorrow = (new DateTimeImmutable('tomorrow', new DateTimeZone('Africa/Tunis')))->format('Y-m-d');
  $macroSel = (string)($_GET['macro'] ?? '');
  $defDays = $mode === 'subscription' ? 30 : 1;
  $pre = \W5D\Gate::prefill();
  $lock = \W5D\Gate::lockEmail();
  $pms = \W5D\Gateways::enabledIds();
  $startStep = 0;
  if (!empty($errors)) { $startStep = (isset($errors['payment_method']) || isset($errors['accept'])) ? 2 : ((isset($errors['parts_per_day']) || isset($errors['days']) || isset($errors['start_on']) || isset($errors['macro'])) ? 1 : 0); }
  $curCode = (string)$snap['currency'];
@endphp
<x-section :d="$d">
  <form method="post" action="{{ url($mode === 'subscription' ? '/subscription/' : '/checkout/') }}" data-wizard data-start="{{ $startStep }}" data-draft="{{ $mode }}" novalidate>
    {!! csrf_field() !!}
    <input type="hidden" name="mode" value="{{ $mode }}">
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
    {!! \W5D\Integrations::field() !!}
    <div class="wz2">
      <div>
        <ol class="wz-bar" aria-label="{{ __('lbl.step') }}">
          @foreach([1 => __('lbl.step_details'), 2 => __('lbl.step_parts'), 3 => tr3('Pay', 'Payer', 'الدفع')] as $i => $lbl)
            <li><span class="wz-dot" data-wz-dot>{{ $i }}</span><span class="hidden sm:inline">{{ $lbl }}</span></li>
          @endforeach
        </ol>

        {{-- Step 1: who you are --}}
        <div class="wz-step wz-card space-y-4" data-step="1">
          <h3 class="font-display text-xl font-semibold">{{ tr3('Who is this for?', 'Pour qui ?', 'لمن هذا الطلب؟') }}</h3>
          <div class="grid gap-4 sm:grid-cols-2">
            <x-form-field name="full_name" :label="__('lbl.name')" :required="true" :errors="$errors" :value="$pre['full_name'] ?? ''" class="sm:col-span-2" />
            <x-form-field name="email" type="email" :label="__('lbl.email')" :required="true" :errors="$errors" :value="$pre['email'] ?? ''" :readonly="$lock" class="sm:col-span-2" />
            <x-form-field name="phone" type="tel" :label="__('lbl.phone')" :errors="$errors" :value="$pre['phone'] ?? ''" :hint="tr3('Needed for local wallet payments (D17, Flouci, Mobiflouss…).', 'Nécessaire pour les paiements par portefeuille local (D17, Flouci, Mobiflouss…).', 'مطلوب للدفع عبر المحافظ المحلية (D17 وFlouci وMobiflouss…).')" class="sm:col-span-2" />
            <fieldset class="sm:col-span-2"><legend class="block text-sm font-medium mb-1">{{ __('lbl.kind') }}</legend>
              <div class="seg">
                <label><input type="radio" name="kind" value="individual" checked><span>{{ __('lbl.individual') }}</span></label>
                <label><input type="radio" name="kind" value="company"><span>{{ __('lbl.company') }}</span></label>
              </div>
            </fieldset>
            <x-form-field name="organisation" :label="__('lbl.org')" :hint="__('lbl.optional')" class="sm:col-span-2" />
          </div>
          <div class="wz-foot"><span></span><button type="button" class="btn-focus" data-wz-next>{{ __('cta.next') }} {{ icon('arrow-right', 'w-4 h-4 rtl:rotate-180') }}</button></div>
        </div>

        {{-- Step 2: how much --}}
        <div class="wz-step wz-card space-y-5" data-step="2" data-quote="{{ url('/api/quote') }}">
          <h3 class="font-display text-xl font-semibold">{{ tr3('How many parts, for how long?', 'Combien de parts, pour combien de temps ?', 'كم حصة ولأي مدة؟') }}</h3>
          <fieldset><legend class="block text-sm font-medium mb-1">{{ __('lbl.cadence') }}</legend>
            <div class="seg">
              <label><input type="radio" name="cadence" value="daily" checked><span>{{ __('lbl.daily') }}</span></label>
              <label><input type="radio" name="cadence" value="weekly"><span>{{ __('lbl.weekly') }}</span></label>
            </div>
          </fieldset>
          <div>
            <x-form-field name="parts_per_day" type="number" :label="__('lbl.parts')" :required="true" :min="$v['min_parts_per_day']" :max="$v['max_parts_per_day']" :value="1" :errors="$errors" />
            <div class="chips" aria-label="{{ __('lbl.parts') }}">@foreach([1, 2, 5, 10, 25] as $n)<button type="button" class="chip" data-set="parts_per_day={{ $n }}">{{ $n }}</button>@endforeach</div>
          </div>
          <div>
            <x-form-field name="days" type="number" :label="__('lbl.days')" :required="true" :min="1" :max="$v['max_days']" :value="$defDays" :errors="$errors" />
            <div class="chips" aria-label="{{ __('lbl.days') }}">@foreach([1 => '1', 7 => '7', 30 => '30', 90 => '90', 365 => '365'] as $n => $lb)<button type="button" class="chip" data-set="days={{ $n }}">{{ $lb }}</button>@endforeach</div>
          </div>
          <x-form-field name="start_on" type="date" :label="__('lbl.start')" :required="true" :value="$tomorrow" :min="date('Y-m-d')" :errors="$errors" :chips="true" />
          <x-form-field name="macro" type="select" :label="__('lbl.macro')" :options="['' => __('lbl.any')] + array_map(fn($m) => $m['name'], $macros)" :value="$macroSel" />
          <div class="rounded-2xl border border-brand-500/40 bg-brand-500/10 p-4 grid gap-3 sm:grid-cols-2" aria-live="polite">
            <div><p class="text-xs uppercase tracking-wider text-slate-600 dark:text-slate-300">{{ __('lbl.unit') }} ({{ __('price.today') }})</p><p class="font-display text-2xl font-bold" data-q-unit>{{ number_format($snap['day'], 2) }} {{ $curCode }}</p></div>
            <div><p class="text-xs uppercase tracking-wider text-slate-600 dark:text-slate-300">{{ __('lbl.total') }}</p><p class="font-display text-2xl font-bold" data-q-total>—</p></div>
            <p class="sm:col-span-2 text-xs text-slate-600 dark:text-slate-300 font-mono" data-q-note></p>
          </div>
          <div class="wz-foot"><button type="button" class="btn-ghost" data-wz-prev>{{ icon('arrow-left', 'w-4 h-4 rtl:rotate-180') }} {{ __('cta.back') }}</button><button type="button" class="btn-focus" data-wz-next>{{ __('cta.next') }} {{ icon('arrow-right', 'w-4 h-4 rtl:rotate-180') }}</button></div>
        </div>

        {{-- Step 3: pay + confirm --}}
        <div class="wz-step wz-card space-y-5" data-step="3" data-quote="{{ url('/api/quote') }}">
          <h3 class="font-display text-xl font-semibold" data-review>{{ tr3('Choose how to pay', 'Choisissez comment payer', 'اختر طريقة الدفع') }}</h3>
          @if($pms)
          <fieldset data-paybox class="space-y-2">
            <legend class="sr-only">{{ __('acct.payments') }}</legend>
            @foreach($pms as $pm)
              @php $pd = \W5D\Gateways::def($pm); $fe = \W5D\Gateways::fee($pm, 100.0); $online = \W5D\Gateways::online($pm); $first = $loop->first; @endphp
              <label class="pm-card" data-fee-pct="{{ $fe['pct'] }}" data-fee-fixed="{{ $fe['fixed'] }}" data-fee-payer="{{ $fe['payer'] }}">
                <input type="radio" name="payment_method" value="{{ $pm }}" @if(old('payment_method', $first ? $pm : '') === $pm) checked @endif>
                <span class="min-w-0">
                  <strong class="flex items-center gap-2">{{ icon($pd['icon'] ?? 'wallet', 'w-4 h-4 text-brand-500') }} {{ \W5D\Gateways::short($pm) }}</strong>
                  <span class="block text-xs text-slate-600 dark:text-slate-300">{{ $online ? __('acct.pay_online') : __('acct.pay_manual') }}@if(!empty($pd['tn_only'])) · {{ __('acct.pay_tn_only') }}@endif — {{ $online ? __('acct.pay_online_steps') : __('acct.pay_steps_generic') }}</span>
                  <span class="pm-fee block" data-pm-fee data-t-buyer="{{ tr3('+ %s fee', '+ %s de frais', '+ %s رسوم') }}" data-t-none="{{ tr3('No extra fee for you', 'Aucun frais en plus pour vous', 'بلا رسوم إضافية عليك') }}">{{ tr3('No extra fee for you', 'Aucun frais en plus pour vous', 'بلا رسوم إضافية عليك') }}</span>
                </span>
              </label>
            @endforeach
            <label class="pm-card"><input type="radio" name="payment_method" value="" @if(old('payment_method', 'x') === '') checked @endif><span><strong>{{ tr3('Decide later', 'Décider plus tard', 'القرار لاحقا') }}</strong><span class="block text-xs text-slate-600 dark:text-slate-300">{{ tr3('Record the pledge now; nothing is charged until you pick a method.', 'Enregistrez l’engagement maintenant ; rien n’est débité avant de choisir un moyen.', 'سجّل الالتزام الآن؛ لا يُخصم شيء قبل اختيار وسيلة.') }}</span></span></label>
            @if(!empty($errors['payment_method']))<p class="text-xs mt-1 text-red-600" role="alert">{{ t($errors['payment_method']) }}</p>@endif
          </fieldset>
          @else
            <p class="rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm" role="note">{{ __('msg.pay_closed') }}</p>
            <input type="hidden" name="payment_method" value="">
          @endif
          <dl class="grid gap-3 sm:grid-cols-2 text-sm rounded-2xl border border-slate-200 dark:border-white/10 p-4">
            @foreach(['full_name' => 'lbl.name', 'email' => 'lbl.email', 'parts_per_day' => 'lbl.parts', 'days' => 'lbl.days', 'start_on' => 'lbl.start', 'cadence' => 'lbl.cadence'] as $n => $k)
              <div><dt class="text-slate-600 dark:text-slate-300">{{ __($k) }}</dt><dd class="font-semibold" data-mirror="{{ $n }}">—</dd></div>
            @endforeach
            @if($pms)<div class="sm:col-span-2"><dt class="text-slate-600 dark:text-slate-300">{{ tr3('Payment method', 'Moyen de paiement', 'وسيلة الدفع') }}</dt><dd class="font-semibold" data-mirror="payment_method">—</dd></div>@endif
          </dl>
          <details class="rounded-2xl border border-slate-200 dark:border-white/10"><summary class="cursor-pointer px-4 py-3 text-sm font-semibold">{{ __('lbl.schedule') }}</summary>
            <div class="overflow-x-auto max-h-72"><table class="w5-table"><caption class="sr-only">{{ __('lbl.schedule') }}</caption><thead><tr><th>{{ __('lbl.date') }}</th><th class="text-end">{{ __('lbl.unit') }}</th><th class="text-end">{{ __('lbl.amount') }}</th></tr></thead><tbody data-q-rows><tr><td colspan="3">…</td></tr></tbody></table></div>
          </details>
          <aside class="rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm space-y-1" role="note"><p>{{ __('limits.jobs') }}</p><p>{{ __('limits.returns') }}</p></aside>
          <x-form-field name="accept" type="checkbox" :label="'[' . __('lbl.accept') . '](' . __('limits.link') . ')'" :required="true" :errors="$errors" />
          <div class="wz-foot"><button type="button" class="btn-ghost" data-wz-prev>{{ icon('arrow-left', 'w-4 h-4 rtl:rotate-180') }} {{ __('cta.back') }}</button><button type="submit" class="btn-peak">{{ __('cta.confirm') }}</button></div>
        </div>
      </div>

      {{-- Live summary: sticky on desktop, a bar on phones --}}
      <aside class="wz-sum hidden lg:block wz-card" aria-label="{{ __('lbl.total') }}" data-quote="{{ url('/api/quote') }}">
        <p class="eyebrow">{{ __('lbl.total') }}</p>
        <p class="font-display text-3xl font-bold" data-q-total>—</p>
        <dl class="mt-3 text-sm space-y-1">
          <div class="flex justify-between gap-3"><dt class="text-slate-600 dark:text-slate-300">{{ __('lbl.unit') }}</dt><dd data-q-unit>{{ number_format($snap['day'], 2) }} {{ $curCode }}</dd></div>
          <div class="flex justify-between gap-3"><dt class="text-slate-600 dark:text-slate-300">{{ tr3('Transaction fee', 'Frais de transaction', 'رسوم العملية') }}</dt><dd data-pay-fee>—</dd></div>
          <div class="flex justify-between gap-3 font-semibold"><dt>{{ tr3('To pay', 'À payer', 'المبلغ المستحق') }}</dt><dd data-pay-due>—</dd></div>
        </dl>
        <p class="mt-3 text-xs text-slate-600 dark:text-slate-300">{{ tr3('The amount and any fee are final before you confirm.', 'Le montant et les frais éventuels sont définitifs avant de confirmer.', 'المبلغ وأي رسوم نهائيان قبل التأكيد.') }}</p>
      </aside>
    </div>
    <div class="wz-sumbar lg:hidden" data-quote="{{ url('/api/quote') }}"><span class="text-xs">{{ tr3('To pay', 'À payer', 'المبلغ المستحق') }}</span><strong class="font-display text-lg" data-pay-due data-q-total>—</strong></div>
  </form>
  <noscript><p class="mt-4 text-sm text-slate-600">{{ tr3('All three steps are shown above; submit the form at the end.', 'Les trois étapes sont affichées ci-dessus ; envoyez le formulaire à la fin.', 'الخطوات الثلاث معروضة أعلاه؛ أرسل النموذج في النهاية.') }}</p></noscript>
</x-section>

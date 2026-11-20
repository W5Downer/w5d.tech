@php $cur = (string)\W5D\Variables::get('money.currency', 'TND'); $v = fn($k) => \W5D\Variables::get('affiliate.' . $k); @endphp
<p class="mt-3 text-slate-600 dark:text-slate-300 max-w-3xl">{{ tr3('Share your link. When someone you introduced confirms an account, pays a verified fee, or earns a first certificate, a small commission is recorded. The owner approves each line by hand and pays by D17 once the balance passes the minimum.', 'Partagez votre lien. Quand une personne que vous avez présentée confirme un compte, paie des frais vérifiés ou obtient un premier certificat, une petite commission est enregistrée. Le propriétaire approuve chaque ligne à la main et paie par D17 quand le solde dépasse le minimum.', 'شارك رابطك. عندما يؤكد شخص قدّمته حسابه أو يدفع رسوما تم التحقق منها أو ينال أول شهادة تُسجَّل عمولة صغيرة. يوافق المالك على كل سطر يدويا ويدفع عبر D17 عندما يتجاوز الرصيد الحد الأدنى.') }}</p>
@if(!$a_on)
  <p class="mt-6 rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-6">{{ tr3('The affiliate programme is paused for now.', 'Le programme d’affiliation est suspendu pour le moment.', 'برنامج الإحالة متوقف حاليا.') }}</p>
@endif
@if(!$a)
  @if($a_on)
  <form method="post" action="{{ url('/account/affiliate/apply/') }}" class="mt-6 max-w-xl space-y-4" novalidate>
    {!! csrf_field() !!}
    <x-form-field name="pitch" type="textarea" :label="tr3('How will you share W5D? (optional)', 'Comment partagerez-vous W5D ? (facultatif)', 'كيف ستشارك W5D؟ (اختياري)')" :errors="$errors" :rows="3" />
    <x-form-field name="payout_gsm" type="tel" :label="tr3('D17 number for payouts (optional now)', 'Numéro D17 pour les paiements (facultatif pour l’instant)', 'رقم D17 للصرف (اختياري الآن)')" :hint="'+216 · 8 ' . tr3('digits', 'chiffres', 'أرقام')" :errors="$errors" />
    <x-form-field name="accept" type="checkbox" :label="tr3('I accept the programme rules: no spam, no promised income, honest descriptions only.', 'J’accepte les règles du programme : pas de spam, pas de revenu promis, descriptions honnêtes uniquement.', 'أقبل قواعد البرنامج: لا رسائل مزعجة ولا دخل موعود ووصف صادق فقط.')" :required="true" :errors="$errors" />
    <button class="btn-peak" type="submit">{{ tr3('Join the programme', 'Rejoindre le programme', 'انضم إلى البرنامج') }}</button>
  </form>
  <p class="mt-3 text-sm"><a class="btn-link" href="{{ url('/affiliate/') }}">{{ tr3('Read the programme terms', 'Lire les conditions du programme', 'اقرأ شروط البرنامج') }}</a></p>
  @endif
@else
  <p class="mt-4"><span class="rounded-full px-2 py-0.5 text-xs {{ $stl($a['status'] === 'active' ? 'verified' : ($a['status'] === 'suspended' ? 'rejected' : 'pending')) }}">{{ $a['status'] === 'active' ? tr3('active', 'actif', 'نشط') : ($a['status'] === 'suspended' ? tr3('suspended', 'suspendu', 'موقوف') : tr3('waiting for the owner', 'en attente du propriétaire', 'بانتظار المالك')) }}</span></p>
  @if($a['status'] === 'active' && $a_link)
    <div class="mt-4 rounded-2xl border border-violet-500/30 bg-violet-500/10 p-5 max-w-2xl">
      <p class="eyebrow">{{ tr3('Your link', 'Votre lien', 'رابطك') }}</p>
      <p class="mt-1 font-mono text-sm break-all" dir="ltr" id="aff-link">{{ $a_link }}</p>
      <p class="mt-3 flex flex-wrap gap-3"><button type="button" class="btn-focus" data-copy-text="{{ $a_link }}">{{ icon('copy', 'w-4 h-4') }} {{ __('cta.copy') }}</button><span class="text-xs text-slate-500 self-center">{{ tr3('Your code', 'Votre code', 'رمزك') }}: <strong class="font-mono">{{ $a['code'] }}</strong> · {{ tr3('attribution lasts', 'l’attribution dure', 'مدة الإسناد') }} {{ $v('cookie_days') }} {{ tr3('days', 'jours', 'يوما') }}</span></p>
    </div>
  @endif
  @if($a_stats)
    <div class="mt-6 grid gap-4 grid-cols-2 lg:grid-cols-4">
      @foreach([[tr3('Clicks', 'Clics', 'النقرات'), $a_stats['clicks']], [tr3('Confirmed sign-ups', 'Inscriptions confirmées', 'تسجيلات مؤكدة'), $a_stats['signups']], [tr3('Pending', 'En attente', 'قيد الانتظار'), number_format($a_stats['pending'], 2) . ' ' . $cur], [tr3('Available to withdraw', 'Disponible au retrait', 'متاح للسحب'), number_format($a_stats['available'], 2) . ' ' . $cur], [tr3('Approved, on hold', 'Approuvé, en attente', 'مقبول قيد الانتظار'), number_format($a_stats['approved'], 2) . ' ' . $cur], [tr3('In a payout request', 'Dans une demande de paiement', 'ضمن طلب صرف'), number_format($a_stats['locked'], 2) . ' ' . $cur], [tr3('Paid', 'Payé', 'مدفوع'), number_format($a_stats['paid'], 2) . ' ' . $cur]] as [$l, $n])
        <div class="rounded-2xl border border-slate-200 dark:border-white/10 p-4"><p class="text-xs text-slate-500">{{ $l }}</p><p class="mt-1 text-xl font-display font-bold break-words">{{ $n }}</p></div>
      @endforeach
    </div>
    <p class="mt-3 text-xs text-slate-500">{{ tr3('Approved commissions wait', 'Les commissions approuvées attendent', 'تنتظر العمولات المقبولة') }} {{ $v('hold_days') }} {{ tr3('days before they can be withdrawn. Minimum payout:', 'jours avant de pouvoir être retirées. Paiement minimum :', 'يوما قبل سحبها. الحد الأدنى للصرف:') }} {{ number_format((float)$v('min_payout'), 2) }} {{ $cur }}.</p>
  @endif

  <h2 class="mt-8 font-display text-lg font-semibold">{{ tr3('Payout details', 'Détails de paiement', 'بيانات الصرف') }}</h2>
  <form method="post" action="{{ url('/account/affiliate/apply/') }}" class="mt-3 max-w-xl space-y-4" novalidate>
    {!! csrf_field() !!}
    <x-form-field name="pitch" type="textarea" :label="tr3('How you share W5D', 'Comment vous partagez W5D', 'كيف تشارك W5D')" :value="$a['pitch'] ?? ''" :errors="$errors" :rows="3" />
    <x-form-field name="payout_gsm" type="tel" :label="tr3('D17 number for payouts', 'Numéro D17 pour les paiements', 'رقم D17 للصرف')" :value="$a['payout_gsm'] ?? ''" :errors="$errors" />
    <x-form-field name="accept" type="checkbox" :label="tr3('I accept the programme rules.', 'J’accepte les règles du programme.', 'أقبل قواعد البرنامج.')" :required="true" :errors="$errors" />
    <button class="btn-ghost" type="submit">{{ __('acct.save') }}</button>
  </form>
  @if($a['status'] === 'active')
    <form method="post" action="{{ url('/account/affiliate/payout/') }}" class="mt-4">{!! csrf_field() !!}<button class="btn-focus" type="submit" @if(!$a_stats || $a_stats['available'] < (float)$v('min_payout')) disabled @endif>{{ icon('download', 'w-4 h-4') }} {{ tr3('Request a payout', 'Demander un paiement', 'اطلب صرفا') }}</button></form>
  @endif

  <h2 class="mt-8 font-display text-lg font-semibold">{{ tr3('Recent commissions', 'Commissions récentes', 'العمولات الأخيرة') }}</h2>
  @if(!$a_refs)<p class="mt-2 text-sm text-slate-500">{{ tr3('Nothing yet.', 'Rien pour le moment.', 'لا شيء بعد.') }}</p>
  @else<div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10"><table class="w-full text-sm"><tbody>@foreach($a_refs as $x)<tr class="border-t first:border-t-0 border-slate-200 dark:border-white/10"><td class="p-3 tabular-nums">{{ substr((string)$x['created_at'], 0, 10) }}</td><td class="p-3">{{ $x['kind'] === 'signup' ? tr3('Sign-up', 'Inscription', 'تسجيل') : ($x['kind'] === 'payment' ? tr3('Verified payment', 'Paiement vérifié', 'دفعة موثقة') : tr3('First certificate', 'Premier certificat', 'أول شهادة')) }}</td><td class="p-3 tabular-nums">{{ number_format((float)$x['commission'], 2) }} {{ $cur }}</td><td class="p-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $stl($x['status'] === 'paid' ? 'verified' : ($x['status'] === 'rejected' ? 'rejected' : 'pending')) }}">{{ $x['status'] }}</span></td></tr>@endforeach</tbody></table></div>@endif
  @if($a_pays)
    <h2 class="mt-8 font-display text-lg font-semibold">{{ tr3('Payout requests', 'Demandes de paiement', 'طلبات الصرف') }}</h2>
    <ul class="mt-3 space-y-2 max-w-xl">@foreach($a_pays as $x)<li class="rounded-xl border border-slate-200 dark:border-white/10 p-3 text-sm flex justify-between gap-3"><span>{{ number_format((float)$x['amount'], 2) }} {{ $cur }} <span class="text-xs text-slate-500">· {{ substr((string)$x['created_at'], 0, 10) }}@if($x['txn_ref']) · {{ $x['txn_ref'] }}@endif</span></span><span class="rounded-full px-2 py-0.5 text-xs h-fit {{ $stl($x['status'] === 'paid' ? 'verified' : ($x['status'] === 'rejected' ? 'rejected' : 'pending')) }}">{{ $x['status'] }}</span></li>@endforeach</ul>
  @endif
@endif
<p class="mt-8 text-xs text-slate-500">{{ __('limits.returns') }} {{ tr3('Affiliate commissions are not a salary and are never guaranteed.', 'Les commissions d’affiliation ne sont pas un salaire et ne sont jamais garanties.', 'عمولات الإحالة ليست راتبا ولا تُضمن أبدا.') }}</p>

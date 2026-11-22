@extends('layouts.app')
@section('title', $title . ' · W5D')
@section('description', tr3('Restricted sponsorship of the registry activation: one purpose, refundable if activation fails, no return.', 'Parrainage affecté de l’activation du registre : un seul objet, remboursable si l’activation échoue, aucun rendement.', 'رعاية مقيّدة لتفعيل السجل: غرض واحد، قابلة للاسترداد إن فشل التفعيل، دون عائد.'))
@section('robots', 'noindex,follow')
@section('content')
<section class="py-14 sm:py-20">
  <div class="mx-auto max-w-3xl px-4 sm:px-6" data-rv="up">
    <p class="eyebrow">{{ tr3('Sponsorship', 'Parrainage', 'الرعاية') }}</p>
    <h1 class="font-display text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white">{{ $title }}</h1>
    <p class="mt-3 text-slate-600 dark:text-slate-300">{{ tr3('A sponsorship pays for one thing only: activating the company registry file. The money is held apart as restricted, shown on the status page, and refunded in full if the activation does not happen. It is not a loan, an investment or a purchase, and it returns nothing.', 'Un parrainage ne finance qu’une chose : l’activation du dossier d’immatriculation. L’argent est isolé comme fonds affecté, montré sur la page de statut, et intégralement remboursé si l’activation n’a pas lieu. Ce n’est ni un prêt, ni un investissement, ni un achat, et cela ne rapporte rien.', 'تموّل الرعاية شيئا واحدا فقط: تفعيل ملف تسجيل الشركة. يُحفظ المال منفصلا كأموال مقيّدة، ويظهر في صفحة الحالة، ويُردّ كاملا إذا لم يتم التفعيل. وهي ليست قرضا ولا استثمارا ولا شراء، ولا تعود بشيء.') }}</p>

    <div class="mt-6 rounded-2xl border p-5 {{ $ch['available'] ? 'border-emerald-500/40 bg-emerald-500/10' : 'border-amber-500/40 bg-amber-500/10' }}" role="status" data-sponsor-state="{{ $ch['available'] ? 'open' : 'closed' }}">
      <p class="font-semibold">{{ $ch['available'] ? tr3('Sponsorship is open', 'Le parrainage est ouvert', 'الرعاية مفتوحة') : tr3('Sponsorship is closed right now', 'Le parrainage est fermé pour le moment', 'الرعاية مغلقة حاليا') }}</p>
      <p class="text-sm mt-1">{{ \W5D\Funding::reasonLocal($ch) }}</p>
      <p class="text-sm mt-2">{{ tr3('Minimum:', 'Minimum :', 'الحد الأدنى:') }} <strong>{{ \W5D\Funding::fmt(\W5D\Funding::n('F2_SPONSORSHIP_MIN_TND')) }} {{ \W5D\Variables::get('money.currency') }}</strong></p>
    </div>

    @if($ch['available'])
    <form method="post" action="{{ url('/sponsor/') }}" class="mt-8 rounded-2xl border border-slate-200 dark:border-white/10 bg-white/60 dark:bg-white/5 p-5 sm:p-8" novalidate>
      {!! csrf_field() !!}
      <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
      {!! \W5D\Integrations::field() !!}
      @if(!empty($errors['_form']))<p class="mb-4 rounded-lg bg-red-500/10 border border-red-500/40 p-3 text-sm" role="alert">{{ t($errors['_form']) }}</p>@endif
      <div class="grid gap-4 sm:grid-cols-2">
        <x-form-field name="name" :label="['en' => 'Full name', 'fr' => 'Nom complet', 'ar' => 'الاسم الكامل']" :required="true" :errors="$errors" />
        <x-form-field name="email" type="email" :label="['en' => 'Email', 'fr' => 'E-mail', 'ar' => 'البريد الإلكتروني']" :required="true" :errors="$errors" />
        <x-form-field name="organisation" :label="['en' => 'Organisation (optional)', 'fr' => 'Organisation (facultatif)', 'ar' => 'الجهة (اختياري)']" :errors="$errors" />
        <x-form-field name="amount" :label="['en' => 'Amount you intend to sponsor', 'fr' => 'Montant que vous souhaitez parrainer', 'ar' => 'المبلغ الذي تنوي رعايته']" :required="true" :errors="$errors" />
        <div class="sm:col-span-2"><x-form-field name="restricted" type="checkbox" :required="true" :errors="$errors" :label="['en' => 'I understand that the sum is restricted to the registry activation, refunded in full if activation fails, and returns nothing. A pledge is not a payment: nothing is due until we sign the agreement.', 'fr' => 'Je comprends que la somme est affectée à l’activation du registre, remboursée intégralement si l’activation échoue, et ne rapporte rien. Un engagement n’est pas un paiement : rien n’est dû avant la signature de l’accord.', 'ar' => 'أفهم أن المبلغ مقيّد بتفعيل السجل، ويُردّ كاملا إذا فشل التفعيل، ولا يعود بشيء. التعهد ليس دفعا: لا شيء مستحق قبل توقيع الاتفاقية.']" /></div>
      </div>
      <div class="mt-6"><button type="submit" class="btn-peak">{{ tr3('Send my pledge', 'Envoyer mon engagement', 'أرسل تعهدي') }}</button></div>
    </form>
    @endif
    <p class="mt-8 text-sm"><a class="btn-link" href="{{ url('/status/') }}">{{ tr3('See the funding status', 'Voir le statut du financement', 'اطّلع على حالة التمويل') }}</a> · <a class="btn-link" href="{{ url('/trust/statement/') }}">{{ __('cta.read_limits') }}</a></p>
    <p class="mt-3 text-xs text-slate-500">{{ tr3('The legal wording is under review by a Tunisian lawyer and accountant; verify before you rely on it.', 'La formulation juridique est en cours de revue par un avocat et un expert-comptable tunisiens ; vérifiez avant de vous y fier.', 'الصياغة القانونية قيد مراجعة محامٍ ومحاسب تونسيين؛ تحقق قبل الاعتماد عليها.') }}</p>
  </div>
</section>
@endsection

@extends('layouts.app')
@section('title', $title . ' · W5D')
@section('description', tr3('W5D plans: pipeline access, weekly review summaries and seats, paid by invoice. Certification and assessment fees listed.', 'Formules W5D : accès au pipeline, résumés hebdomadaires et sièges, payés sur facture. Frais de certification et d’évaluation listés.', 'باقات W5D: الوصول إلى المسار وملخصات أسبوعية ومقاعد، تُدفع بفاتورة. مع رسوم الاعتماد والتقييم.'))
@section('robots', 'index,follow')
@section('content')
@php $cur = \W5D\Billing::currency(); @endphp
<section class="py-14 sm:py-20">
  <div class="mx-auto max-w-6xl px-4 sm:px-6" data-rv="up">
    <p class="eyebrow">{{ tr3('Plans', 'Formules', 'الباقات') }}</p>
    <h1 class="font-display text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white">{{ $title }}</h1>
    <p class="mt-3 max-w-3xl text-slate-600 dark:text-slate-300">{{ tr3('Individual, Team and Enterprise plans open the anonymous pipeline of certified people, a weekly review summary and seats for colleagues. A plan renews by invoice: nothing is charged by itself, and you can stop at the end of any period. A plan is a service, not an investment, and promises no hiring or income.', 'Les formules Individuel, Équipe et Entreprise ouvrent le pipeline anonyme des personnes certifiées, un résumé hebdomadaire des revues et des sièges pour vos collègues. Une formule se renouvelle par facture : rien n’est prélevé automatiquement et vous pouvez arrêter à la fin de chaque période. Une formule est un service, pas un investissement, et ne promet ni embauche ni revenu.', 'تفتح باقات الأفراد والفرق والمؤسسات مسار الأشخاص المعتمدين المجهول وملخصا أسبوعيا للمراجعة ومقاعد للزملاء. تتجدد الباقة بفاتورة: لا يُخصم شيء تلقائيا ويمكنك التوقف في نهاية أي فترة. الباقة خدمة وليست استثمارا ولا تَعِد بتوظيف أو دخل.') }}</p>
    @if(!$plans)
      <p class="mt-8 rounded-2xl border border-dashed border-slate-300 dark:border-white/15 p-8 text-center text-slate-600 dark:text-slate-300" data-plans-state="none">{{ tr3('Plans are not on sale yet. Ask the partners desk to be told when they open.', 'Les formules ne sont pas encore en vente. Demandez au bureau partenaires d’être prévenu à l’ouverture.', 'الباقات غير معروضة للبيع بعد. اطلب من مكتب الشركاء إبلاغك عند فتحها.') }}</p>
    @else
    <div class="mt-8 grid gap-4 md:grid-cols-3" data-plans-state="open">
      @foreach($plans as $p)
        @php $m = \W5D\Billing::sellable($p, 'month'); $y = \W5D\Billing::sellable($p, 'year'); @endphp
        <article class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-6 flex flex-col">
          <h2 class="font-display text-xl font-semibold">{{ \W5D\Billing::planName($p) }}</h2>
          @if($m)<p class="mt-2 font-display text-2xl font-bold tabular-nums">{{ number_format(\W5D\Billing::planPrice($p, 'month', (int)$p['seats_included']), 2) }} <span class="text-sm font-normal text-slate-600 dark:text-slate-300">{{ $cur }} / {{ tr3('month', 'mois', 'شهر') }}</span></p>@endif
          @if($y)<p class="text-sm tabular-nums text-slate-600 dark:text-slate-300">{{ number_format(\W5D\Billing::planPrice($p, 'year', (int)$p['seats_included']), 2) }} {{ $cur }} / {{ tr3('year', 'an', 'سنة') }}</p>@endif
          <ul class="mt-3 text-sm text-slate-600 dark:text-slate-300 space-y-1 grow">
            <li>{{ $p['seats_included'] }} {{ tr3('seat(s) included', 'siège(s) inclus', 'مقعد مشمول') }}@if((float)$p['seat_price'] > 0) · {{ tr3('extra seat', 'siège en plus', 'مقعد إضافي') }} {{ number_format((float)$p['seat_price'], 2) }} {{ $cur }} / {{ tr3('month', 'mois', 'شهر') }}@endif</li>
            @if($p['pipeline_access'])<li>{{ tr3('Pipeline of certified people (anonymous counts)', 'Pipeline des personnes certifiées (chiffres anonymes)', 'مسار الأشخاص المعتمدين (أرقام مجهولة)') }}</li>@endif
            @if($p['weekly_reports'])<li>{{ tr3('Weekly review summary', 'Résumé hebdomadaire des revues', 'ملخص المراجعة الأسبوعي') }}</li>@endif
          </ul>
          @if($m || $y)<a class="btn-focus mt-5 justify-center" href="{{ url('/account/plan/') }}">{{ tr3('Choose in my account', 'Choisir dans mon compte', 'اختر من حسابي') }}</a>
          @elseif($p['quote_only'])<a class="btn-ghost mt-5 justify-center" href="{{ url('/contact/') }}">{{ tr3('Talk to the partners desk', 'Parler au bureau partenaires', 'تحدث إلى مكتب الشركاء') }}</a>@endif
        </article>
      @endforeach
    </div>
    @endif

    @if($fees)
    <h2 class="mt-14 font-display text-2xl font-semibold">{{ tr3('Assessment and certification fees', 'Frais d’évaluation et de certification', 'رسوم التقييم والاعتماد') }}</h2>
    <p class="mt-2 max-w-3xl text-slate-600 dark:text-slate-300">{{ tr3('One-time fees for experts and graduates. Paying a fee gives you the attempt, not the result: a certificate is issued only after a passed round, and it expires, so re-certification has its own fee.', 'Frais uniques pour experts et diplômés. Payer donne la tentative, pas le résultat : un certificat n’est délivré qu’après une session réussie, et il expire, d’où des frais de recertification distincts.', 'رسوم لمرة واحدة للخبراء والخريجين. الدفع يمنحك المحاولة لا النتيجة: لا تصدر الشهادة إلا بعد جولة ناجحة، وهي تنتهي، لذلك لإعادة الاعتماد رسومها.') }}</p>
    <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      @foreach($fees as $f)<li class="rounded-2xl border border-slate-200 dark:border-white/10 p-4"><p class="eyebrow">{{ \W5D\Billing::kindLabel((string)$f['kind']) }}@if((int)$f['level'] > 0) · L{{ $f['level'] }}@endif</p><p class="font-semibold">{{ $f['name'] }}</p><p class="font-display text-xl font-bold tabular-nums">{{ number_format((float)$f['amount'], 2) }} {{ $cur }}</p></li>@endforeach
    </ul>
    <p class="mt-4"><a class="btn-ghost" href="{{ url('/account/certificates/') }}">{{ tr3('Pay from my certificates page', 'Payer depuis ma page certificats', 'ادفع من صفحة شهاداتي') }}</a></p>
    @endif
  </div>
</section>
@endsection

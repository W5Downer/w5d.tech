@extends('layouts.app')
@section('title', $title . ' · W5D')
@section('description', tr3('Check a W5D certificate code. The result shows a masked name, the service, the level, the score and the dates.', 'Vérifiez un code de certificat W5D. Le résultat montre un nom masqué, le service, le niveau, le score et les dates.', 'تحقق من رمز شهادة W5D. تعرض النتيجة اسما مقنّعا والخدمة والمستوى والنتيجة والتواريخ.'))
@section('robots', $robots ?? 'index,follow')
@section('content')
<section class="py-14 sm:py-20">
  <div class="mx-auto max-w-2xl px-4 sm:px-6">
    <p class="eyebrow">{{ tr3('Certification', 'Certification', 'الاعتماد') }}</p>
    <h1 class="font-display text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white">{{ $title }}</h1>
    <p class="mt-3 text-slate-600 dark:text-slate-300">{{ tr3('Type the code printed on the certificate (W5D-XXXX-XXXX-XXXX). The check is public and never shows an e-mail address.', 'Saisissez le code imprimé sur le certificat (W5D-XXXX-XXXX-XXXX). La vérification est publique et n’affiche jamais d’adresse e-mail.', 'اكتب الرمز المطبوع على الشهادة (W5D-XXXX-XXXX-XXXX). التحقق عام ولا يعرض أي بريد إلكتروني.') }}</p>
    <form method="get" action="{{ url('/certification/verify/') }}" class="mt-6 flex flex-wrap gap-3 items-end" novalidate>
      <div class="grow min-w-[14rem]"><label for="f-code" class="block text-sm font-medium mb-1">{{ tr3('Certificate code', 'Code du certificat', 'رمز الشهادة') }}</label><input class="field font-mono" dir="ltr" id="f-code" name="code" value="{{ $code }}" maxlength="19" autocomplete="off" placeholder="W5D-ABCD-1234-WXYZ" required></div>
      <button class="btn-peak" type="submit">{{ icon('search', 'w-4 h-4') }} {{ tr3('Check', 'Vérifier', 'تحقق') }}</button>
    </form>

    @if($state === 'limited')
      <p class="mt-6 rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm" role="alert">{{ tr3('Too many checks from this connection. Please wait a few minutes.', 'Trop de vérifications depuis cette connexion. Patientez quelques minutes.', 'عدد كبير من عمليات التحقق من هذا الاتصال. انتظر بضع دقائق.') }}</p>
    @elseif($state === 'missing')
      <div class="mt-6 rounded-2xl border border-red-500/40 bg-red-500/10 p-5" role="alert" data-cert-state="missing"><p class="font-semibold flex items-center gap-2">{{ icon('circle-x', 'w-5 h-5 text-red-500') }} {{ tr3('No certificate with this code.', 'Aucun certificat avec ce code.', 'لا توجد شهادة بهذا الرمز.') }}</p><p class="mt-1 text-sm">{{ tr3('Check the characters. Codes use letters and digits only, in groups of four.', 'Vérifiez les caractères. Les codes n’utilisent que des lettres et des chiffres, par groupes de quatre.', 'تحقق من الأحرف. تتكون الرموز من أحرف وأرقام فقط في مجموعات من أربعة.') }}</p></div>
    @elseif($state === 'found')
      @php $ok = $rec['status'] === 'valid'; @endphp
      <div class="mt-6 rounded-2xl border p-6 {{ $ok ? 'border-emerald-500/40 bg-emerald-500/10' : 'border-amber-500/40 bg-amber-500/10' }}" data-cert-state="{{ $rec['status'] }}">
        <p class="font-semibold flex items-center gap-2">{{ icon($ok ? 'circle-check' : 'circle-x', 'w-5 h-5 ' . ($ok ? 'text-emerald-500' : 'text-amber-500')) }} {{ $ok ? tr3('Valid certificate', 'Certificat valide', 'شهادة صالحة') : ($rec['status'] === 'revoked' ? tr3('Revoked certificate', 'Certificat révoqué', 'شهادة ملغاة') : tr3('Expired certificate', 'Certificat expiré', 'شهادة منتهية')) }}</p>
        <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
          <dt class="text-slate-600 dark:text-slate-400">{{ tr3('Code', 'Code', 'الرمز') }}</dt><dd class="font-mono" dir="ltr">{{ $rec['code'] }}</dd>
          <dt class="text-slate-600 dark:text-slate-400">{{ tr3('Holder', 'Titulaire', 'الحامل') }}</dt><dd>{{ $rec['holder'] }}</dd>
          <dt class="text-slate-600 dark:text-slate-400">{{ tr3('Service', 'Service', 'الخدمة') }}</dt><dd>{{ $rec['service_title'] }}</dd>
          <dt class="text-slate-600 dark:text-slate-400">{{ tr3('Level', 'Niveau', 'المستوى') }}</dt><dd>L{{ $rec['level'] }} · {{ $rec['level_label'] }}</dd>
          @if($rec['score'] !== null)<dt class="text-slate-600 dark:text-slate-400">{{ tr3('Score', 'Score', 'النتيجة') }}</dt><dd>{{ round($rec['score']) }}%</dd>@endif
          <dt class="text-slate-600 dark:text-slate-400">{{ tr3('Issued', 'Délivré', 'تاريخ الإصدار') }}</dt><dd class="tabular-nums">{{ $rec['issued_at'] }}</dd>
          @if($rec['expires_at'])<dt class="text-slate-600 dark:text-slate-400">{{ tr3('Valid until', 'Valide jusqu’au', 'صالحة حتى') }}</dt><dd class="tabular-nums">{{ $rec['expires_at'] }}</dd>@endif
          <dt class="text-slate-600 dark:text-slate-400">{{ tr3('Issuer', 'Émetteur', 'الجهة المصدرة') }}</dt><dd>{{ $rec['issuer'] }}</dd>
        </dl>
        <p class="mt-4 text-xs text-slate-600 dark:text-slate-300">{{ tr3('Machine-readable record:', 'Fiche lisible par machine :', 'السجل المقروء آليا:') }} <a class="btn-link" href="{{ url('/api/certificates/' . $rec['code'] . '.json') }}" dir="ltr">/api/certificates/{{ $rec['code'] }}.json</a></p>
      </div>
    @endif
    <p class="mt-8 text-xs text-slate-600 dark:text-slate-400">{{ tr3('A certificate proves that someone passed a W5D round on a given day. It is not a diploma, not a job offer and not a promise of income.', 'Un certificat prouve qu’une personne a réussi une session W5D à une date donnée. Ce n’est ni un diplôme, ni une offre d’emploi, ni une promesse de revenu.', 'الشهادة تثبت أن شخصا نجح في جولة W5D في يوم محدد. وهي ليست دبلوما ولا عرض عمل ولا وعدا بدخل.') }}</p>
  </div>
</section>
@endsection

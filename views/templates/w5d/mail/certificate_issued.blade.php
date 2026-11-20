@extends('mail.layout')
@section('preheader', tr3('Your certificate is ready and can be verified online.', 'Votre certificat est prêt et vérifiable en ligne.', 'شهادتك جاهزة ويمكن التحقق منها عبر الإنترنت.'))
@section('content')
<x-mail-title>{{ tr3('Your certificate is ready', 'Votre certificat est prêt', 'شهادتك جاهزة') }}</x-mail-title>
<x-mail-p>{{ tr3('Hello', 'Bonjour', 'مرحبا') }}{{ !empty($name) ? ' ' . $name : '' }},</x-mail-p>
<x-mail-p>{{ tr3('You passed the QCM round and your certificate has been issued. Anyone can check it with the code below; the check page never shows your e-mail address.', 'Vous avez réussi la session QCM et votre certificat a été délivré. Chacun peut le vérifier avec le code ci-dessous ; la page de vérification n’affiche jamais votre adresse e-mail.', 'لقد نجحت في جولة الاختبار وصدرت شهادتك. يمكن لأي شخص التحقق منها بالرمز أدناه، ولا تعرض صفحة التحقق بريدك الإلكتروني أبدا.') }}</x-mail-p>
<x-mail-box :rows="[
  [tr3('Code', 'Code', 'الرمز'), $code],
  [tr3('Level', 'Niveau', 'المستوى'), 'L' . (int)($level ?? 1)],
  [tr3('Service', 'Service', 'الخدمة'), \W5D\Certificate::serviceTitle((string)($service ?? ''))],
  [tr3('Score', 'Score', 'النتيجة'), isset($score) && $score !== null ? round((float)$score) . '%' : '—'],
]" />
<x-mail-button :href="$verify_url" :label="tr3('Open the verification page', 'Ouvrir la page de vérification', 'افتح صفحة التحقق')" />
<x-mail-p :muted="true">{{ tr3('A certificate shows what you passed on a given day. It is not a job offer and not a promise of income.', 'Un certificat montre ce que vous avez réussi un jour donné. Ce n’est ni une offre d’emploi ni une promesse de revenu.', 'الشهادة تُظهر ما نجحت فيه في يوم محدد. وهي ليست عرض عمل ولا وعدا بدخل.') }}</x-mail-p>
@endsection

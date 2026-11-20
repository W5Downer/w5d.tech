@extends('mail.layout')
@section('preheader', tr3('An update on your QCM round fee.', 'Une mise à jour sur les frais de votre session QCM.', 'تحديث بشأن رسوم جولة الاختبار.'))
@section('content')
@php $ok = ($status ?? '') === 'verified'; @endphp
<x-mail-title>{{ $ok ? tr3('QCM fee verified', 'Frais QCM vérifiés', 'تم التحقق من رسوم الاختبار') : tr3('QCM fee not verified', 'Frais QCM non vérifiés', 'تعذّر التحقق من رسوم الاختبار') }}</x-mail-title>
<x-mail-p>{{ tr3('Hello', 'Bonjour', 'مرحبا') }}{{ !empty($name) ? ' ' . $name : '' }},</x-mail-p>
<x-mail-note :tone="$ok ? 'ok' : 'bad'"><strong>{{ $ok ? tr3('Verified by an administrator. You can start the round now.', 'Vérifié par un administrateur. Vous pouvez démarrer la session.', 'تحقق منها مسؤول. يمكنك بدء الجولة الآن.') : tr3('Rejected.', 'Rejeté.', 'مرفوضة.') }}</strong></x-mail-note>
<x-mail-box :rows="[
  [tr3('Fee', 'Frais', 'الرسوم'), $ref],
  [tr3('Amount', 'Montant', 'المبلغ'), number_format((float)$amount, 2) . ' ' . $currency],
]" />
@if(!empty($note))<x-mail-p>{{ tr3('Note from the reviewer:', 'Note du relecteur :', 'ملاحظة المراجع:') }} <em>{{ $note }}</em></x-mail-p>@endif
<x-mail-button :href="$url" :label="tr3('Open my QCM page', 'Ouvrir ma page QCM', 'افتح صفحة الاختبار')" />
@endsection

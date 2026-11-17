@extends('mail.layout')
@php $ok = ($status ?? '') === 'verified'; @endphp
@section('preheader', ($ok ? tr3('Payment verified', 'Paiement vérifié', 'تم التحقق من الدفعة') : tr3('Payment could not be verified', 'Paiement non vérifié', 'تعذّر التحقق من الدفعة')) . ' — ' . ($ref ?? ''))
@section('content')
@php $ok = ($status ?? '') === 'verified'; @endphp
<x-mail-title>{{ $ok ? tr3('Payment verified', 'Paiement vérifié', 'تم التحقق من الدفعة') : tr3('Payment could not be verified', 'Paiement non vérifié', 'تعذّر التحقق من الدفعة') }}</x-mail-title>
<x-mail-p>{{ tr3('Hello', 'Bonjour', 'مرحبا') }}{{ !empty($name) ? ' ' . $name : '' }},</x-mail-p>
<x-mail-note :tone="$ok ? 'ok' : 'bad'"><strong>{{ $ok ? tr3('Verified by an administrator.', 'Vérifié par un administrateur.', 'تحقق منه مسؤول.') : tr3('Rejected.', 'Rejeté.', 'مرفوضة.') }}</strong></x-mail-note>
<x-mail-box :rows="[
  [tr3('Payment', 'Paiement', 'الدفعة'), $ref],
  [tr3('Order', 'Commande', 'الطلب'), $order_ref],
  [tr3('Amount', 'Montant', 'المبلغ'), number_format((float)$amount, 2) . ' ' . $currency],
]" />
@if(!empty($note))<x-mail-p>{{ tr3('Note from the reviewer:', 'Note du relecteur :', 'ملاحظة المراجع:') }} <em>{{ $note }}</em></x-mail-p>@endif
@if(!$ok)
<x-mail-p>{{ tr3('Check the transaction reference and the sender number, then submit the payment again from the payment page.', 'Vérifiez la référence de transaction et le numéro émetteur, puis soumettez à nouveau le paiement depuis la page de paiement.', 'تحقق من مرجع العملية ورقم المرسل ثم أعد إرسال الدفعة من صفحة الدفع.') }}</x-mail-p>
<x-mail-button :href="$pay_url" :label="tr3('Open the payment page', 'Ouvrir la page de paiement', 'افتح صفحة الدفع')" />
@endif
@endsection

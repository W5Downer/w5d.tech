@extends('mail.layout')
@section('content')
@php $ok = ($status ?? '') === 'verified'; @endphp
<h1 style="font-size:22px;margin:0 0 12px;color:#fff;">{{ $ok ? tr3('Payment verified', 'Paiement vérifié', 'تم التحقق من الدفعة') : tr3('Payment could not be verified', 'Paiement non vérifié', 'تعذّر التحقق من الدفعة') }}</h1>
<p>{{ tr3('Hello', 'Bonjour', 'مرحبا') }} {{ $name ?? '' }},</p>
<p>{{ tr3('Payment', 'Le paiement', 'الدفعة') }} <strong>{{ $ref }}</strong> ({{ tr3('order', 'commande', 'الطلب') }} {{ $order_ref }}, {{ number_format((float)$amount, 2) }} {{ $currency }}) :
  <strong>{{ $ok ? tr3('verified by an administrator.', 'vérifié par un administrateur.', 'تحقق منه مسؤول.') : tr3('rejected.', 'rejeté.', 'مرفوضة.') }}</strong></p>
@if(!empty($note))<p style="background:#1a1a2e;border-radius:10px;padding:10px 12px;">{{ $note }}</p>@endif
@if(!$ok)<p>{{ tr3('Check the transaction reference and the sender number, then submit the payment again from the payment page.', 'Vérifiez la référence de transaction et le numéro émetteur, puis soumettez à nouveau le paiement depuis la page de paiement.', 'تحقق من مرجع العملية ورقم المرسل ثم أعد إرسال الدفعة من صفحة الدفع.') }}</p>
<x-mail-button :href="$pay_url" :label="tr3('Open the payment page', 'Ouvrir la page de paiement', 'افتح صفحة الدفع')" />@endif
@endsection

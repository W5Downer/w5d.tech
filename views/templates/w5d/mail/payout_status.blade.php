@extends('mail.layout')
@section('preheader', tr3('An update on your affiliate payout.', 'Une mise à jour sur votre paiement d’affilié.', 'تحديث بشأن صرف عمولتك.'))
@section('content')
@php $ok = ($status ?? '') === 'paid'; @endphp
<x-mail-title>{{ $ok ? tr3('Your payout was sent', 'Votre paiement a été envoyé', 'تم إرسال المبلغ') : tr3('Your payout request was not paid', 'Votre demande de paiement n’a pas été payée', 'لم يُدفع طلب الصرف') }}</x-mail-title>
<x-mail-p>{{ tr3('Hello', 'Bonjour', 'مرحبا') }}{{ !empty($name) ? ' ' . $name : '' }},</x-mail-p>
<x-mail-note :tone="$ok ? 'ok' : 'bad'"><strong>{{ $ok ? tr3('Paid by D17.', 'Payé par D17.', 'دُفع عبر D17.') : tr3('Rejected. The amount went back to your available balance.', 'Refusé. Le montant est revenu dans votre solde disponible.', 'مرفوض. عاد المبلغ إلى رصيدك المتاح.') }}</strong></x-mail-note>
<x-mail-box :rows="array_values(array_filter([
  [tr3('Amount', 'Montant', 'المبلغ'), number_format((float)$amount, 2) . ' ' . $currency],
  $ok ? [tr3('D17 reference', 'Référence D17', 'مرجع D17'), $txn] : null,
]))" />
@if(!empty($note))<x-mail-p>{{ tr3('Note:', 'Note :', 'ملاحظة:') }} <em>{{ $note }}</em></x-mail-p>@endif
<x-mail-button :href="$url" :label="tr3('Open my affiliate page', 'Ouvrir ma page d’affilié', 'افتح صفحة الإحالة')" />
@endsection

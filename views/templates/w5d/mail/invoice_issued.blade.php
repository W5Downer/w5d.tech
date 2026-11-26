@extends('mail.layout')
@section('preheader', tr3('A new invoice is waiting for you.', 'Une nouvelle facture vous attend.', 'فاتورة جديدة بانتظارك.'))
@section('content')
<x-mail-title>{{ !empty($why) ? tr3('Payment reminder', 'Rappel de paiement', 'تذكير بالدفع') : tr3('Your invoice', 'Votre facture', 'فاتورتك') }}</x-mail-title>
<x-mail-p>{{ tr3('Hello', 'Bonjour', 'مرحبا') }}{{ !empty($name) ? ' ' . $name : '' }},</x-mail-p>
<x-mail-p>{{ !empty($title) ? $title : tr3('A new invoice was issued for you.', 'Une nouvelle facture a été émise pour vous.', 'صدرت لك فاتورة جديدة.') }}</x-mail-p>
<x-mail-box :rows="[
  [tr3('Invoice', 'Facture', 'الفاتورة'), $ref],
  [tr3('Amount', 'Montant', 'المبلغ'), number_format((float)$amount, 2) . ' ' . $currency],
  [tr3('Due', 'Échéance', 'الاستحقاق'), !empty($due) ? $due : '—'],
]" />
<x-mail-button :href="$pay_url" :label="tr3('Pay this invoice', 'Payer cette facture', 'ادفع هذه الفاتورة')" />
<x-mail-p><a href="{{ $view_url }}">{{ tr3('View or print the invoice', 'Voir ou imprimer la facture', 'عرض الفاتورة أو طباعتها') }}</a></x-mail-p>
@endsection

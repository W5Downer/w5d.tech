@extends('layouts.app')
@section('title', $title . ' · W5D')
@section('description', tr3('An invoice from W5D.', 'Une facture W5D.', 'فاتورة من W5D.'))
@section('robots', 'noindex,nofollow')
@section('chromeless', '1')
@section('content')
@php
  $paid = $inv['status'] === 'paid'; $void = $inv['status'] === 'void';
  $stLabel = $paid ? tr3('Paid', 'Payée', 'مدفوعة') : ($void ? tr3('Void', 'Annulée', 'ملغاة') : tr3('Awaiting payment', 'En attente de paiement', 'بانتظار الدفع'));
@endphp
<section class="py-10 sm:py-14">
  <div class="mx-auto max-w-3xl px-4 sm:px-6">
    <p class="mb-4 flex flex-wrap gap-3 no-print">
      @if(!empty($payUrl))<a class="btn-peak" href="{{ $payUrl }}">{{ icon('wallet', 'w-4 h-4') }} {{ tr3('Pay this invoice', 'Payer cette facture', 'ادفع هذه الفاتورة') }}</a>@endif
      <button type="button" class="btn-ghost" data-print>{{ icon('printer', 'w-4 h-4') }} {{ tr3('Print or save as PDF', 'Imprimer ou enregistrer en PDF', 'اطبع أو احفظ بصيغة PDF') }}</button>
    </p>
    <article class="cert-sheet rounded-3xl border border-slate-300 bg-white text-slate-900 p-6 sm:p-10" data-invoice-status="{{ $inv['status'] }}">
      <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p class="text-xs tracking-[0.3em] uppercase text-violet-700">W5D · {{ tr3('Invoice', 'Facture', 'فاتورة') }}</p>
          <h1 class="mt-2 font-display text-2xl sm:text-3xl font-bold tabular-nums" dir="ltr">{{ $inv['number'] }}</h1>
        </div>
        <p class="rounded-full px-3 py-1 text-sm font-semibold {{ $paid ? 'bg-emerald-100 text-emerald-800' : ($void ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-900') }}">{{ $stLabel }}</p>
      </header>
      <dl class="mt-6 grid gap-4 sm:grid-cols-2 text-sm">
        <div><dt class="text-slate-600">{{ tr3('Billed to', 'Facturé à', 'صدرت إلى') }}</dt><dd class="font-semibold">{{ $inv['name'] ?: '—' }}</dd><dd class="text-slate-700" dir="ltr">{{ $inv['email'] }}</dd></div>
        <div><dt class="text-slate-600">{{ tr3('From', 'De', 'من') }}</dt><dd class="font-semibold">W5D</dd>@if(!empty($seller))<dd class="text-slate-700">{{ $seller }}</dd>@endif</div>
        <div><dt class="text-slate-600">{{ tr3('Issued', 'Émise le', 'تاريخ الإصدار') }}</dt><dd class="tabular-nums">{{ $inv['issued_at'] ?: $inv['created_at'] ?? '' }}</dd></div>
        <div><dt class="text-slate-600">{{ $paid ? tr3('Paid on', 'Payée le', 'تاريخ الدفع') : tr3('Due', 'Échéance', 'الاستحقاق') }}</dt><dd class="tabular-nums">{{ $paid ? $inv['paid_at'] : ($inv['due_on'] ?: '—') }}</dd></div>
      </dl>
      <div class="mt-6 overflow-x-auto">
        <table class="w-full text-sm">
          <thead><tr class="border-b border-slate-300 text-start"><th scope="col" class="py-2 text-start">{{ tr3('Description', 'Description', 'الوصف') }}</th><th scope="col" class="py-2 text-end">{{ tr3('Qty', 'Qté', 'الكمية') }}</th><th scope="col" class="py-2 text-end">{{ tr3('Unit', 'Unitaire', 'سعر الوحدة') }}</th><th scope="col" class="py-2 text-end">{{ tr3('Amount', 'Montant', 'المبلغ') }}</th></tr></thead>
          <tbody>
          @foreach($lines as $l)
            <tr class="border-b border-slate-200"><td class="py-2" dir="auto">{{ \W5D\Billing::desc((string)$l['description']) }}</td><td class="py-2 text-end tabular-nums">{{ rtrim(rtrim(number_format((float)$l['qty'], 2), '0'), '.') }}</td><td class="py-2 text-end tabular-nums">{{ number_format((float)$l['unit_price'], 2) }}</td><td class="py-2 text-end tabular-nums">{{ number_format((float)$l['amount'], 2) }}</td></tr>
          @endforeach
          </tbody>
          <tfoot><tr><th scope="row" colspan="3" class="py-3 text-end">{{ tr3('Total', 'Total', 'المجموع') }}</th><td class="py-3 text-end font-bold tabular-nums">{{ number_format((float)$inv['amount'], 2) }} {{ $inv['currency'] }}</td></tr></tfoot>
        </table>
      </div>
      @if($paid && !empty($inv['method']))<p class="mt-4 text-sm text-slate-700">{{ tr3('Paid with', 'Payée avec', 'طريقة الدفع') }}: <strong>{{ $inv['method'] }}</strong>@if(!empty($inv['payment_ref'])) · <span dir="ltr">{{ $inv['payment_ref'] }}</span>@endif</p>@endif
      @if(!empty($inv['note']))<p class="mt-4 text-sm text-slate-700" dir="auto">{{ $inv['note'] }}</p>@endif
      <p class="mt-8 text-[11px] text-slate-600">{{ tr3('This is a payment receipt and request for the service shown. Tax details, if any apply to you, are not included unless a line says so.', 'Ce document est une demande et un reçu de paiement pour le service indiqué. Les éléments fiscaux, s’ils s’appliquent à vous, ne sont pas inclus sauf mention sur une ligne.', 'هذه الوثيقة طلب دفع وإيصال للخدمة المذكورة. لا تشمل التفاصيل الضريبية، إن انطبقت عليك، إلا إذا ذُكرت في أحد السطور.') }}</p>
    </article>
  </div>
</section>
@endsection

@extends('mail.layout')
@section('content')
<h1 style="font-size:22px;margin:0 0 12px;color:#fff;">{{ tr3('Your pledge is recorded', 'Votre engagement est enregistré', 'سُجّل التزامك') }}</h1>
<p>{{ tr3('Hello', 'Bonjour', 'مرحبا') }} {{ $name ?? '' }},</p>
<table role="presentation" cellpadding="6" cellspacing="0" style="width:100%;border:1px solid #1f1f3a;border-radius:12px;font-size:14px;">
  <tr><td style="color:#94a3b8;">{{ tr3('Reference', 'Référence', 'المرجع') }}</td><td><strong>{{ $ref }}</strong></td></tr>
  <tr><td style="color:#94a3b8;">{{ tr3('Parts', 'Parts', 'الحصص') }}</td><td>{{ $parts }} × {{ $days }}</td></tr>
  <tr><td style="color:#94a3b8;">{{ tr3('Starts', 'Début', 'البدء') }}</td><td>{{ $start }}</td></tr>
  <tr><td style="color:#94a3b8;">{{ tr3('Total of the schedule', 'Total du calendrier', 'مجموع الجدول') }}</td><td><strong>{{ number_format((float)$total, 2) }} {{ $currency }}</strong></td></tr>
</table>
@if(!empty($d17))
<h2 style="font-size:17px;margin:22px 0 8px;color:#fff;">{{ tr3('Pay with D17 (Tunisia)', 'Payer avec D17 (Tunisie)', 'الدفع عبر D17 (تونس)') }}</h2>
<p>{{ tr3('Send', 'Envoyez', 'أرسل') }} <strong>{{ number_format((float)$d17['amount'], 2) }} {{ $currency }}</strong> {{ tr3('by D17 to', 'par D17 au', 'عبر D17 إلى الرقم') }} <strong dir="ltr">{{ $d17['gsm'] }}</strong>@if(!empty($d17['name'])) ({{ $d17['name'] }})@endif. {{ tr3('Then enter the D17 transaction reference on the payment page. An administrator verifies it by hand; your pledge stays “awaiting verification” until then.', 'Saisissez ensuite la référence de transaction D17 sur la page de paiement. Un administrateur la vérifie manuellement ; votre engagement reste « en attente de vérification » d’ici là.', 'ثم أدخل مرجع عملية D17 في صفحة الدفع. يتحقق منها مسؤول يدويا؛ ويبقى التزامك «بانتظار التحقق» حتى ذلك الحين.') }}</p>
<x-mail-button :href="$pay_url" :label="tr3('Open the payment page', 'Ouvrir la page de paiement', 'افتح صفحة الدفع')" />
@else
<p>{{ tr3('Demo mode: no payment is taken and no money moves.', 'Mode démo : aucun paiement n’est prélevé.', 'الوضع التجريبي: لا يُحصَّل أي دفع ولا تتحرك أموال.') }}</p>
@endif
@if(!empty($key))<p>{{ tr3('Management key (shown once):', 'Clé de gestion (affichée une fois) :', 'مفتاح الإدارة (يظهر مرة واحدة):') }} <code style="background:#1a1a2e;padding:2px 6px;border-radius:6px;">{{ $key }}</code></p>@endif
@endsection

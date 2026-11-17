@extends('mail.layout')
@section('preheader', tr3('Your pledge', 'Votre engagement', 'التزامك') . ' ' . ($ref ?? '') . ' — ' . tr3('recorded.', 'enregistré.', 'سُجّل.'))
@section('content')
<x-mail-title>{{ tr3('Your pledge is recorded', 'Votre engagement est enregistré', 'سُجّل التزامك') }}</x-mail-title>
<x-mail-p>{{ tr3('Hello', 'Bonjour', 'مرحبا') }}{{ !empty($name) ? ' ' . $name : '' }},</x-mail-p>
<x-mail-box :rows="[
  [tr3('Reference', 'Référence', 'المرجع'), $ref],
  [tr3('Parts', 'Parts', 'الحصص'), $parts . ' × ' . $days],
  [tr3('Starts', 'Début', 'البدء'), $start],
  [tr3('Total of the schedule', 'Total du calendrier', 'مجموع الجدول'), number_format((float)$total, 2) . ' ' . $currency],
]" />
@if(!empty($d17))
<h2 class="t-main" style="font-size:18px;margin:24px 0 8px;color:#0f172a;">{{ tr3('Pay with D17 (Tunisia)', 'Payer avec D17 (Tunisie)', 'الدفع عبر D17 (تونس)') }}</h2>
<x-mail-p>{{ tr3('Send', 'Envoyez', 'أرسل') }} <strong>{{ number_format((float)$d17['amount'], 2) }} {{ $currency }}</strong> {{ tr3('by D17 to', 'par D17 au', 'عبر D17 إلى الرقم') }} <strong dir="ltr">{{ $d17['gsm'] }}</strong>@if(!empty($d17['name'])) ({{ $d17['name'] }})@endif.</x-mail-p>
<x-mail-note tone="info">{{ tr3('Then enter the D17 transaction reference on the payment page. An administrator verifies it by hand; your pledge stays “awaiting verification” until then.', 'Saisissez ensuite la référence de transaction D17 sur la page de paiement. Un administrateur la vérifie manuellement ; votre engagement reste « en attente de vérification » d’ici là.', 'ثم أدخل مرجع عملية D17 في صفحة الدفع. يتحقق منها مسؤول يدويا؛ ويبقى التزامك «بانتظار التحقق» حتى ذلك الحين.') }}</x-mail-note>
<x-mail-button :href="$pay_url" :label="tr3('Open the payment page', 'Ouvrir la page de paiement', 'افتح صفحة الدفع')" />
@else
<x-mail-note tone="info">{{ tr3('Demo mode: no payment is taken and no money moves.', 'Mode démo : aucun paiement n’est prélevé.', 'الوضع التجريبي: لا يُحصَّل أي دفع ولا تتحرك أموال.') }}</x-mail-note>
@endif
@if(!empty($key))<x-mail-p>{{ tr3('Management key (shown once):', 'Clé de gestion (affichée une fois) :', 'مفتاح الإدارة (يظهر مرة واحدة):') }} <code dir="ltr" style="background:#eef2ff;color:#3730a3;padding:3px 8px;border-radius:6px;font-size:13px;">{{ $key }}</code></x-mail-p>@endif
@endsection

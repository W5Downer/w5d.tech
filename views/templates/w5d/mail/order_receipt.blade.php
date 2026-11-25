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
  ...(!empty($fee) ? [[tr3('Transaction fee', 'Frais de transaction', 'رسوم العملية'), number_format((float)$fee, 2) . ' ' . $currency], [tr3('To pay', 'À payer', 'المبلغ المستحق'), number_format((float)($due ?? $total), 2) . ' ' . $currency]] : []),
]" />
@if(!empty($method))
<h2 class="t-main" style="font-size:18px;margin:24px 0 8px;color:#0f172a;">{{ tr3('Pay with', 'Payer avec', 'الدفع عبر') }} {{ $method_name }}</h2>
@if(!empty($manual))
@if(!empty($d17))
<x-mail-p>{{ tr3('Send', 'Envoyez', 'أرسل') }} <strong>{{ number_format((float)$d17['amount'], 2) }} {{ $currency }}</strong> {{ tr3('by D17 to', 'par D17 au', 'عبر D17 إلى الرقم') }} <strong dir="ltr">{{ $d17['gsm'] }}</strong>@if(!empty($d17['name'])) ({{ $d17['name'] }})@endif.</x-mail-p>
@elseif(!empty($manual_rows))
<x-mail-box :rows="array_map(fn($r) => [$r[0], $r[1]], $manual_rows)" />
@endif
<x-mail-note tone="info">{{ tr3('Then enter your transaction reference on the payment page. An administrator verifies it by hand; your pledge stays “awaiting verification” until then.', 'Saisissez ensuite votre référence de transaction sur la page de paiement. Un administrateur la vérifie manuellement ; votre engagement reste « en attente de vérification » d’ici là.', 'ثم أدخل مرجع عمليتك في صفحة الدفع. يتحقق منها مسؤول يدويا؛ ويبقى التزامك «بانتظار التحقق» حتى ذلك الحين.') }}</x-mail-note>
@else
<x-mail-note tone="info">{{ tr3('You pay on the provider’s secure page. Your order is confirmed only when the provider confirms the payment to our server.', 'Vous payez sur la page sécurisée du prestataire. Votre commande n’est confirmée que lorsque celui-ci confirme le paiement à notre serveur.', 'تدفع على صفحة المزوّد الآمنة. لا يُؤكَّد طلبك إلا عندما يؤكد المزوّد الدفع لخادمنا.') }}</x-mail-note>
@endif
<x-mail-button :href="$pay_url" :label="tr3('Open the payment page', 'Ouvrir la page de paiement', 'افتح صفحة الدفع')" />
@else
<x-mail-note tone="info">{{ tr3('No payment was requested with this pledge. If payments open later, you will receive the amount and the steps before anything is charged.', 'Aucun paiement n’a été demandé avec cet engagement. Si les paiements ouvrent plus tard, vous recevrez le montant et les étapes avant tout prélèvement.', 'لم يُطلب أي دفع مع هذا الالتزام. إذا فُتح الدفع لاحقا ستصلك قيمة المبلغ والخطوات قبل أي خصم.') }}</x-mail-note>
@endif
@if(!empty($key))<x-mail-p>{{ tr3('Management key (shown once):', 'Clé de gestion (affichée une fois) :', 'مفتاح الإدارة (يظهر مرة واحدة):') }} <code dir="ltr" style="background:#eef2ff;color:#3730a3;padding:3px 8px;border-radius:6px;font-size:13px;">{{ $key }}</code></x-mail-p>@endif
@endsection

@extends('mail.layout')
@php $ok = ($status ?? '') === 'active'; @endphp
@section('preheader', tr3('The status of your affiliate profile changed.', 'Le statut de votre profil d’affilié a changé.', 'تغيّرت حالة ملف الإحالة الخاص بك.'))
@section('content')
@php $ok = ($status ?? '') === 'active'; @endphp
<x-mail-title>{{ $ok ? tr3('You are an active affiliate', 'Vous êtes affilié actif', 'أنت مُحيل نشط') : tr3('Your affiliate profile changed', 'Votre profil d’affilié a changé', 'تغيّر ملف الإحالة الخاص بك') }}</x-mail-title>
<x-mail-p>{{ tr3('Hello', 'Bonjour', 'مرحبا') }}{{ !empty($name) ? ' ' . $name : '' }},</x-mail-p>
<x-mail-note :tone="$ok ? 'ok' : 'bad'"><strong>{{ $ok ? tr3('Approved by the owner.', 'Approuvé par le propriétaire.', 'وافق عليه المالك.') : tr3('Status: ', 'Statut : ', 'الحالة: ') . ($status ?? '') }}</strong></x-mail-note>
<x-mail-box :rows="[[tr3('Your code', 'Votre code', 'رمزك'), $code]]" />
<x-mail-p>{{ tr3('Commissions are small, reviewed by hand and paid by D17 once the available balance reaches the minimum. They are not income you can plan on.', 'Les commissions sont modestes, examinées à la main et payées par D17 quand le solde disponible atteint le minimum. Ce n’est pas un revenu sur lequel compter.', 'العمولات صغيرة وتُراجع يدويا وتُدفع عبر D17 عند بلوغ الرصيد المتاح الحد الأدنى. وهي ليست دخلا يُعتمد عليه.') }}</x-mail-p>
<x-mail-button :href="$url" :label="tr3('Open my affiliate page', 'Ouvrir ma page d’affilié', 'افتح صفحة الإحالة')" />
@endsection

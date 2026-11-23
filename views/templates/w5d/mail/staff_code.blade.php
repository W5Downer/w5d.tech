@extends('mail.layout')
@section('preheader', tr3('Your one-time sign-in code. It works once.', 'Votre code de connexion à usage unique.', 'رمز الدخول لمرة واحدة.'))
@section('content')
<x-mail-title>{{ tr3('Your team sign-in code', 'Votre code de connexion équipe', 'رمز دخول الفريق') }}</x-mail-title>
<x-mail-p>{{ tr3('Hello', 'Bonjour', 'مرحبا') }}{{ !empty($name) ? ' ' . $name : '' }},</x-mail-p>
<x-mail-p>{{ tr3('Enter this code on the W5D sign-in page. It is valid for', 'Saisissez ce code sur la page de connexion W5D. Il est valable', 'أدخل هذا الرمز في صفحة الدخول إلى W5D. صالح لمدة') }} {{ $minutes ?? 10 }} {{ tr3('minutes and works once.', 'minutes et ne fonctionne qu’une fois.', 'دقيقة ويعمل مرة واحدة.') }}</x-mail-p>
<x-mail-box :rows="[[tr3('Code', 'Code', 'الرمز'), implode(' ', str_split((string)($code ?? ''), 3))]]" />
<x-mail-note tone="warn">{{ tr3('Nobody at W5D will ever ask you for this code. If you did not try to sign in, ignore this message and tell the owner.', 'Personne chez W5D ne vous demandera jamais ce code. Si vous n’avez pas tenté de vous connecter, ignorez ce message et prévenez le propriétaire.', 'لن يطلب منك أحد في W5D هذا الرمز أبدا. إن لم تحاول الدخول فتجاهل الرسالة وأبلغ المالك.') }}</x-mail-note>
@if(!empty($ip))<x-mail-p :muted="true">{{ tr3('Request made from IP', 'Demande faite depuis l’IP', 'الطلب من العنوان') }} {{ $ip }}</x-mail-p>@endif
@endsection

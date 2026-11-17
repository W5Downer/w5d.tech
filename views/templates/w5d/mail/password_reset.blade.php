@extends('mail.layout')
@section('content')
<h1 style="font-size:22px;margin:0 0 12px;color:#fff;">{{ tr3('Reset your password', 'Réinitialisez votre mot de passe', 'أعد تعيين كلمة المرور') }}</h1>
<p>{{ tr3('Hello', 'Bonjour', 'مرحبا') }} {{ $name ?? '' }},</p>
<p>{{ tr3('We received a request to reset your password. The link below is valid for', 'Nous avons reçu une demande de réinitialisation de votre mot de passe. Le lien ci-dessous est valable', 'تلقينا طلبا لإعادة تعيين كلمة مرورك. الرابط أدناه صالح لمدة') }} {{ $minutes ?? 60 }} {{ tr3('minutes and works once.', 'minutes et ne fonctionne qu’une fois.', 'دقيقة ويعمل مرة واحدة.') }}</p>
<x-mail-button :href="$url" :label="tr3('Choose a new password', 'Choisir un nouveau mot de passe', 'اختر كلمة مرور جديدة')" />
<p style="color:#94a3b8;font-size:13px;">{{ tr3('If you did not ask for this, ignore this message: your password is unchanged.', 'Si vous n’êtes pas à l’origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.', 'إن لم تطلب ذلك فتجاهل الرسالة: كلمة مرورك لم تتغير.') }}</p>
@endsection

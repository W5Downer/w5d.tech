@extends('mail.layout')
@section('preheader', tr3('Choose a new password. The link works once.', 'Choisissez un nouveau mot de passe. Le lien ne fonctionne qu’une fois.', 'اختر كلمة مرور جديدة. الرابط يعمل مرة واحدة.'))
@section('content')
<x-mail-title>{{ tr3('Reset your password', 'Réinitialisez votre mot de passe', 'أعد تعيين كلمة المرور') }}</x-mail-title>
<x-mail-p>{{ tr3('Hello', 'Bonjour', 'مرحبا') }}{{ !empty($name) ? ' ' . $name : '' }},</x-mail-p>
<x-mail-p>{{ tr3('We received a request to reset your password. The link below is valid for', 'Nous avons reçu une demande de réinitialisation de votre mot de passe. Le lien ci-dessous est valable', 'تلقينا طلبا لإعادة تعيين كلمة مرورك. الرابط أدناه صالح لمدة') }} {{ $minutes ?? 60 }} {{ tr3('minutes and works once.', 'minutes et ne fonctionne qu’une fois.', 'دقيقة ويعمل مرة واحدة.') }}</x-mail-p>
<x-mail-button :href="$url" :label="tr3('Choose a new password', 'Choisir un nouveau mot de passe', 'اختر كلمة مرور جديدة')" />
<x-mail-note tone="warn">{{ tr3('If you did not ask for this, ignore this message: your password is unchanged.', 'Si vous n’êtes pas à l’origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.', 'إن لم تطلب ذلك فتجاهل الرسالة: كلمة مرورك لم تتغير.') }}</x-mail-note>
@endsection

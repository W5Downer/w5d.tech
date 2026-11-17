@extends('mail.layout')
@section('content')
<h1 style="font-size:22px;margin:0 0 12px;color:#fff;">{{ tr3('Confirm your e-mail address', 'Confirmez votre adresse e-mail', 'أكّد عنوان بريدك الإلكتروني') }}</h1>
<p>{{ tr3('Hello', 'Bonjour', 'مرحبا') }} {{ $name ?? '' }},</p>
<p>{{ tr3('Use the button below to confirm this address and activate your W5D account. The link is valid for 24 hours and works once.', 'Utilisez le bouton ci-dessous pour confirmer cette adresse et activer votre compte W5D. Le lien est valable 24 heures et ne fonctionne qu’une fois.', 'استخدم الزر أدناه لتأكيد هذا العنوان وتفعيل حسابك في W5D. الرابط صالح 24 ساعة ويعمل مرة واحدة.') }}</p>
<x-mail-button :href="$url" :label="tr3('Confirm my e-mail', 'Confirmer mon e-mail', 'أكّد بريدي')" />
<p style="color:#94a3b8;font-size:13px;">{{ tr3('If you did not create an account, ignore this message: nothing happens without the click.', 'Si vous n’avez pas créé de compte, ignorez ce message : rien ne se passe sans le clic.', 'إن لم تُنشئ حسابا فتجاهل هذه الرسالة: لا يحدث شيء دون النقر.') }}</p>
@endsection

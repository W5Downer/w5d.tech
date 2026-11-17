@extends('mail.layout')
@section('preheader', tr3('Confirm your address to activate your W5D account (valid 24 hours).', 'Confirmez votre adresse pour activer votre compte W5D (valable 24 heures).', 'أكّد عنوانك لتفعيل حسابك في W5D (صالح 24 ساعة).'))
@section('content')
<x-mail-title>{{ tr3('Confirm your e-mail address', 'Confirmez votre adresse e-mail', 'أكّد عنوان بريدك الإلكتروني') }}</x-mail-title>
<x-mail-p>{{ tr3('Hello', 'Bonjour', 'مرحبا') }}{{ !empty($name) ? ' ' . $name : '' }},</x-mail-p>
<x-mail-p>{{ tr3('Use the button below to confirm this address and activate your W5D account. The link is valid for 24 hours and works once.', 'Utilisez le bouton ci-dessous pour confirmer cette adresse et activer votre compte W5D. Le lien est valable 24 heures et ne fonctionne qu’une fois.', 'استخدم الزر أدناه لتأكيد هذا العنوان وتفعيل حسابك في W5D. الرابط صالح 24 ساعة ويعمل مرة واحدة.') }}</x-mail-p>
<x-mail-button :href="$url" :label="tr3('Confirm my e-mail', 'Confirmer mon e-mail', 'أكّد بريدي')" />
<x-mail-p :muted="true">{{ tr3('If you did not create an account, ignore this message: nothing happens without the click.', 'Si vous n’avez pas créé de compte, ignorez ce message : rien ne se passe sans le clic.', 'إن لم تُنشئ حسابا فتجاهل هذه الرسالة: لا يحدث شيء دون النقر.') }}</x-mail-p>
@endsection

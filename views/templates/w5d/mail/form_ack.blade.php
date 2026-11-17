@extends('mail.layout')
@php $kind = $kind ?? 'contact'; @endphp
@if(!empty($staff))
@section('preheader', $title ?? 'W5D')
@elseif($kind === 'newsletter')
@section('preheader', tr3('You are on the W5D dated-updates list.', 'Vous êtes inscrit aux mises à jour datées de W5D.', 'تم تسجيلك في تحديثات W5D المؤرخة.'))
@else
@section('preheader', tr3('We received your request.', 'Nous avons bien reçu votre demande.', 'استلمنا طلبك.'))
@endif
@section('content')
@if(!empty($staff))
<x-mail-title>{{ $title }}</x-mail-title>
<x-mail-box :rows="array_map(fn($k, $v) => [(string)$k, (string)$v], array_keys($lines), array_values($lines))" />
@else
@php $kind = $kind ?? 'contact'; @endphp
<x-mail-title>{{ $kind === 'newsletter' ? tr3('You are subscribed', 'Inscription confirmée', 'تم اشتراكك') : tr3('We received your request', 'Nous avons bien reçu votre demande', 'استلمنا طلبك') }}</x-mail-title>
<x-mail-p>{{ tr3('Hello', 'Bonjour', 'مرحبا') }}{{ !empty($name) ? ' ' . $name : '' }},</x-mail-p>
@if($kind === 'register')
<x-mail-p>{{ tr3('Your applicant file is open. Registration starts certification; it is not a job offer. A human reviewer checks identity and eligibility next.', 'Votre dossier de candidature est ouvert. L’inscription lance la certification ; ce n’est pas une offre d’emploi. Un relecteur humain vérifie ensuite l’identité et l’éligibilité.', 'تم فتح ملفك. يبدأ التسجيل الاعتماد وليس عرض عمل. ثم يتحقق مراجع بشري من الهوية والأهلية.') }}</x-mail-p>
@elseif($kind === 'manager')
<x-mail-p>{{ tr3('Your manager application is received. Applications are read by people, ranked for reading order only, and never rejected automatically.', 'Votre candidature de manager est reçue. Les candidatures sont lues par des personnes, classées pour l’ordre de lecture seulement, jamais rejetées automatiquement.', 'استُلم طلبك لمنصب مدير. يقرأ الطلبات أشخاص ويُرتَّب للقراءة فقط دون رفض آلي.') }}</x-mail-p>
@elseif($kind === 'newsletter')
<x-mail-p>{{ tr3('You are on the W5D dated-updates list: a short, dated message when something real changes, nothing else.', 'Vous êtes inscrit à la liste des mises à jour datées de W5D : un court message daté quand quelque chose de réel change, rien d’autre.', 'أنت الآن في قائمة تحديثات W5D المؤرخة: رسالة قصيرة مؤرخة عندما يتغير شيء حقيقي، لا غير.') }}</x-mail-p>
<x-mail-p :muted="true">{{ tr3('You can leave at any time with the unsubscribe link at the bottom of every message.', 'Vous pouvez partir à tout moment avec le lien de désabonnement en bas de chaque message.', 'يمكنك المغادرة في أي وقت عبر رابط إلغاء الاشتراك أسفل كل رسالة.') }}</x-mail-p>
@else
<x-mail-p>{{ tr3('Your message reached the right desk. We answer with a public source when one exists.', 'Votre message est arrivé au bon bureau. Nous répondons avec une source publique lorsqu’il en existe une.', 'وصلت رسالتك إلى المكتب المناسب. نجيب بمصدر عام عند وجوده.') }}</x-mail-p>
@endif
@if(!empty($ref) && $ref !== '—')<x-mail-box :rows="[[tr3('Reference', 'Référence', 'المرجع'), $ref]]" />@endif
@endif
@endsection

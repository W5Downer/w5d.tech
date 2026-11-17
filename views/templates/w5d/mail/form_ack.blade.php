@extends('mail.layout')
@section('content')
@if(!empty($staff))
<h1 style="font-size:20px;margin:0 0 12px;color:#fff;">{{ $title }}</h1>
<ul>@foreach($lines as $k => $v)<li><strong>{{ $k }}:</strong> {{ $v }}</li>@endforeach</ul>
@else
@php $kind = $kind ?? 'contact'; @endphp
<h1 style="font-size:22px;margin:0 0 12px;color:#fff;">{{ tr3('We received your request', 'Nous avons bien reçu votre demande', 'استلمنا طلبك') }}</h1>
<p>{{ tr3('Hello', 'Bonjour', 'مرحبا') }} {{ $name ?? '' }},</p>
<p>@switch($kind)
@case('register'){{ tr3('Your applicant file is open. Registration starts certification; it is not a job offer. A human reviewer checks identity and eligibility next.', 'Votre dossier de candidature est ouvert. L’inscription lance la certification ; ce n’est pas une offre d’emploi. Un relecteur humain vérifie ensuite l’identité et l’éligibilité.', 'تم فتح ملفك. يبدأ التسجيل الاعتماد وليس عرض عمل. ثم يتحقق مراجع بشري من الهوية والأهلية.') }}@break
@case('manager'){{ tr3('Your manager application is received. Applications are read by people, ranked for reading order only, and never rejected automatically.', 'Votre candidature de manager est reçue. Les candidatures sont lues par des personnes, classées pour l’ordre de lecture seulement, jamais rejetées automatiquement.', 'استُلم طلبك لمنصب مدير. يقرأ الطلبات أشخاص ويُرتَّب للقراءة فقط دون رفض آلي.') }}@break
@case('newsletter'){{ tr3('You are on the W5D dated-updates list. Write to the contact desk at any time to be removed.', 'Vous êtes inscrit à la liste des mises à jour datées de W5D. Écrivez au bureau de contact à tout moment pour être retiré.', 'تم تسجيلك في قائمة تحديثات W5D المؤرخة. اكتب إلى مكتب التواصل في أي وقت لإزالتك.') }}@break
@default{{ tr3('Your message reached the right desk. We answer with a public source when one exists.', 'Votre message est arrivé au bon bureau. Nous répondons avec une source publique lorsqu’il en existe une.', 'وصلت رسالتك إلى المكتب المناسب. نجيب بمصدر عام عند وجوده.') }}
@endswitch</p>
@if(!empty($ref) && $ref !== '—')<p>{{ tr3('Reference', 'Référence', 'المرجع') }}: <strong>{{ $ref }}</strong></p>@endif
@endif
@endsection

@extends('layouts.app')
@section('title', tr3('Unsubscribe', 'Se désabonner', 'إلغاء الاشتراك') . ' · W5D')
@section('description', tr3('Manage W5D dated-update e-mails.', 'Gérer les e-mails de mises à jour datées W5D.', 'إدارة رسائل تحديثات W5D المؤرخة.'))
@section('robots', 'noindex,nofollow')
@section('content')
@php
  $state = $state ?? 'invalid';
  $email = $email ?? '';
  $sig = $sig ?? '';
  $action = url('/unsubscribe/');
@endphp
<section class="py-14 sm:py-20">
  <div class="mx-auto max-w-md px-4 sm:px-6">
    <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-6 sm:p-8">
      @if($state === 'confirm')
        <h1 class="font-display text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">{{ tr3('Unsubscribe from updates', 'Se désabonner des mises à jour', 'إلغاء الاشتراك في التحديثات') }}</h1>
        @if(!empty($error))<p class="mt-4 rounded-lg bg-red-500/10 border border-red-500/40 p-3 text-sm" role="alert">{{ tr3('Session expired. Reload and try again.', 'Session expirée. Rechargez et réessayez.', 'انتهت الجلسة. أعد التحميل وحاول مجددا.') }}</p>@endif
        <p class="mt-4 text-slate-600 dark:text-slate-300">{{ tr3('Stop dated-update e-mails to', 'Arrêter les e-mails de mises à jour datées vers', 'إيقاف رسائل التحديثات المؤرخة إلى') }} <strong dir="ltr">{{ $email }}</strong>?</p>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ tr3('Service e-mails (account verification, password reset, receipts, payment status) are still sent when you use those features.', 'Les e-mails de service (vérification de compte, réinitialisation, reçus, statut de paiement) restent envoyés lorsque vous utilisez ces fonctions.', 'تبقى رسائل الخدمة (تأكيد الحساب، إعادة التعيين، الإيصالات، حالة الدفع) مرسلة عند استخدامك لهذه الميزات.') }}</p>
        <form method="post" action="{{ $action }}" class="mt-6">
          {!! csrf_field() !!}
          <input type="hidden" name="e" value="{{ $email }}"><input type="hidden" name="s" value="{{ $sig }}">
          <input type="hidden" name="action" value="unsubscribe">
          <button type="submit" class="btn-peak w-full">{{ tr3('Yes, unsubscribe me', 'Oui, me désabonner', 'نعم، ألغِ اشتراكي') }}</button>
        </form>
        <p class="mt-4 text-sm"><a class="btn-link" href="{{ url('/') }}">{{ tr3('No, keep me subscribed', 'Non, rester abonné', 'لا، أبقِ اشتراكي') }}</a></p>
      @elseif($state === 'done')
        <h1 class="font-display text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white" role="status">{{ tr3('You are unsubscribed', 'Vous êtes désabonné', 'تم إلغاء اشتراكك') }}</h1>
        <p class="mt-4 text-slate-600 dark:text-slate-300"><strong dir="ltr">{{ $email }}</strong> {{ tr3('will no longer receive W5D dated updates.', 'ne recevra plus les mises à jour datées de W5D.', 'لن تصله بعد الآن تحديثات W5D المؤرخة.') }}</p>
        <form method="post" action="{{ $action }}" class="mt-6">
          {!! csrf_field() !!}
          <input type="hidden" name="e" value="{{ $email }}"><input type="hidden" name="s" value="{{ $sig }}">
          <input type="hidden" name="action" value="resubscribe">
          <button type="submit" class="btn-link underline">{{ tr3('Changed your mind? Resubscribe', 'Changé d’avis ? Se réabonner', 'غيّرت رأيك؟ أعد الاشتراك') }}</button>
        </form>
        <p class="mt-4 text-sm"><a class="btn-link" href="{{ url('/') }}">{{ tr3('Back to the home page', 'Retour à l’accueil', 'العودة إلى الرئيسية') }}</a></p>
      @elseif($state === 'resubscribed')
        <h1 class="font-display text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white" role="status">{{ tr3('Welcome back', 'Content de vous revoir', 'مرحبا بعودتك') }}</h1>
        <p class="mt-4 text-slate-600 dark:text-slate-300"><strong dir="ltr">{{ $email }}</strong> {{ tr3('is subscribed to dated updates again.', 'est de nouveau inscrit aux mises à jour datées.', 'عاد مشتركا في التحديثات المؤرخة.') }}</p>
        <p class="mt-6"><a class="btn-peak" href="{{ url('/') }}">{{ tr3('Back to the home page', 'Retour à l’accueil', 'العودة إلى الرئيسية') }}</a></p>
      @elseif($state === 'limited')
        <h1 class="font-display text-2xl font-bold text-slate-900 dark:text-white">{{ tr3('Too many requests', 'Trop de requêtes', 'طلبات كثيرة') }}</h1>
        <p class="mt-4 text-slate-600 dark:text-slate-300">{{ tr3('Try again in a few minutes.', 'Réessayez dans quelques minutes.', 'حاول مجددا بعد دقائق.') }}</p>
      @elseif($state === 'down')
        <h1 class="font-display text-2xl font-bold text-slate-900 dark:text-white">{{ tr3('Temporarily unavailable', 'Momentanément indisponible', 'غير متاح مؤقتا') }}</h1>
        <p class="mt-4 text-slate-600 dark:text-slate-300">{{ tr3('Please try the link again in a moment.', 'Veuillez réessayer le lien dans un instant.', 'يرجى إعادة فتح الرابط بعد قليل.') }}</p>
      @else
        <h1 class="font-display text-2xl font-bold text-slate-900 dark:text-white">{{ tr3('This link is not valid', 'Ce lien n’est pas valide', 'هذا الرابط غير صالح') }}</h1>
        <p class="mt-4 text-slate-600 dark:text-slate-300">{{ tr3('The unsubscribe link is incomplete or was changed. Use the link from the latest e-mail, or write to the contact desk and we will remove you by hand.', 'Le lien de désabonnement est incomplet ou a été modifié. Utilisez le lien du dernier e-mail, ou écrivez au bureau de contact : nous vous retirerons à la main.', 'رابط إلغاء الاشتراك ناقص أو تم تغييره. استخدم الرابط من آخر رسالة، أو اكتب إلى مكتب التواصل وسنزيلك يدويا.') }}</p>
        <p class="mt-6"><a class="btn-peak" href="{{ url('/contact/') }}">{{ __('nav.contact') }}</a></p>
      @endif
    </div>
  </div>
</section>
@endsection

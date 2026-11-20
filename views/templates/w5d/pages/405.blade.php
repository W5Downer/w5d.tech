@extends('layouts.app')
@section('title', '405 — W5D')
@section('robots', 'noindex')
@section('content')
  @widget('hero', ['id' => 'e405', 'variant' => 'editorial', 'gradient' => 'calm', 'eyebrow' => ['en' => '405', 'fr' => '405', 'ar' => '405'], 'title' => ['en' => 'This address does not accept that action. **Try opening it.**', 'fr' => 'Cette adresse n’accepte pas cette action. **Essayez de l’ouvrir.**', 'ar' => 'هذا العنوان لا يقبل هذا الإجراء. **جرّب فتحه مباشرة.**'], 'lead' => ['en' => 'The page exists, but not for the way it was requested. Go back and use the form or link on the site.', 'fr' => 'La page existe, mais pas pour cette façon de la demander. Revenez en arrière et utilisez le formulaire ou le lien du site.', 'ar' => 'الصفحة موجودة لكن ليس بهذه الطريقة في الطلب. ارجع واستخدم النموذج أو الرابط في الموقع.'], 'ctas' => [['label' => ['en' => 'Home', 'fr' => 'Accueil', 'ar' => 'الرئيسية'], 'href' => '/', 'style' => 'focus'], ['label' => ['en' => 'Sitemap', 'fr' => 'Plan du site', 'ar' => 'خريطة الموقع'], 'href' => '/sitemap/', 'style' => 'ghost']]])
@endsection

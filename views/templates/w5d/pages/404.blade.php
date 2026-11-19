@extends('layouts.app')
@section('title', '404 — W5D')
@section('robots', 'noindex')
@section('content')
  @widget('hero', ['id' => 'e404', 'variant' => 'editorial', 'gradient' => 'calm', 'eyebrow' => ['en' => '404', 'fr' => '404', 'ar' => '404'], 'title' => ['en' => 'This path does not exist. **Yet.**', 'fr' => 'Ce chemin n’existe pas. **Pour l’instant.**', 'ar' => 'هذا المسار غير موجود. **حتى الآن.**'], 'lead' => ['en' => 'Check the address, or start from the home page.', 'fr' => 'Vérifiez l’adresse, ou repartez de l’accueil.', 'ar' => 'تحقق من العنوان، أو ابدأ من الصفحة الرئيسية.'], 'ctas' => [['label' => ['en' => 'Home', 'fr' => 'Accueil', 'ar' => 'الرئيسية'], 'href' => '/', 'style' => 'focus'], ['label' => ['en' => 'Sitemap', 'fr' => 'Plan du site', 'ar' => 'خريطة الموقع'], 'href' => '/sitemap/', 'style' => 'ghost']]])
  @widget('path-chooser', ['id' => 'e404-paths', 'tier' => 'i', 'focus' => 2, 'title' => ['en' => 'Try one of these short paths', 'fr' => 'Essayez l’un de ces chemins courts', 'ar' => 'جرّب أحد هذه المسارات القصيرة']])
@endsection

@extends('layouts.app')
@section('title', $title . ' · W5D')
@section('description', tr3('A W5D certificate.', 'Un certificat W5D.', 'شهادة W5D.'))
@section('robots', 'noindex,nofollow')
@section('chromeless', '1')
@section('content')
@php $ok = $rec['status'] === 'valid'; @endphp
<section class="py-10 sm:py-14 cert-sheet-wrap">
  <div class="mx-auto max-w-3xl px-4 sm:px-6">
    <p class="mb-4 flex flex-wrap gap-3 no-print"><button type="button" class="btn-peak" data-print>{{ icon('printer', 'w-4 h-4') }} {{ tr3('Print or save as PDF', 'Imprimer ou enregistrer en PDF', 'اطبع أو احفظ بصيغة PDF') }}</button><button type="button" class="btn-ghost" data-copy-text="{{ $verify }}">{{ icon('link-2', 'w-4 h-4') }} {{ tr3('Copy the verification link', 'Copier le lien de vérification', 'انسخ رابط التحقق') }}</button></p>
    <article class="cert-sheet rounded-3xl border-2 border-violet-500/50 bg-white text-slate-900 p-8 sm:p-12 text-center" data-cert-status="{{ $rec['status'] }}">
      <p class="text-xs tracking-[0.3em] uppercase text-violet-700">W5D · {{ tr3('Certificate', 'Certificat', 'شهادة') }}</p>
      <h1 class="mt-4 font-display text-3xl sm:text-4xl font-bold">{{ $rec['holder'] }}</h1>
      <p class="mt-4 text-slate-600">{{ tr3('passed the QCM round for', 'a réussi la session QCM pour', 'نجح في جولة الاختبار لخدمة') }}</p>
      <p class="mt-2 font-display text-xl sm:text-2xl font-semibold">{{ $rec['service_title'] }}</p>
      <p class="mt-3"><span class="inline-block rounded-full bg-violet-600 text-white px-4 py-1 text-sm font-semibold">L{{ $rec['level'] }} · {{ $rec['level_label'] }}</span>@if($rec['score'] !== null) <span class="ms-2 text-sm text-slate-600">{{ round($rec['score']) }}%</span>@endif</p>
      <p class="mt-6 text-sm text-slate-600 tabular-nums">{{ tr3('Issued', 'Délivré le', 'صدرت في') }} {{ $rec['issued_at'] }}@if($rec['expires_at']) · {{ tr3('valid until', 'valide jusqu’au', 'صالحة حتى') }} {{ $rec['expires_at'] }}@endif</p>
      @if(!$ok)<p class="mt-3 inline-block rounded-lg border border-red-500/50 bg-red-50 px-3 py-1 text-sm font-semibold text-red-700">{{ $rec['status'] === 'revoked' ? tr3('REVOKED', 'RÉVOQUÉ', 'ملغاة') : tr3('EXPIRED', 'EXPIRÉ', 'منتهية') }}</p>@endif
      <p class="mt-8 font-mono text-lg font-semibold" dir="ltr">{{ $rec['code'] }}</p>
      <p class="mt-2 text-xs text-slate-500 break-all" dir="ltr">{{ $verify }}</p>
      <p class="mt-6 text-[11px] text-slate-500">{{ tr3('This certificate proves a passed online round on the date shown. It is not a diploma, not a job offer and not a promise of income. Check it online with the code.', 'Ce certificat atteste d’une session en ligne réussie à la date indiquée. Ce n’est ni un diplôme, ni une offre d’emploi, ni une promesse de revenu. Vérifiez-le en ligne avec le code.', 'تثبت هذه الشهادة نجاحا في جولة عبر الإنترنت في التاريخ المذكور. وهي ليست دبلوما ولا عرض عمل ولا وعدا بدخل. تحقق منها عبر الإنترنت بالرمز.') }}</p>
    </article>
  </div>
</section>
@endsection

@extends('layouts.app')
@section('title', $title . ' · W5D')
@section('description', tr3('A timed multiple-choice round.', 'Une session chronométrée à choix multiples.', 'جولة اختبار محددة الوقت.'))
@section('robots', 'noindex,nofollow')
@section('chromeless', '1')
@section('content')
@php
  $open = $a['status'] === 'open';
  $svc = \W5D\Certificate::serviceTitle((string)$a['service_slug']);
  $pl = match($a['purpose']) { 'practice' => tr3('Practice', 'Entraînement', 'تدريب'), 'certification' => tr3('Certification', 'Certification', 'اعتماد'), default => tr3('Upgrade', 'Montée de niveau', 'ترقية') };
  $total = count($open ? $items : $review);
@endphp
<section class="py-8 sm:py-12">
  <div class="mx-auto max-w-3xl px-4 sm:px-6">
    <p class="eyebrow">{{ $pl }} · L{{ $a['level'] }} · {{ $svc }}</p>
    <h1 class="font-display text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">{{ $meta['rule'] ?? $title }}</h1>

    @if($open)
      <div class="qcm-bar sticky top-16 z-20 mt-4 rounded-2xl border border-slate-200 dark:border-white/10 bg-white/90 dark:bg-[#0b0b18]/90 backdrop-blur p-3 flex flex-wrap items-center gap-3 justify-between" data-qcm-bar>
        <p class="flex items-center gap-2 font-mono text-lg font-semibold" aria-live="off">{{ icon('timer', 'w-5 h-5 text-violet-500') }}<span data-qcm-clock data-left="{{ $left }}" dir="ltr">{{ sprintf('%02d:%02d', intdiv($left, 60), $left % 60) }}</span></p>
        <p class="text-sm text-slate-600 dark:text-slate-300"><span data-qcm-count>0</span> / {{ $total }} {{ tr3('answered', 'répondues', 'تمت الإجابة') }} · {{ tr3('pass at', 'réussite à', 'النجاح عند') }} {{ $meta['pass_pct'] ?? 70 }}%</p>
        <span class="sr-only" role="status" aria-live="polite" data-qcm-status></span>
      </div>
      <div class="mt-3 h-2 rounded-full bg-slate-200 dark:bg-white/10 overflow-hidden" aria-hidden="true"><div class="h-full bg-violet-500 transition-all" style="width:0" data-qcm-progress></div></div>

      <form method="post" action="{{ url('/account/qcm/attempt/' . $a['id'] . '/submit/') }}" class="mt-6" data-qcm data-attempt="{{ $a['id'] }}" data-answer-url="{{ url('/account/qcm/answer/') }}" data-csrf="{{ \W5D\Csrf::token() }}" data-total="{{ $total }}" novalidate>
        {!! csrf_field() !!}
        <ol class="space-y-6">
          @foreach($items as $i => $it)
            @php $mine = $ans[$it['id']] ?? []; $multi = $it['type'] === 'multiple'; @endphp
            <li class="qcm-step rounded-2xl border border-slate-200 dark:border-white/10 bg-white/70 dark:bg-white/5 p-5" data-qid="{{ $it['id'] }}" data-step="{{ $i }}">
              <fieldset>
                <legend class="w-full">
                  <span class="flex items-center justify-between gap-3 text-xs text-slate-500"><span>{{ tr3('Question', 'Question', 'السؤال') }} {{ $i + 1 }} / {{ $total }}</span><span class="tier-badge">{{ $multi ? tr3('Several answers', 'Plusieurs réponses', 'عدة إجابات') : tr3('One answer', 'Une réponse', 'إجابة واحدة') }}</span></span>
                  <span class="mt-2 block font-display text-lg font-semibold text-slate-900 dark:text-white">{{ t($it['q']) }}</span>
                  @if($multi)<span class="mt-1 block text-xs text-slate-500">{{ tr3('Tick every right choice: a partly right answer scores zero.', 'Cochez tous les bons choix : une réponse partielle vaut zéro.', 'حدّد كل الخيارات الصحيحة: الإجابة الجزئية لا تحصل على شيء.') }}</span>@endif
                </legend>
                <div class="mt-4 grid gap-2">
                  @foreach($it['choices'] as $c)
                    <label class="qcm-choice flex items-start gap-3 rounded-xl border border-slate-200 dark:border-white/10 p-3 cursor-pointer hover:bg-slate-50 dark:hover:bg-white/5">
                      <input type="{{ $multi ? 'checkbox' : 'radio' }}" class="mt-1 h-4 w-4" name="a[{{ $it['id'] }}][]" value="{{ $c['id'] }}" @if(in_array($c['id'], $mine, true)) checked @endif>
                      <span class="text-sm sm:text-base">{{ t($c['t']) }}</span>
                    </label>
                  @endforeach
                </div>
              </fieldset>
            </li>
          @endforeach
        </ol>
        <nav class="mt-6 flex flex-wrap items-center justify-between gap-3" aria-label="{{ tr3('Round navigation', 'Navigation de la session', 'التنقل في الجولة') }}">
          <button type="button" class="btn-ghost hidden" data-qcm-prev>{{ tr3('Previous', 'Précédent', 'السابق') }}</button>
          <button type="button" class="btn-ghost hidden" data-qcm-next>{{ tr3('Next', 'Suivant', 'التالي') }}</button>
          <button type="submit" class="btn-peak" data-qcm-submit data-confirm-text="{{ tr3('Some questions are unanswered. Submit anyway?', 'Certaines questions restent sans réponse. Soumettre quand même ?', 'بعض الأسئلة بلا إجابة. هل ترسل رغم ذلك؟') }}">{{ icon('circle-check', 'w-4 h-4') }} {{ tr3('Submit my answers', 'Soumettre mes réponses', 'أرسل إجاباتي') }}</button>
        </nav>
        <p class="mt-4 text-xs text-slate-500">{{ tr3('Your answers are saved as you go. When the timer ends the round is closed and scored with what was saved.', 'Vos réponses sont enregistrées au fil de l’eau. À la fin du chronomètre, la session est close et notée avec ce qui a été enregistré.', 'تُحفظ إجاباتك أثناء التقدم. عند انتهاء المؤقت تُغلق الجولة وتُصحَّح بما تم حفظه.') }}</p>
      </form>
    @else
      @php $pct = (float)$a['score_pct']; $pass = (int)$a['passed'] === 1; @endphp
      <div class="mt-6 rounded-2xl border p-6 text-center {{ $pass ? 'border-emerald-500/40 bg-emerald-500/10' : 'border-amber-500/40 bg-amber-500/10' }}" data-qcm-result="{{ $pass ? 'passed' : 'failed' }}">
        <p class="eyebrow">{{ $pass ? tr3('Passed', 'Réussi', 'ناجح') : tr3('Not passed this time', 'Non réussi cette fois', 'لم تنجح هذه المرة') }}</p>
        <p class="mt-2 font-display text-5xl font-bold tabular-nums">{{ round($pct) }}%</p>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $a['correct'] }} / {{ $a['total'] }} {{ tr3('correct', 'correctes', 'صحيحة') }} · {{ tr3('pass mark', 'seuil', 'حد النجاح') }} {{ $meta['pass_pct'] ?? 70 }}%@if($a['timed_out']) · {{ tr3('time ran out', 'temps écoulé', 'انتهى الوقت') }}@endif</p>
        @if($cert)
          <p class="mt-4 font-mono text-sm" dir="ltr">{{ $cert['code'] }}</p>
          <p class="mt-3 flex flex-wrap justify-center gap-3"><a class="btn-peak" href="{{ url('/certificate/' . $cert['code'] . '/') }}">{{ icon('award', 'w-4 h-4') }} {{ tr3('Open my certificate', 'Ouvrir mon certificat', 'افتح شهادتي') }}</a><a class="btn-ghost" href="{{ url('/account/certificates/') }}">{{ tr3('All my certificates', 'Tous mes certificats', 'كل شهاداتي') }}</a></p>
        @elseif(!$pass)
          <p class="mt-3 text-sm">{{ tr3('A practice round is free and shows the right answers. Try again when the wait on the round allows it.', 'Un entraînement est gratuit et montre les bonnes réponses. Réessayez quand l’attente de la session le permet.', 'التدريب مجاني ويُظهر الإجابات الصحيحة. حاول مجددا عندما يسمح انتظار الجولة.') }}</p>
        @endif
        <p class="mt-4"><a class="btn-ghost" href="{{ url('/account/qcm/') }}">{{ icon('list-checks', 'w-4 h-4') }} {{ tr3('Back to QCM rounds', 'Retour aux sessions QCM', 'العودة إلى جولات الاختبار') }}</a></p>
      </div>

      <h2 class="mt-8 font-display text-lg font-semibold">{{ tr3('Your answers', 'Vos réponses', 'إجاباتك') }}</h2>
      @if($a['purpose'] !== 'practice')<p class="text-xs text-slate-500">{{ tr3('The right answers of graded rounds are not shown, so the question bank stays fair.', 'Les bonnes réponses des sessions notées ne sont pas montrées, pour que la banque reste équitable.', 'لا تُعرض الإجابات الصحيحة في الجولات المصحَّحة حفاظا على عدالة البنك.') }}</p>@endif
      <ol class="mt-4 space-y-4">
        @foreach($review as $r)
          <li class="rounded-2xl border border-slate-200 dark:border-white/10 p-4" data-review="{{ $r['n'] }}" data-ok="{{ $r['ok'] ? '1' : '0' }}">
            <p class="flex items-start gap-2 font-semibold">{{ icon($r['ok'] ? 'circle-check' : 'circle-x', 'w-5 h-5 shrink-0 ' . ($r['ok'] ? 'text-emerald-500' : 'text-red-500')) }}<span>{{ $r['n'] }}. {{ t($r['q']) }}</span></p>
            <ul class="mt-2 space-y-1 text-sm">
              @foreach($r['choices'] as $c)
                <li class="rounded-lg px-3 py-1.5 {{ $c['right'] ? 'bg-emerald-500/15' : ($c['mine'] ? 'bg-red-500/10' : '') }}">{{ $c['mine'] ? '☑' : '☐' }} {{ t($c['t']) }}@if($c['right']) <span class="text-xs text-emerald-700 dark:text-emerald-300">· {{ tr3('right answer', 'bonne réponse', 'الإجابة الصحيحة') }}</span>@endif</li>
              @endforeach
            </ul>
            @if($r['skipped'])<p class="mt-1 text-xs text-slate-500">{{ tr3('Not answered.', 'Sans réponse.', 'بلا إجابة.') }}</p>@endif
            @if($r['explain'])<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ t($r['explain']) }}</p>@endif
          </li>
        @endforeach
      </ol>
    @endif
    <p class="mt-8 text-xs text-slate-500">{{ __('limits.returns') }}</p>
  </div>
</section>
@endsection

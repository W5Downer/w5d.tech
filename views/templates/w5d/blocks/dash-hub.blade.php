@php
  $me = \W5D\Dash::current();
  $aud = \W5D\Controllers\Pages::visibleAudiences();
  $href = fn($k) => url('/for/' . $k . '/');
@endphp
<x-section :d="$d" pad="py-12">
  @if($me !== '')
    <div class="mb-8 rounded-2xl border border-violet-500/40 bg-violet-500/10 p-5 flex flex-wrap items-center gap-4" data-hub-me="{{ $me }}">
      <span class="inline-flex w-12 h-12 items-center justify-center rounded-xl bg-violet-500/20 text-violet-600 dark:text-violet-300">{{ icon(\W5D\Dash::spec($me)['icon'], 'w-6 h-6') }}</span>
      <div class="min-w-0 grow">
        <p class="eyebrow">{{ tr3('You are signed in as', 'Vous êtes connecté en tant que', 'أنت مسجّل بصفة') }} {{ t(\W5D\Dash::spec($me)['label']) }}</p>
        <p class="text-sm text-slate-600 dark:text-slate-300">{{ t(\W5D\Dash::spec($me)['blurb']) }}</p>
      </div>
      <p class="flex flex-wrap gap-2"><a class="btn-peak" href="{{ \W5D\Dash::home($me) }}">{{ tr3('Open my dashboard', 'Ouvrir mon tableau de bord', 'افتح لوحتي') }}</a><a class="btn-ghost" href="{{ \W5D\Dash::api($me) }}">{{ icon('code', 'w-4 h-4') }} {{ tr3('My API docs', 'Ma doc API', 'وثائق API الخاصة بي') }}</a></p>
    </div>
  @endif
  <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3" role="list">
    @foreach($aud as $k)
      @php $sp = \W5D\Dash::spec($k); @endphp
      <li class="rounded-2xl border {{ $me === $k ? 'border-violet-500/60 ring-1 ring-violet-500/30' : 'border-slate-200 dark:border-white/10' }} bg-white/70 dark:bg-white/5 p-5 flex flex-col" data-hub-card="{{ $k }}">
        <span class="inline-flex w-11 h-11 items-center justify-center rounded-xl bg-violet-500/15 text-violet-600 dark:text-violet-300">{{ icon($sp['icon'], 'w-5 h-5') }}</span>
        <h3 class="mt-3 font-display text-lg font-semibold text-slate-900 dark:text-white">{{ t($sp['label']) }}</h3>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300 grow">{{ t($sp['blurb']) }}</p>
        <ul class="mt-3 space-y-1 text-xs text-slate-500">
          @foreach(array_slice($sp['widgets'], 0, 3) as [$wi, $wl])<li class="flex items-center gap-2">{{ icon($wi, 'w-3.5 h-3.5') }} {{ t($wl) }}</li>@endforeach
        </ul>
        <p class="mt-4 flex flex-wrap gap-3 text-sm">
          <a class="btn-link" href="{{ $href($k) }}">{{ tr3('See the door', 'Voir la porte', 'شاهد الباب') }} {{ icon('arrow-right', 'w-4 h-4 rtl:rotate-180') }}</a>
          @if($me === $k)<a class="btn-link" href="{{ \W5D\Dash::home($k) }}">{{ tr3('My dashboard', 'Mon tableau de bord', 'لوحتي') }}</a>@endif
        </p>
      </li>
    @endforeach
  </ul>
  <p class="mt-6 text-sm text-slate-500">{{ tr3('API documentation is private: sign in and open “API & Docs” in your own dashboard.', 'La documentation de l’API est privée : connectez-vous et ouvrez « API et docs » dans votre tableau de bord.', 'وثائق API خاصة: سجّل الدخول وافتح «API والوثائق» في لوحتك.') }} <a class="btn-link" href="{{ url('/account/api/') }}">{{ tr3('Open my docs', 'Ouvrir mes docs', 'افتح وثائقي') }}</a></p>
</x-section>

@php $members = $d['members'] ?? []; $open = $d['open'] ?? []; @endphp
<x-section :d="$d">
  <div class="grid gap-6 lg:grid-cols-3">
    @foreach($members as $m)
      @php $ph = $m['photo'] ?? null; @endphp
      <article data-rvi="up" class="group spot shine lg:col-span-1 rounded-3xl border border-slate-200 dark:border-white/10 bg-white/60 dark:bg-white/5 overflow-hidden flex flex-col" @if(!empty($m['id']))id="{{ $m['id'] }}"@endif>
        @if($ph)
        <img src="{{ asset('images/team/' . $ph . '-360.webp') }}" srcset="{{ asset('images/team/' . $ph . '-360.webp') }} 360w, {{ asset('images/team/' . $ph . '.webp') }} 576w" sizes="(min-width:1024px) 320px, 90vw" width="360" height="640" alt="{{ t($m['alt'] ?? $m['name']) }}" loading="lazy" decoding="async" class="w-full aspect-[4/5] object-cover object-top">
        @endif
        <div class="p-6 flex flex-col gap-2">
          <div class="flex flex-wrap gap-2">@foreach(($m['roles'] ?? []) as $r)<span class="tier-badge">{{ t($r) }}</span>@endforeach</div>
          <h3 class="font-display text-xl font-semibold text-slate-900 dark:text-white">{{ t($m['name']) }}</h3>
          <p class="text-sm text-slate-600 dark:text-slate-400">{{ rich($m['body']) }}</p>
        </div>
      </article>
    @endforeach
    <div class="lg:col-span-2 grid gap-5 sm:grid-cols-2 content-start">
      @foreach($open as $o)
        <div data-rvi="up" class="group spot rounded-2xl border border-dashed border-brand-500/50 bg-white/40 dark:bg-white/5 overflow-hidden flex flex-col">
          <x-card-media :k="'team:' . ($d['id'] ?? '') . ':open:' . $loop->index" :alt="t($o['title'])" />
          <div class="p-5 flex flex-col gap-2 flex-1">
          <span class="inline-flex w-10 h-10 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500">{{ icon($o['icon'] ?? 'user-plus', 'w-5 h-5') }}</span>
          <p class="font-display text-2xl font-bold text-brand-600 dark:text-brand-300">{{ t($o['value'] ?? __('ux.be_first')) }}</p>
          <h3 class="font-display font-semibold text-slate-900 dark:text-white">{{ t($o['title']) }}</h3>
          <p class="text-sm text-slate-600 dark:text-slate-400">{{ rich($o['body']) }}</p>
          @if(!empty($o['href']))<a class="btn-link mt-auto" href="{{ link_to($o['href']) }}">{{ t($o['cta']) }} {{ icon('arrow-right', 'w-4 h-4 rtl:rotate-180') }}</a>@endif
          </div>
        </div>
      @endforeach
    </div>
  </div>
  <x-ctas :list="$d['ctas'] ?? []" />
</x-section>

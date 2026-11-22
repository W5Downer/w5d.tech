@php $cols = (int)($d['cols'] ?? 3); $items = $d['items'] ?? []; @endphp
<x-section :d="$d">
  <div class="grid gap-5 sm:grid-cols-2 {{ $cols >= 4 ? 'lg:grid-cols-4' : ($cols === 3 ? 'lg:grid-cols-3' : '') }}">
    @foreach($items as $c)
      @php $tag = !empty($c['href']) ? 'a' : 'div'; @endphp
      <{{ $tag }} @if(!empty($c['href']))href="{{ link_to($c['href']) }}"@endif data-rvi="up" class="group spot shine relative rounded-2xl border border-slate-200 dark:border-white/10 bg-white/60 dark:bg-white/5 overflow-hidden flex flex-col transition hover:-translate-y-0.5 hover:border-brand-500/60" @if(!empty($c['gradient']))style="--grad: {{ g_macro($c['gradient']) }}"@endif>
        @if(!empty($c['gradient']))<span class="absolute inset-x-0 top-0 h-1 z-10" style="background: var(--grad)"></span>@endif
        <x-card-media :k="'cards:' . ($d['id'] ?? '') . ':' . $loop->index" :alt="t($c['title'])" />
        <div class="p-6 flex flex-col gap-3 flex-1">
        <div class="flex items-center gap-3">
          @if(!empty($c['icon']))<span class="inline-flex w-10 h-10 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500 chip-fx">{{ icon($c['icon'], 'w-5 h-5') }}</span>@endif
          @if(!empty($c['tag']))<span class="tier-badge">{{ t($c['tag']) }}</span>@endif
        </div>
        <h3 class="font-display text-lg font-semibold text-slate-900 dark:text-white">{{ t($c['title']) }}</h3>
        @if(!empty($c['body']))<p class="text-sm text-slate-600 dark:text-slate-400">{{ rich($c['body']) }}</p>@endif
        @if(!empty($c['cta']))<span class="mt-auto inline-flex items-center gap-1 text-sm font-semibold text-brand-600 dark:text-brand-300">{{ t($c['cta']) }} <span class="arrow-fx inline-flex">{{ icon('arrow-right', 'w-4 h-4 rtl:rotate-180') }}</span></span>@endif
        </div>
      </{{ $tag }}>
    @endforeach
  </div>
  <x-ctas :list="$d['ctas'] ?? []" />
</x-section>

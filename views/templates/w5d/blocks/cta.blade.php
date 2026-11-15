@php $g = $d['gradient'] ?? 'peak'; $gv = in_array($g, ['cultures','built','sciences','governance','technology']) ? g_macro($g) : 'var(--g-' . $g . ')'; @endphp
<section @if(!empty($d['id']))id="{{ $d['id'] }}"@endif class="tier-e focus-5 py-16 sm:py-20" data-tier="e" data-focus="5">
  <div class="mx-auto max-w-5xl px-4 sm:px-6">
    <div class="on-grad rounded-3xl px-6 py-12 sm:px-12 text-center overflow-hidden" style="--grad: {{ $gv }}">
      <h2 class="font-display text-3xl sm:text-4xl font-bold tracking-tight">{{ hl($d['title'] ?? '') }}</h2>
      @if(!empty($d['body']))<p class="mt-4 text-lg text-white/90 max-w-2xl mx-auto">{{ rich($d['body']) }}</p>@endif
      <div class="mt-8 flex flex-wrap justify-center gap-3">
        @foreach(($d['ctas'] ?? []) as $c)<x-cta :c="$c" />@endforeach
      </div>
      @if(!empty($d['note']))<p class="mt-5 text-xs text-white/85">{{ rich($d['note']) }}</p>@endif
    </div>
  </div>
</section>

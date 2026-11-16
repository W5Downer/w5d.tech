@props(['c' => [], 'class' => ''])
@php
  $style = $c['style'] ?? 'focus';
  $href = $c['href'] ?? '#';
@endphp
<a @if(!empty($c['id']))id="{{ $c['id'] }}"@endif href="{{ link_to($href) }}" class="btn-{{ $style }} {{ $class }}">
  @if(!empty($c['icon'])){{ icon($c['icon'], 'w-5 h-5') }}@endif
  <span>{{ t($c['label'] ?? '') }}</span>
  @if(!empty($c['arrow'])){{ icon('arrow-right', 'w-4 h-4 rtl:rotate-180') }}@endif
</a>

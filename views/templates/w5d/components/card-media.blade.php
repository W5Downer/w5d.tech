@props(['k' => '', 'alt' => '', 'class' => 'aspect-[16/9]', 'sizes' => '(min-width:1024px) 400px, (min-width:640px) 45vw, 92vw'])
@php $imgs = card_images($k); $n = count($imgs); @endphp
@if($n === 1)
<img src="{{ asset('images/cards/img-' . sprintf('%04d', $imgs[0]['index']) . '.jpg') }}" width="640" height="360" alt="{{ $alt }}" loading="lazy" decoding="async" data-img-index="{{ $imgs[0]['index'] }}" class="card-media w-full object-cover {{ $class }}">
@elseif($n > 1)
<div class="card-media card-carousel relative overflow-hidden {{ $class }}" data-card-carousel role="group" aria-roledescription="carousel" aria-label="{{ $alt }}">
  @foreach($imgs as $i => $im)
  <img src="{{ asset('images/cards/img-' . sprintf('%04d', $im['index']) . '.jpg') }}" width="640" height="360" alt="{{ $i === 0 ? $alt : '' }}" @if($i > 0)aria-hidden="true"@endif loading="lazy" decoding="async" data-img-index="{{ $im['index'] }}" data-cslide class="absolute inset-0 w-full h-full object-cover {{ $i === 0 ? 'is-active' : '' }}">
  @endforeach
  <button type="button" data-cprev class="card-car-btn start-2" aria-label="{{ __('cta.back') }}">{{ icon('chevron-left', 'w-4 h-4 rtl:rotate-180') }}</button>
  <button type="button" data-cnext class="card-car-btn end-2" aria-label="{{ __('cta.next') }}">{{ icon('chevron-right', 'w-4 h-4 rtl:rotate-180') }}</button>
  <span class="card-car-dots" aria-hidden="true">@foreach($imgs as $i => $im)<i data-cdot @if($i === 0)class="is-active"@endif></i>@endforeach</span>
</div>
@endif

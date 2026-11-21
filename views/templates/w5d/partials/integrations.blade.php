@php $__int = \W5D\Integrations::pageConfig(); @endphp
@if($__int)
  @if(!empty($__int['track']))
  <div class="w5-consent" data-consent hidden role="region" aria-label="{{ __('consent.title') }}">
    <p class="font-semibold text-sm">{{ __('consent.title') }}</p>
    <p class="text-xs mt-1">{{ rich(__('consent.body')) }}</p>
    <div class="mt-3 flex flex-wrap gap-2">
      <button type="button" class="btn-focus text-sm" data-consent-accept>{{ __('consent.accept') }}</button>
      <button type="button" class="btn-ghost text-sm" data-consent-refuse>{{ __('consent.refuse') }}</button>
    </div>
  </div>
  @endif
  <script src="{{ asset('js/integrations.js') }}" data-w5d-int="{{ json_encode($__int, JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}" defer></script>
@endif

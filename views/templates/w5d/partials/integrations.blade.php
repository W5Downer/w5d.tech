@php $__int = \W5D\Integrations::pageConfig(); @endphp
@if($__int)
  <script src="{{ asset('js/integrations.js') }}" data-w5d-int="{{ json_encode($__int, JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}" defer></script>
@endif

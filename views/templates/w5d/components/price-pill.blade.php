@php $pr = \W5D\Variables::get('price'); @endphp
<span class="inline-flex items-center gap-2 rounded-full border border-brand-500/30 bg-brand-500/10 px-3 py-1 text-sm font-medium text-brand-700 dark:text-brand-200">
  {{ icon('trending-up', 'w-4 h-4') }}
  <span>{{ __('price.today') }}: <strong>{{ money((float)($pr['day'] ?? 0)) }}</strong> / {{ __('price.per_day') }}</span>
</span>

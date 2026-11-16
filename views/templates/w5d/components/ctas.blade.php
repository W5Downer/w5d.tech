@props(['list' => [], 'class' => 'mt-8'])
@if(!empty($list))
<div class="flex flex-wrap items-center gap-3 {{ $class }}">
  @foreach($list as $c)<x-cta :c="$c" />@endforeach
</div>
@endif

@props(['name', 'label' => '', 'type' => 'text', 'required' => false, 'options' => [], 'value' => '', 'hint' => '', 'errors' => [], 'rows' => 4, 'min' => null, 'max' => null, 'placeholder' => '', 'class' => '', 'readonly' => false])
@php $err = $errors[$name] ?? null; $val = old($name, (string)$value); $id = 'f-' . $name; @endphp
<div class="{{ $class }} {{ $err ? 'has-error' : '' }}">
  @if($type === 'checkbox')
    <label class="flex items-start gap-3 text-sm" for="{{ $id }}">
      <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="1" class="mt-1" @if($val) checked @endif @if($required) required @endif>
      <span>{{ rich($label) }}</span>
    </label>
  @else
    <label for="{{ $id }}" class="block text-sm font-medium mb-1">{{ t($label) }}@if($required) <span aria-hidden="true">*</span>@endif</label>
    @if($type === 'select')
      <select class="field" id="{{ $id }}" name="{{ $name }}" @if($required) required @endif>
        @foreach($options as $ov => $ol)<option value="{{ $ov }}" @if((string)$val === (string)$ov) selected @endif>{{ t($ol) }}</option>@endforeach
      </select>
    @elseif($type === 'textarea')
      <textarea class="field" id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @if($required) required @endif placeholder="{{ t($placeholder) }}">{{ $val }}</textarea>
    @else
      <input class="field @if($readonly) opacity-70 @endif" id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $val }}" @if($readonly) readonly aria-readonly="true" @endif @if($required) required @endif @if($min !== null) min="{{ $min }}" @endif @if($max !== null) max="{{ $max }}" @endif placeholder="{{ t($placeholder) }}">
    @endif
  @endif
  @if($hint)<p class="text-xs mt-1 text-slate-500 dark:text-slate-400">{{ t($hint) }}</p>@endif
  @if($err)<p class="text-xs mt-1 text-red-500" role="alert">{{ t($err) }}</p>@endif
</div>

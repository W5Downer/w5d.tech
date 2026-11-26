@props(['name', 'label' => '', 'type' => 'text', 'required' => false, 'options' => [], 'value' => '', 'hint' => '', 'errors' => [], 'rows' => 4, 'min' => null, 'max' => null, 'placeholder' => '', 'class' => '', 'readonly' => false, 'autocomplete' => null, 'chips' => false, 'stepper' => null, 'format' => null, 'phoneDefault' => 'TN'])
@php $err = $errors[$name] ?? null; $val = old($name, (string)$value); $id = 'f-' . $name;
  $acMap = ['email' => 'email', 'name' => 'name', 'full_name' => 'name', 'phone' => 'tel', 'payout_gsm' => 'off', 'sender_gsm' => 'tel', 'organisation' => 'organization', 'username' => 'username', 'identifier' => 'username',
            'txn_ref' => 'off', 'key' => 'off', 'ref' => 'off', 'current' => 'current-password'];
  $ac = $autocomplete ?? ($acMap[$name] ?? ($type === 'email' ? 'email' : ($type === 'tel' ? 'tel' : null)));
@endphp
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
      <input class="field @if($readonly) opacity-70 @endif" id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" @if($ac) autocomplete="{{ $ac }}" @endif @if($type === 'email') inputmode="email" autocapitalize="off" spellcheck="false" @elseif($type === 'tel') inputmode="tel" data-phone data-phone-default="{{ $phoneDefault }}" @elseif($type === 'number') inputmode="numeric" @if($stepper ?? ($min !== null && $max !== null)) data-stepper @endif @elseif(in_array($name, ['full_name', 'name', 'organisation'], true)) autocapitalize="words" @endif @if($chips) data-date-chips @endif @if($format) data-format="{{ $format }}" @endif value="{{ $val }}" @if($readonly) readonly aria-readonly="true" @endif @if($required) required @endif @if($min !== null) min="{{ $min }}" @endif @if($max !== null) max="{{ $max }}" @endif placeholder="{{ t($placeholder) }}">
    @endif
  @endif
  @if($hint)<p class="text-xs mt-1 text-slate-600 dark:text-slate-400">{{ t($hint) }}</p>@endif
  @if($err)<p class="text-xs mt-1 text-red-500" role="alert">{{ t($err) }}</p>@endif
</div>

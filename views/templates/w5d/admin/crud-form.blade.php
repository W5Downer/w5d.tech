@extends('admin.layout')
@section('title', t($menu['label']))
@section('content')
@php
  $editing = !empty($row);
  $lang = lang();
@endphp
<header class="mb-6">
  <p class="eyebrow">{{ t($menu['label']) }}</p>
  <div class="flex items-center gap-3"><h1 class="font-display text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white min-w-0">{{ $editing ? __('ux.crud_edit_title') . ' #' . $row['id'] : __('ux.crud_new_title') }}</h1>{{ help_btn('admin.crud-form', t($menu['label']), 'start', 'page') }}</div>
  <p class="text-sm text-slate-500 mt-1">{{ t($def['title']) }}</p>
</header>
<form method="post" action="{{ admin_url($slug . '/save') }}" class="max-w-2xl rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 p-5 sm:p-8 space-y-4" novalidate>
  {!! csrf_field() !!}
  @if($editing)<input type="hidden" name="id" value="{{ $row['id'] }}">@endif
  @foreach($def['fields'] as $name => $f)
    @php
      $type = $f[0]; $label = t($f[1]); $id = 'cf-' . $name;
      $val = old($name, (string)($row[$name] ?? ($f['default'] ?? '')));
      $err = $errors[$name] ?? null;
      $req = !empty($f['req']);
      $locked = !empty($f['scope_lock']) && (\W5D\Crud::scopeCol($role, $def) === $name);
    @endphp
    <div class="{{ $err ? 'has-error' : '' }}">
      @if($type === 'bool')
        <label class="flex items-center gap-3 text-sm" for="{{ $id }}"><input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="1" @if($val === '1') checked @endif><span>{{ $label }}</span></label>
      @elseif($type === 'ro')
        <p class="block text-sm font-medium mb-1">{{ $label }}</p>
        <p class="field opacity-70" aria-readonly="true">{{ $editing ? ($row[$name] ?? '') : '—' }}</p>
      @else
        <label for="{{ $id }}" class="block text-sm font-medium mb-1">{{ $label }}@if($req) <span aria-hidden="true">*</span>@endif</label>
        @if($locked)
          <input class="field opacity-70" id="{{ $id }}" type="text" value="{{ $user['id'] }}" readonly aria-readonly="true">
          <p class="text-xs mt-1 text-slate-500">{{ __('ux.crud_scope') }}</p>
        @elseif($type === 'select')
          <select class="field" id="{{ $id }}" name="{{ $name }}" @if($req) required @endif>
            @foreach(\W5D\Crud::options($f) as $ov => $ol)<option value="{{ $ov }}" @if((string)$val === (string)$ov) selected @endif>{{ $ol }}</option>@endforeach
          </select>
        @elseif($type === 'textarea')
          <textarea class="field" id="{{ $id }}" name="{{ $name }}" rows="5" @if(isset($f['max'])) maxlength="{{ $f['max'] }}" @endif @if($req) required @endif>{{ $val }}</textarea>
        @else
          <input class="field" id="{{ $id }}" name="{{ $name }}" type="{{ $type === 'number' ? 'number' : ($type === 'email' ? 'email' : ($type === 'date' ? 'date' : 'text')) }}" value="{{ $val }}" @if($type === 'number') inputmode="decimal" step="{{ $f['step'] ?? 1 }}" @if(isset($f['min'])) min="{{ $f['min'] }}" @endif @if(isset($f['maxv'])) max="{{ $f['maxv'] }}" @endif @endif @if(isset($f['max'])) maxlength="{{ $f['max'] }}" @endif @if($req) required @endif @if(in_array($type, ['email', 'number', 'date'], true) || $name === 'utm') dir="ltr" @endif>
        @endif
      @endif
      @if($err)<p class="text-xs mt-1 text-red-500" role="alert">{{ $err }}</p>@endif
    </div>
  @endforeach
  <div class="flex flex-wrap items-center gap-3 pt-2">
    <button class="btn-peak" type="submit">{{ __('ux.crud_save') }}</button>
    <a class="btn-ghost" href="{{ admin_url($slug . '/') }}">{{ __('ux.crud_cancel') }}</a>
  </div>
</form>
@endsection

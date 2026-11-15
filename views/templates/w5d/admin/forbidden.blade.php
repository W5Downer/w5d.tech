@extends('admin.layout')
@section('title', 'Forbidden')
@section('content')
<div class="max-w-xl mx-auto mt-24 rounded-2xl border border-amber-500/40 bg-amber-500/10 p-8">
  <h1 class="font-display text-2xl font-bold">403</h1><p class="mt-2">{{ __('admin.forbidden') }}</p>
  <a class="btn-link mt-4" href="{{ admin_url() }}">{{ __('cta.back_home') }}</a>
</div>
@endsection

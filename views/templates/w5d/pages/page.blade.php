@extends('layouts.app')
@section('title', $title)
@section('description', $description ?? '')
@section('robots', $robots ?? 'index,follow')
@section('content')
  @foreach($sections as $s)
    @widget($s['type'], $s)
  @endforeach
@endsection

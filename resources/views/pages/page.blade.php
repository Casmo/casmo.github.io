@extends('default')

@php($terminal = \App\Support\Terminal::forEntry($page))

@section('terminal-header')
  <x-terminal.header
    :path="$terminal['path']"
    :file="$terminal['file']"
  />
@endsection

@section('body')
  @content($content)
@endsection

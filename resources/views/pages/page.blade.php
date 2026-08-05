@extends('default')

@section('terminal-header')
  {{-- A page is its own directory, so the path comes from its title: About -> ~/about.
       The filename is usually the slug, but these pages have hand-picked ones
       (introduction.txt, useful-stuff.txt) set via terminal_file. --}}
  <x-terminal.header
    :path="\App\Support\Terminal::path($terminal_path ?? null, [$title])"
    :file="\App\Support\Terminal::file($terminal_file ?? null, $slug)"
  />
@endsection

@section('body')
  @content($content)
@endsection

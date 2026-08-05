@extends('default')

@php($category = $categories->first())

@section('terminal-header')
  <x-terminal.header
    :path="\App\Support\Terminal::path($terminal_path ?? null, ['blog', $category?->slug])"
    :file="\App\Support\Terminal::file($terminal_file ?? null, $slug)"
    :user="$author->name ?? null"
    :rows="[
      'Title' => $title,
      'Date' => $date->format('F j, Y'),
      'Tags' => $categories->pluck('title')->join(', '),
    ]"
  />
@endsection

@section('body')
  <div>
    @content($content)
  </div>

  <statamic:collection:next in="blog" as="posts" limit="2" sort="date:asc">

    <x-terminal.section label="up next">
      @foreach ($posts as $post)
        <div class="post">
          <a href="{{ $post->url }}">{{ $post->title }}</a>
        </div>
      @endforeach
    </x-terminal.section>

  </statamic:collection:next>
@endsection

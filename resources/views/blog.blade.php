@extends('default')

@section('body')

  <p>
    <strong>{{ $title }}:$</strong><br />
    <span class="text-lime-500">&gt; Date:</span> {{ $date->format('F j, Y') }}<br />
    <span class="text-lime-500">&gt; Tags:</span> {{ $categories->pluck('title')->join(', ') }}<br />
  </p>
  <div>
    {!! $content !!}
  </div>

  <statamic:collection:next in="blog" as="posts" limit="2" sort="date:asc">
    <!-- @if ($no_results)
      <a href="/blog">/Blog</a>
    @endif -->

    @foreach ($posts as $post)
      <div class="post">
        <a href="{{ $post->url }}">{{ $post->title }}</a>
      </div>
    @endforeach

  </statamic:collection:next>
@endsection
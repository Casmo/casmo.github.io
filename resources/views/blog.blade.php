@extends('default')

@section('body')
    <p>
      <strong{{ strtolower($author->name) }}@laptop:{{ $categories->first()?->title }}$</strong> cat <strong>{{ $slug }}.txt</strong><br />
      <span class="text-lime-500">&gt; Title:</span> {{ $title }}</span><br />
      <span class="text-lime-500">&gt; Date:</span> {{ $date->format('F j, Y') }}</span><br />
      <span class="text-lime-500">&gt; Tags:</span> {{ $categories->pluck('title')->join(', ') }}<br />
    </p>
  <div>
    {!! $content !!}
  </div>

  <statamic:collection:next in="blog" as="posts" limit="2" sort="date:asc">

    @foreach ($posts as $post)
      <div class="post">
        <a href="{{ $post->url }}">{{ $post->title }}</a>
      </div>
    @endforeach

  </statamic:collection:next>
@endsection
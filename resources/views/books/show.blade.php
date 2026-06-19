@extends('default')

@php
    $val = fn ($v) => ($v instanceof \Statamic\Fields\Value) ? $v->value() : $v;

    $author_ = $val($author);
    $link_   = $val($link);

    $bookCover = $val($cover);
    $bookCover = is_iterable($bookCover) ? collect($bookCover)->first() : $bookCover;
@endphp

@section('body')
  <p>
    <strong>mathieu@laptop:~/books$</strong> cat <strong>{{ $slug }}.txt</strong><br />
    <span class="text-lime-500">&gt; Book:</span> {{ $title }}<br />
    @if($author_)
      <span class="text-lime-500">&gt; Author:</span> {{ $author_ }}<br />
    @endif
    @if($link_)
      <span class="text-lime-500">&gt; Link:</span> <a href="{{ $link_ }}" class="text-lime-500 hover:text-lime-400">{{ $link_ }}</a><br />
    @endif
  </p>

  @if($bookCover)
    <div class="my-6">
      <img src="{{ $bookCover->url() }}" alt="{{ $title }}" class="max-w-xs rounded border border-zinc-700" />
    </div>
  @endif

  <div class="my-8">
    <p class="text-lime-500">&gt; Reviews influenced by this book:</p>
    <s:collection:games taxonomy:books="{{ $slug }}" sort="date:desc" as="reviews">
      @forelse($reviews as $review)
        <div class="post">
          <a href="{{ $review->url }}">{{ $review->name ?? $review->title }}</a>
        </div>
      @empty
        <p>No reviews yet.</p>
      @endforelse
    </s:collection:games>
  </div>
@endsection

@extends('default')

@php
    $val = fn ($v) => ($v instanceof \Statamic\Fields\Value) ? $v->value() : $v;

    $author_ = $val($author);
    $link_   = $val($link);

    $bookCover = $val($cover);
    $bookCover = is_iterable($bookCover) ? collect($bookCover)->first() : $bookCover;
@endphp

@section('terminal-header')
  {{-- A taxonomy term, so the path is stated rather than derived from a collection. --}}
  <x-terminal.header
    path="~/books"
    :file="\App\Support\Terminal::file(null, $slug)"
    :rows="[
      'Book' => $title,
      'Author' => $author_,
      'Link' => $link_ ? ['url' => $link_] : null,
    ]"
  />
@endsection

@section('body')
  @if($bookCover)
    <div class="my-6">
      <img src="{{ $bookCover->url() }}" alt="{{ $title }}" class="max-w-xs" style="border: 1px solid var(--rule);" />
    </div>
  @endif

  <x-terminal.section label="reviews influenced by this book">
    <s:collection:games taxonomy:books="{{ $slug }}" sort="date:desc" as="reviews">
      @forelse($reviews as $review)
        <div class="post">
          <a href="{{ $review->url }}">{{ $review->name ?? $review->title }}</a>
        </div>
      @empty
        <p>No reviews yet.</p>
      @endforelse
    </s:collection:games>
  </x-terminal.section>
@endsection

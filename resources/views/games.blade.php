@extends('default')

@php
    $val = fn ($v) => ($v instanceof \Statamic\Fields\Value) ? $v->value() : $v;

    $gameName   = $val($name) ?? $val($title);
    $dev        = $val($developer);
    $rel        = $val($release_date);
    $platforms_ = collect($val($platforms) ?? [])->map(function ($p) {
        if (is_array($p))  return $p['label'] ?? $p['value'] ?? null;
        if (is_object($p)) return $p->label ?? $p->value ?? (string) $p;
        return $p;
    })->filter();
    $play       = $val($playtime);
    $rate       = $val($rating);
    $verdict_   = $val($verdict);
    $takeaway   = $val($designers_takeaway);
    $steal      = $val($what_id_steal);
    $store      = $val($store_url);
    $inf        = $val($books);
    $bookList   = collect(is_object($inf) && method_exists($inf, 'get') ? $inf->get() : ($inf ?? []));

    $cover = $val($capsule);
    $cover = is_iterable($cover) ? collect($cover)->first() : $cover;

    $terminal = \App\Support\Terminal::forEntry($page);
@endphp

@section('terminal-header')
  <x-terminal.header
    :path="$terminal['path']"
    :file="$terminal['file']"
    :rows="[
      'Game' => $gameName,
      'Developer' => $dev,
      'Released' => $rel?->format('F j, Y'),
      'Platforms' => $platforms_->join(', '),
      'Playtime' => $play,
      'Rating' => $rate ? $rate.'/10' : null,
      'Reviewed' => $date->format('F j, Y'),
    ]"
  />
@endsection

@section('body')
  @if($cover)
    <img src="{{ $cover->url() }}" alt="{{ $gameName }}" class="max-w-full" />
  @endif

  {{-- Verdict is one line, but it is prose rather than data, so it takes a
       section marker instead of a leader row. --}}
  @if($verdict_)
    <x-terminal.section label="verdict">
      <p>{{ $verdict_ }}</p>
    </x-terminal.section>
  @endif

  <div>
    @content($content)
  </div>

  @if($takeaway)
    <x-terminal.section label="designer's takeaway">
        <p>{{ $takeaway }}</p>
    </x-terminal.section>
  @endif

  @if($steal)
    <x-terminal.section label="what i'd steal">
      {!! $steal !!}
    </x-terminal.section>
  @endif

  @if($bookList->isNotEmpty())
    <x-terminal.section label="influenced by">
      <ul class="list-disc pl-6">
        @foreach($bookList as $book)
          @php($bookAuthor = $val($book->author))
          @php($link = $val($book->link))
          <li>
            <a href="{{ $book->url() }}">{{ $book->title() }}</a>
            @if($bookAuthor)<span> — {{ $bookAuthor }}</span>@endif
            @if($link)<span> (<a href="{{ $link }}">info</a>)</span>@endif
          </li>
        @endforeach
      </ul>
    </x-terminal.section>
  @endif

  @if($store)
    <p>
      <a href="{{ $store }}">Store page</a>
    </p>
  @endif

  {{-- The tag pair still renders on the last review, so the marker is gated on
       the list rather than on the tag: no follow-up, no "// up next". --}}
  <statamic:collection:next in="games" as="entries" limit="2" sort="date:desc">

    @if (filled($entries))
      <x-terminal.section label="up next">
        @foreach ($entries as $entry)
          <div class="post">
            <a href="{{ $entry->url }}">{{ $entry->name ?? $entry->title }}</a>
          </div>
        @endforeach
      </x-terminal.section>
    @endif

  </statamic:collection:next>
@endsection

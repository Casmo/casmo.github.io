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
@endphp

@section('body')
  <p>
    <strong>mathieu@laptop:~/reviews$</strong> cat <strong>{{ $slug }}.review</strong><br />

  @if($cover)
      <img src="{{ $cover->url() }}" alt="{{ $gameName }}" class="max-w-full" />
  @endif
    <span class="text-lime-500">&gt; Game:</span> {{ $gameName }}<br />
    @if($dev)
      <span class="text-lime-500">&gt; Developer:</span> {{ $dev }}<br />
    @endif
    @if($rel)
      <span class="text-lime-500">&gt; Released:</span> {{ $rel->format('F j, Y') }}<br />
    @endif
    @if($platforms_->isNotEmpty())
      <span class="text-lime-500">&gt; Platforms:</span> {{ $platforms_->join(', ') }}<br />
    @endif
    @if($play)
      <span class="text-lime-500">&gt; Playtime:</span> {{ $play }}<br />
    @endif
    @if($rate)
      <span class="text-lime-500">&gt; Rating:</span> {{ $rate }}/10<br />
    @endif
    <span class="text-lime-500">&gt; Reviewed:</span> {{ $date->format('F j, Y') }}<br />
  </p>

  @if($verdict_)
    <p class="my-4"><span class="text-lime-500">&gt; Verdict:</span> {{ $verdict_ }}</p>
  @endif

  <div>
    {!! $content !!}
  </div>

  @if($takeaway)
    <blockquote class="border-l-2 border-lime-500 pl-4 my-6 italic">
      <p><span class="text-lime-500">// designer's takeaway</span><br />
      {{ $takeaway }}
      </p>
    </blockquote>
  @endif

  @if($steal)
      <p>
        <span class="text-lime-500">&gt; What I'd steal:<br />
        {!! $steal !!}
      </p>
  @endif

  @if($bookList->isNotEmpty())
    <div class="my-6">
      <p class="text-lime-500">&gt; Influenced by:</p>
      <ul class="list-disc pl-6">
        @foreach($bookList as $book)
          @php($author = $val($book->author))
          @php($link = $val($book->link))
          <li>
            <a href="{{ $book->url() }}" class="text-lime-500 hover:text-lime-400">{{ $book->title() }}</a>
            @if($author)<span> — {{ $author }}</span>@endif
            @if($link)<span> (<a href="{{ $link }}" class="underline">info</a>)</span>@endif
          </li>
        @endforeach
      </ul>
    </div>
  @endif

  @if($store)
    <p class="my-4">
      <a href="{{ $store }}">Store page</a>
    </p>
  @endif

  <statamic:collection:next in="games" as="entries" limit="2" sort="date:desc">

    @foreach ($entries as $entry)
      <div class="post">
        <a href="{{ $entry->url }}">{{ $entry->name ?? $entry->title }}</a>
      </div>
    @endforeach

  </statamic:collection:next>
@endsection

@extends('default')

@section('body')
  @content($content)

  <div class="my-8">
    <s:collection:games limit="10" sort="date:desc" paginate="true" as="reviews">

      @foreach($reviews as $review)
        @php($cover = is_iterable($review->capsule ?? null) ? collect($review->capsule)->first() : ($review->capsule ?? null))
        <div class="mb-8">
          @if($cover)
            <a href="{{ $review->url }}" class="shrink-0">
              <img src="{{ $cover->url() }}" alt="{{ $review->name }}" />
            </a>
          @endif
          <p>
            <span class="text-lime-500">&gt; Game:</span> {{ $review->name ?? $review->title }}<br />
            @if($review->rating)<span class="text-lime-500">&gt; Rating:</span> {{ $review->rating }}/10<br />@endif
            <span class="text-lime-500">&gt; Date:</span> {{ $review->date->format('F j, Y') }}<br />
            @if($review->verdict)<span class="text-lime-500">&gt; Verdict:</span> {{ $review->verdict }}<br />@endif
            <a href="{{ $review->url }}">Read review</a>
          </p>
        </div>
      @endforeach

      @if($paginate)
        <div class="pagination">
          @if($paginate['prev_page'])
            <a href="{{ $paginate['prev_page'] }}">Previous</a>
          @endif

          @if($paginate['next_page'])
            <a href="{{ $paginate['next_page'] }}">Next</a>
          @endif
        </div>
      @endif

    </s:collection:games>
  </div>
@endsection

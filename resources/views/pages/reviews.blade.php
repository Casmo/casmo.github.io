@extends('default')

@section('terminal-header')
  <x-terminal.header
    :path="\App\Support\Terminal::path($terminal_path ?? null, [$title])"
    :file="\App\Support\Terminal::file($terminal_file ?? null, $slug)"
  />
@endsection

@section('body')
  @content($content)

  {{-- No prompt of its own: the listing belongs to the `cat` in the panel above,
       and a fourth prompt per page would tip the session into a gag. --}}
  <div>
    <s:collection:games limit="10" sort="date:desc" paginate="true" as="reviews">

      @foreach($reviews as $review)
        @php($cover = is_iterable($review->capsule ?? null) ? collect($review->capsule)->first() : ($review->capsule ?? null))
        <div class="entry">
          @if($cover)
            <a href="{{ $review->url }}">
              <img src="{{ $cover->url() }}" alt="{{ $review->name }}" />
            </a>
          @endif

          <x-terminal.readout :rows="[
            'Game' => $review->name ?? $review->title,
            'Rating' => $review->rating ? $review->rating.'/10' : null,
            'Date' => $review->date->format('F j, Y'),
          ]" />

          @if($review->verdict)
            <p>{{ $review->verdict }}</p>
          @endif

          <p><a href="{{ $review->url }}">Read review</a></p>
        </div>
      @endforeach

      @if($paginate)
        <div class="pagination">
          @if($paginate['prev_page'])
            <a class="pagination__prev" href="{{ $paginate['prev_page'] }}">Previous</a>
          @endif

          @if($paginate['next_page'])
            <a class="pagination__next" href="{{ $paginate['next_page'] }}">Next</a>
          @endif
        </div>
      @endif

    </s:collection:games>
  </div>
@endsection

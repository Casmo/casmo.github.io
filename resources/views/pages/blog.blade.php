@extends('default')

@php($terminal = \App\Support\Terminal::forEntry($page))

@section('terminal-header')
  <x-terminal.header
    :path="$terminal['path']"
    :file="$terminal['file']"
  />
@endsection

@section('body')
  @content($content)

  {{-- No prompt of its own: the listing belongs to the `cat` in the panel above,
       and a fourth prompt per page would tip the session into a gag. --}}
  <div>
    <s:collection:blog limit="3" sort="date:desc" paginate="true" as="posts">

      @foreach($posts as $post)
        <div class="entry">
          <x-terminal.readout :rows="[
            'Title' => $post->title,
            'Date' => $post->date->format('F j, Y'),
          ]" />

          <p>{{ Str::limit(strip_tags(\App\Support\Palette::render((string) $post->content)), 150) }}</p>

          <p><a href="{{ $post->url }}">Read more</a></p>
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
  </s:collection:blog>
</div>
@endsection

@extends('default')

@section('body')
  {!! $content !!}

  <div class="my-8">
    <s:collection:blog limit="3" sort="date:desc" paginate="true" as="posts">

      @foreach($posts as $post)
        <div class="mb-8">
          <p>
            <span class="text-lime-500">&gt; Title:</span> {{ $post->title }}</span><br />
            <span class="text-lime-500">&gt; Date:</span> {{ $post->date->format('F j, Y') }}</span><br />
            <span class="text-lime-500">&gt; Excerpt:</span> {{ Str::limit(strip_tags($post->content), 150) }}</span><br />
            <a href="{{ $post->url }}">Read more</a>
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
  </s:collection:blog>
</div>
@endsection
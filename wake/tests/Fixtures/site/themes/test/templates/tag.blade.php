<!doctype html><html><head>@wakeHead</head><body><h1>Tag: {{ $tag->title() }}</h1>
@foreach ($tag->posts() as $post)<a class="post" href="{{ $post->url() }}">{{ $post->title() }}</a>
@endforeach
</body></html>

<!doctype html><html lang="{{ $site->language() }}"><head>@wakeHead</head><body>
<h1>Home: {{ $entry?->title() ?? $site->title() }}</h1>
@foreach ($site->posts() as $post)<a class="post" href="{{ $post->url() }}">{{ $post->title() }}</a>
@endforeach
</body></html>

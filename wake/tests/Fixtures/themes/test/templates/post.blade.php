<!doctype html><html lang="{{ $post->language() }}"><head>@wakeHead</head><body>
<h1>Post: {{ $post->title() }}</h1>
@if ($post->isFallback())<p class="fallback">fallback</p>@endif
{{ $post->content() }}
</body></html>

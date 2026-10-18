<!doctype html>
<html lang="{{ $site->language() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $category->title() }} · {{ $site->title() }}</title></head>
<body>
    <h1>{{ $category->title() }}</h1>
    @foreach ($category->posts() as $post)<p><a href="{{ $post->url() }}">{{ $post->title() }}</a></p>@endforeach
</body>
</html>

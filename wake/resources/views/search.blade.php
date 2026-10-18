<!doctype html>
<html lang="{{ $site->language() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>{{ $query }} · {{ $site->title() }}</title>
</head>
<body>
    <form><input type="search" name="q" value="{{ $query }}"></form>
    @foreach ($results as $result)<p><a href="{{ $result->url() }}">{{ $result->title() }}</a></p>@endforeach
</body>
</html>

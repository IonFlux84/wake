<!doctype html>
<html lang="{{ $site->language() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 · {{ $site->title() }}</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <main>
        <h1>404</h1>
        <p><a href="{{ $site->url() }}">{{ $site->title() }}</a></p>
    </main>
</body>
</html>

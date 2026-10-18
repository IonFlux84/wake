<!doctype html>
<html lang="{{ $entry?->language() ?? $site->language() }}">
<head>
@wakeHead
</head>
<body>
    <main>
        <h1>{{ $entry->title() }}</h1>
        {{ $entry->content() }}
    </main>
</body>
</html>

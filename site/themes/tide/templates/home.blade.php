@extends('theme::layouts.default')

@section('content')
    @if ($entry)
        <section class="intro">
            <h1>{{ $entry->title() }}</h1>
            {{ $entry->content() }}
        </section>
    @else
        <section class="intro">
            <h1>{{ $site->title() }}</h1>
            @if ($site->description())<p class="lead">{{ $site->description() }}</p>@endif
        </section>
    @endif

    @php($posts = $site->posts()->paginate())
    <section class="post-grid">
        @foreach ($posts as $post)
            @include('theme::snippets.post-card', ['post' => $post])
        @endforeach
    </section>

    @if ($posts->hasPages())
        <nav class="pagination">
            @if ($posts->previousPageUrl())<a href="{{ $posts->previousPageUrl() }}">← {{ __('Newer posts') }}</a>@endif
            @if ($posts->nextPageUrl())<a href="{{ $posts->nextPageUrl() }}">{{ __('Older posts') }} →</a>@endif
        </nav>
    @endif
@endsection

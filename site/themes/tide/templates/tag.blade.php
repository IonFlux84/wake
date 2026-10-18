@extends('theme::layouts.default')

@section('content')
    <section class="intro">
        <p class="meta">{{ __('Tag') }}</p>
        <h1>#{{ $tag->title() }}</h1>
        @if ($tag->body() !== '')<div class="lead">{{ $tag->content() }}</div>@endif
    </section>

    <section class="post-grid">
        @foreach ($tag->posts() as $post)
            @include('theme::snippets.post-card', ['post' => $post])
        @endforeach
    </section>
@endsection

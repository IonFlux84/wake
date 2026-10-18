@extends('theme::layouts.default')

@section('content')
    @php
        $posts = $site->posts()->paginate();
        $featured = $posts->currentPage() === 1 ? ($site->posts()->where('featured', true)->first() ?? $posts->first()) : null;
    @endphp

    <section class="wrap intro">
        <h1 class="display">{{ $entry?->title() ?? $site->title() }}</h1>
        @if ($entry && trim($entry->body()) !== '')
            <div class="lead prose">{{ $entry->content() }}</div>
        @elseif ($site->description())
            <p class="lead">{{ $site->description() }}</p>
        @endif
    </section>

    @if ($featured)
        <section class="wrap featured">@include('theme::snippets.post-card', ['post' => $featured, 'large' => true])</section>
    @endif

    <section class="wrap grid">
        @foreach ($posts as $post)
            @continue($featured && $post->id() === $featured->id())
            @include('theme::snippets.post-card', ['post' => $post])
        @endforeach
    </section>

    <div class="wrap">@include('theme::snippets.pagination', ['paginator' => $posts])</div>

    @if ($site->tags()->isNotEmpty())
        <section class="wrap tags-cloud" aria-label="{{ __('Tags') }}">
            @foreach ($site->tags()->take(20) as $tag)<a class="chip" href="{{ $tag->url() }}">{{ $tag->title() }}</a>@endforeach
        </section>
    @endif
@endsection

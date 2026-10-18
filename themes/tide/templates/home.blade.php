@extends('theme::layouts.default')

@section('content')
    @php
        // Events have their own block below, so they don't appear twice.
        $eventKeys = collect($site->categories())->filter(fn ($c) => $c->dateField())->keys()->all();
        $posts = $site->posts()->where(fn ($fields) => ! in_array($fields['category'] ?? null, $eventKeys, true))->paginate();
        $featured = $posts->currentPage() === 1 ? ($site->posts()->where('featured', true)->where(fn ($fields) => ! in_array($fields['category'] ?? null, $eventKeys, true))->first() ?? $posts->first()) : null;
    @endphp

    <section class="wrap intro">
        <h1 class="display">{{ $entry?->title() ?? $site->title() }}</h1>
        @if ($entry && trim($entry->body()) !== '')
            <div class="lead prose">{{ $entry->content() }}</div>
        @elseif ($site->description())
            <p class="lead">{{ $site->description() }}</p>
        @endif
    </section>

    @foreach ($site->categories() as $eventCategory)
        @continue(! $eventCategory->dateField() || ! theme_option('show_next_events', true))
        @php($nextEvents = $eventCategory->posts()->limit(3)->get())
        {{-- posts() is ordered by the next date, so each event appears once, at its next date. --}}
        @if ($nextEvents->isNotEmpty() && $posts->currentPage() === 1)
            <section class="wrap next-events">
                <div class="section-head"><h2 class="section-title">{{ __('Next :category', ['category' => $eventCategory->title()]) }}</h2><a href="{{ $eventCategory->url() }}">{{ __('All') }} →</a></div>
                <div class="event-list event-list-row">@foreach ($nextEvents as $post)@include('theme::snippets.event-row', ['post' => $post])@endforeach</div>
            </section>
        @endif
    @endforeach

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

    @if (theme_option('show_tags', true) && $site->tags()->isNotEmpty())
        <section class="wrap tags-cloud" aria-label="{{ __('Tags') }}">
            @foreach ($site->tags()->take(20) as $tag)<a class="chip" href="{{ $tag->url() }}">{{ $tag->title() }}</a>@endforeach
        </section>
    @endif
@endsection

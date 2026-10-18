@extends('theme::layouts.default')

@section('content')
    @php
        $upcoming = $category->posts()->get();
        $past = $category->past()->limit(12)->get();
        $months = $upcoming->groupBy(fn ($post) => \Carbon\CarbonImmutable::parse($post->get('start'))->format('Y-m'));
    @endphp
    <section class="wrap narrow intro">
        <h1 class="display">{{ $category->title() }}</h1>
        @if ($category->description())<p class="lead">{{ $category->description() }}</p>@endif
    </section>

    <section class="wrap narrow">
        @forelse ($months as $month => $events)
            <h2 class="section-title month">{{ \Carbon\CarbonImmutable::parse($month.'-01')->locale($site->language())->isoFormat('MMMM YYYY') }}</h2>
            <div class="event-list">@foreach ($events as $post)@include('theme::snippets.event-row', ['post' => $post])@endforeach</div>
        @empty
            <p class="muted">{{ __('No upcoming events at the moment.') }}</p>
        @endforelse
    </section>

    @if ($past->isNotEmpty())
        <section class="wrap narrow past-events">
            <details>
                <summary class="section-title">{{ __('Past events') }}</summary>
                <div class="event-list">@foreach ($past as $post)@include('theme::snippets.event-row', ['post' => $post])@endforeach</div>
            </details>
        </section>
    @endif
@endsection

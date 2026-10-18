@extends('theme::layouts.default')

@section('content')
    @php
        // One row per date: a weekly event appears every week.
        $occurrences = collect($category->occurrences(now()->startOfDay(), now()->addMonths(6), $site->language(), 120));
        $months = $occurrences->groupBy(fn ($o) => $o['start']->format('Y-m'));
        $past = $category->past()->limit(12)->get();
    @endphp
    <section class="wrap narrow intro">
        <h1 class="display">{{ $category->title() }}</h1>
        @if ($category->description())<p class="lead">{{ $category->description() }}</p>@endif
    </section>

    <section class="wrap narrow">
        @forelse ($months as $month => $items)
            <h2 class="section-title month">{{ \Carbon\CarbonImmutable::parse($month.'-01')->locale($site->language())->isoFormat('MMMM YYYY') }}</h2>
            <div class="event-list">@foreach ($items as $o)@include('theme::snippets.event-row', ['post' => $o['entry'], 'start' => $o['start']])@endforeach</div>
        @empty
            <p class="muted">{{ __('No upcoming events at the moment.') }}</p>
        @endforelse
    </section>

    @if ($past->isNotEmpty())
        <section class="wrap narrow past-events">
            <details>
                <summary class="section-title">{{ __('Past events') }}</summary>
                <div class="event-list">@foreach ($past as $post)@include('theme::snippets.event-row', ['post' => $post, 'start' => $post->recurrence()?->last(now())['start'] ?? null])@endforeach</div>
            </details>
        </section>
    @endif
@endsection

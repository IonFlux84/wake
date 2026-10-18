@php
    $start = \Carbon\CarbonImmutable::parse($post->get('start'), config('app.timezone'))->locale($post->language());
@endphp
<span class="event-date {{ $post->get('event_status') === 'cancelled' ? 'is-cancelled' : '' }}" aria-hidden="true">
    <span class="event-date-month">{{ $start->isoFormat('MMM') }}</span>
    <span class="event-date-day">{{ $start->format('j') }}</span>
</span>

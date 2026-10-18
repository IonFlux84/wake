@php
    // $start may be passed for one occurrence of a recurring event.
    $start = ($start ?? $post->nextOccurrence()['start'] ?? $post->recurrence()?->start)?->locale($post->language());
@endphp
@if ($start)
<span class="event-date {{ $post->get('event_status') === 'cancelled' ? 'is-cancelled' : '' }}" aria-hidden="true">
    <span class="event-date-month">{{ $start->isoFormat('MMM') }}</span>
    <span class="event-date-day">{{ $start->format('j') }}</span>
</span>
@endif

@php
    // $start may be passed for one occurrence of a recurring event.
    $start = ($start ?? $post->nextOccurrence()['start'] ?? $post->recurrence()?->start)?->locale($post->language());
    $status = $post->get('event_status');
    $recurring = $post->recurrence()?->isRecurring();
@endphp
<article class="event-row">
    @include('theme::snippets.event-date', ['post' => $post, 'start' => $start])
    <div class="event-row-body">
        <h3 class="card-title"><a href="{{ $post->url() }}">{{ $post->title() }}</a></h3>
        <p class="meta">
            @if ($start)<span>{{ $start->isoFormat('dddd') }}{{ $post->get('all_day') ? '' : ', '.$start->isoFormat('LT') }}</span>@endif
            @if ($post->get('location'))<span>{{ $post->get('location') }}</span>@endif
            @if ($recurring)<span class="status-label status-recurring" title="{{ $post->recurrence()->describe($post->language()) }}">↻ {{ __('repeats') }}</span>@endif
            @if ($status && $status !== 'scheduled')<span class="status-label status-{{ $status }}">{{ $post->category()?->toArray()['fields']['event_status']['options'][$status][$post->language()] ?? $status }}</span>@endif
        </p>
    </div>
</article>

@php
    $start = \Carbon\CarbonImmutable::parse($post->get('start'), config('app.timezone'))->locale($post->language());
    $status = $post->get('event_status');
@endphp
<article class="event-row">
    @include('theme::snippets.event-date', ['post' => $post])
    <div class="event-row-body">
        <h3 class="card-title"><a href="{{ $post->url() }}">{{ $post->title() }}</a></h3>
        <p class="meta">
            <span>{{ $start->isoFormat('dddd') }}{{ $post->get('all_day') ? '' : ', '.$start->isoFormat('LT') }}</span>
            @if ($post->get('location'))<span>{{ $post->get('location') }}</span>@endif
            @if ($status && $status !== 'scheduled')<span class="status-label status-{{ $status }}">{{ $post->category()?->toArray()['fields']['event_status']['options'][$status][$post->language()] ?? $status }}</span>@endif
        </p>
    </div>
</article>

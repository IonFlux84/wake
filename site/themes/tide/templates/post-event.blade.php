@extends('theme::layouts.default')

@section('content')
    @php
        $tz = config('app.timezone');
        $start = \Carbon\CarbonImmutable::parse($post->get('start'), $tz)->locale($post->language());
        $end = $post->get('end') ? \Carbon\CarbonImmutable::parse($post->get('end'), $tz)->locale($post->language()) : null;
        $allDay = (bool) $post->get('all_day');
        $category = $post->category();
        $status = $post->get('event_status');
        $statusText = $status ? ($category?->toArray()['fields']['event_status']['options'][$status][$post->language()] ?? $status) : null;
        $label = fn (string $field) => $category?->label($field, $post->language()) ?? $field;
        $when = $start->isoFormat('dddd, LL');
        if (! $allDay) { $when .= ', '.$start->isoFormat('LT'); }
        if ($end) { $when .= ' – '.($end->isSameDay($start) ? ($allDay ? '' : $end->isoFormat('LT')) : $end->isoFormat($allDay ? 'dddd, LL' : 'dddd, LL, LT')); }
        $past = ($end ?? $start)->endOfDay()->isPast();
    @endphp

    <article class="post event">
        <header class="wrap narrow post-header">
            <p class="kicker-row">
                @if ($category)<a class="kicker" href="{{ $category->url($post->language()) }}">{{ $category->title($post->language()) }}</a>@endif
                @foreach ($post->tags() as $tag)<a class="kicker" href="{{ $tag->url() }}">{{ $tag->title() }}</a>@endforeach
            </p>
            <h1 class="display">{{ $post->title() }}</h1>
            @if ($statusText && $status !== 'scheduled')<p class="event-banner status-{{ $status }}">{{ $statusText }}</p>@endif
            @if ($past)<p class="event-banner">{{ __('This event is over.') }}</p>@endif
            @if ($post->get('excerpt'))<p class="lead">{{ $post->get('excerpt') }}</p>@endif
        </header>

        <section class="wrap narrow event-facts" aria-label="{{ __('Details') }}">
            <dl>
                <div><dt>{{ __('When') }}</dt><dd>{{ $when }}</dd></div>
                @if ($post->get('location') || $post->get('address'))
                    <div><dt>{{ __('Where') }}</dt><dd>
                        {{ $post->get('location') }}@if ($post->get('location') && $post->get('address'))<br>@endif{{ $post->get('address') }}
                        @if ($post->get('address'))<br><a href="https://www.openstreetmap.org/search?query={{ urlencode($post->get('address')) }}" rel="noopener" target="_blank">{{ __('Show on the map') }} ↗</a>@endif
                    </dd></div>
                @endif
                @if ($post->get('online_url'))<div><dt>{{ $label('online_url') }}</dt><dd><a href="{{ $post->get('online_url') }}" rel="noopener">{{ parse_url($post->get('online_url'), PHP_URL_HOST) }}</a></dd></div>@endif
                @if ($post->get('price'))<div><dt>{{ $label('price') }}</dt><dd>{{ $post->get('price') }}</dd></div>@endif
                @if ($post->get('organizer'))<div><dt>{{ $label('organizer') }}</dt><dd>{{ $post->get('organizer') }}</dd></div>@endif
                @if ($post->get('registration_deadline'))<div><dt>{{ $label('registration_deadline') }}</dt><dd>{{ \Carbon\CarbonImmutable::parse($post->get('registration_deadline'))->locale($post->language())->isoFormat('LL') }}</dd></div>@endif
                @if ($post->get('contact'))<div><dt>{{ $label('contact') }}</dt><dd>@if (filter_var($post->get('contact'), FILTER_VALIDATE_EMAIL))<a href="mailto:{{ $post->get('contact') }}">{{ $post->get('contact') }}</a>@else{{ $post->get('contact') }}@endif</dd></div>@endif
            </dl>
            <div class="event-actions">
                @if ($post->get('registration_url') && ! $past && $status !== 'cancelled' && $status !== 'soldout')
                    <a class="button" href="{{ $post->get('registration_url') }}" rel="noopener">{{ __('Register') }}</a>
                @endif
                @if (! $past && $status !== 'cancelled')
                    <a class="button button-quiet" href="{{ $post->url() }}.ics">{{ __('Add to calendar') }}</a>
                @endif
            </div>
        </section>

        @if ($post->image()?->isImage())
            <figure class="wrap cover">{{ $post->image()->picture($post->title(), '(min-width: 1100px) 1100px, 100vw', ['loading' => 'eager']) }}</figure>
        @endif

        <div class="wrap narrow prose">{{ $post->content() }}</div>
    </article>

    @if ($category)
        @php($next = $category->posts($post->language())->where(fn ($f, $row) => $row['id'] !== $post->id())->limit(3)->get())
        @if ($next->isNotEmpty())
            <section class="wrap narrow related">
                <h2 class="section-title">{{ __('More events') }}</h2>
                <div class="event-list">@foreach ($next as $item)@include('theme::snippets.event-row', ['post' => $item])@endforeach</div>
            </section>
        @endif
    @endif
@endsection

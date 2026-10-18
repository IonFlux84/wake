@php
    /** @var \Wake\Content\Site $site */
    /** @var \Wake\Content\Entry|null $entry */
    $languages = app(\Wake\Localization\Languages::class);
    $title = $entry ? ($entry->get('seo.title') ?: $entry->title().' · '.$site->title()) : $site->title();
    $description = $entry ? ($entry->get('seo.description') ?: $entry->excerpt(30)) : $site->description();
    $canonical = $entry ? $entry->url() : $site->url();
    $image = $entry?->thumbnail();
@endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
@if ($description !== '')
    <meta name="description" content="{{ $description }}">
@endif
    <link rel="canonical" href="{{ $canonical }}">
@if ($entry?->get('noindex') || $entry?->isFallback())
    <meta name="robots" content="noindex">
@endif
@if ($languages->isMultilingual())
@if ($entry)
@foreach ($entry->translations() as $code)
@if ($entry->translation($code)?->isPublished())
    <link rel="alternate" hreflang="{{ $code }}" href="{{ $entry->translation($code)->url() }}">
@endif
@endforeach
@else
@foreach ($languages->codes() as $code)
    <link rel="alternate" hreflang="{{ $code }}" href="{{ $site->url($code) }}">
@endforeach
@endif
@endif
    <link rel="alternate" type="application/rss+xml" title="{{ $site->title() }}" href="{{ $languages->url('feed.xml') }}">
    <meta property="og:site_name" content="{{ $site->title() }}">
    <meta property="og:title" content="{{ $entry?->title() ?? $site->title() }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:type" content="{{ $entry?->type() === 'post' ? 'article' : 'website' }}">
    <meta property="og:locale" content="{{ $entry?->language() ?? $site->language() }}">
@if ($description !== '')
    <meta property="og:description" content="{{ $description }}">
@endif
@if ($image?->isImage())
    <meta property="og:image" content="{{ $image->url(1280) }}">
    <meta name="twitter:card" content="summary_large_image">
@endif
@if ($entry?->type() === 'post' && $entry->get('start'))
    <script type="application/ld+json">{!! json_encode(array_filter([
        '@@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $entry->title(),
        'description' => $entry->excerpt(30),
        'startDate' => \Carbon\CarbonImmutable::parse($entry->get('start'), config('app.timezone'))->toIso8601String(),
        'endDate' => $entry->get('end') ? \Carbon\CarbonImmutable::parse($entry->get('end'), config('app.timezone'))->toIso8601String() : null,
        'eventStatus' => ['cancelled' => 'https://schema.org/EventCancelled', 'postponed' => 'https://schema.org/EventPostponed'][$entry->get('event_status')] ?? 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => $entry->get('online_url') && ! $entry->get('location') ? 'https://schema.org/OnlineEventAttendanceMode' : 'https://schema.org/OfflineEventAttendanceMode',
        'location' => $entry->get('location') || $entry->get('address') ? array_filter(['@type' => 'Place', 'name' => $entry->get('location'), 'address' => $entry->get('address')]) : null,
        'organizer' => $entry->get('organizer') ? ['@type' => 'Organization', 'name' => $entry->get('organizer')] : null,
        'image' => $image?->isImage() ? $image->url(1280) : null,
        'url' => $entry->url(),
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@elseif ($entry?->type() === 'post')
    <script type="application/ld+json">{!! json_encode(array_filter([
        '@@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $entry->title(),
        'datePublished' => $entry->date()?->toIso8601String(),
        'dateModified' => $entry->modifiedAt()->toIso8601String(),
        'inLanguage' => $entry->language(),
        'image' => $image?->isImage() ? $image->url(1280) : null,
        'url' => $entry->url(),
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endif
    <meta name="generator" content="Wake">
@php($consent = app(\Wake\Consent\ConsentSettings::class))
@php($consentTexts = $consent->texts(app()->getLocale()))
    <link rel="stylesheet" href="{{ app(\Wake\Panel\Panel::class)->asset('consent.css') }}">
    <script type="application/json" id="wake-consent-config">{!! json_encode([
        'version' => (int) $consent->get('version'),
        'banner' => $consent->bannerNeeded(),
        'categories' => $consent->categories(),
        'position' => $consent->get('position'),
        'privacy' => $consent->get('privacy_url'),
        'endpoint' => url('hit.php'),
        'texts' => $consentTexts,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    <script defer src="{{ app(\Wake\Panel\Panel::class)->asset('consent.js') }}"></script>
@php(app(\Wake\Stats\TrackingConfig::class)->sync())
@if (config('wake.tracking.enabled'))
    <script defer src="{{ app(\Wake\Panel\Panel::class)->asset('track.js') }}" data-endpoint="{{ url('hit.php') }}"></script>
@endif

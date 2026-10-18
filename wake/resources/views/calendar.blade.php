@php
    $tz = config('app.timezone');
    $start = \Carbon\CarbonImmutable::parse($entry->get('start'), $tz);
    $end = $entry->get('end') ? \Carbon\CarbonImmutable::parse($entry->get('end'), $tz) : ($entry->get('all_day') ? $start : $start->addHours(2));
    $allDay = (bool) $entry->get('all_day');
    $recurrence = $entry->recurrence();
    $esc = fn ($text) => addcslashes(str_replace(["\r\n", "\n"], '\n', (string) $text), ',;\\');
    $lines = array_filter([
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Wake//EN', 'CALSCALE:GREGORIAN', 'BEGIN:VEVENT',
        'UID:'.md5($entry->id()).'@'.parse_url(url('/'), PHP_URL_HOST),
        'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
        $allDay ? 'DTSTART;VALUE=DATE:'.$start->format('Ymd') : 'DTSTART:'.$start->utc()->format('Ymd\THis\Z'),
        $allDay ? 'DTEND;VALUE=DATE:'.$end->addDay()->format('Ymd') : 'DTEND:'.$end->utc()->format('Ymd\THis\Z'),
        $recurrence?->rrule() ? 'RRULE:'.$recurrence->rrule() : null,
        $recurrence?->isRecurring() && $recurrence->except ? 'EXDATE'.($allDay ? ';VALUE=DATE:' : ':').collect($recurrence->except)->map(fn ($d) => $allDay ? str_replace('-', '', $d) : \Carbon\CarbonImmutable::parse($d.' '.$start->format('H:i'), $tz)->utc()->format('Ymd\THis\Z'))->implode(',') : null,
        'SUMMARY:'.$esc($entry->title()),
        $entry->excerpt() ? 'DESCRIPTION:'.$esc($entry->excerpt().' '.$entry->url()) : null,
        ($entry->get('location') || $entry->get('address')) ? 'LOCATION:'.$esc(trim($entry->get('location').', '.$entry->get('address'), ', ')) : null,
        'URL:'.$entry->url(),
        $entry->get('event_status') === 'cancelled' ? 'STATUS:CANCELLED' : 'STATUS:CONFIRMED',
        'END:VEVENT', 'END:VCALENDAR',
    ]);
@endphp
{!! implode("\r\n", $lines)."\r\n" !!}

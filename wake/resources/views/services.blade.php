@php
    $german = str_starts_with(app()->getLocale(), 'de');
    $services = app(\Wake\Content\Index::class)->services();
    $withConsent = config('wake.tracking.level1') === 'consent';
    $withCountry = is_file((string) config('wake.tracking.geo'));
@endphp
<div class="wake-services">
@if (config('wake.tracking.enabled'))
    <h3>{{ $german ? 'Statistik' : 'Statistics' }}</h3>
    <p>
        @if ($german)
            Diese Website zählt Besuche mit der eingebauten Statistik von Wake{{ $withConsent ? ', aber nur, wenn Sie im Cookie-Banner der Statistik zustimmen (Art. 6 Abs. 1 lit. a DSGVO)' : '' }}. Dabei wird kein Cookie gesetzt und die IP-Adresse nicht gespeichert. Aus IP-Adresse und Browserkennung wird ein Kennwert gebildet, der mit einem täglich wechselnden, zufälligen Schlüssel verschlüsselt ist und sich nach 24 Stunden keiner Person mehr zuordnen lässt. Erfasst werden aufgerufene Seiten, Herkunft, Lesedauer, Scrolltiefe, Sprache, Gerätetyp, Browser, Betriebssystem{{ $withCountry ? ' und Land' : '' }}. Die Daten verlassen unseren Server nicht. Rohdaten werden nach {{ config('wake.tracking.retention') }} Tagen gelöscht.
        @else
            This website counts visits with Wake's built-in statistics{{ $withConsent ? ', but only if you agree to statistics in the cookie banner' : '' }}. No cookie is set and the IP address is not stored. IP address and browser identifier are turned into a value encrypted with a random key that changes daily, so after 24 hours it cannot be linked to anyone. Recorded are pages viewed, origin, reading time, scroll depth, language, device type, browser, operating system{{ $withCountry ? ' and country' : '' }}. The data does not leave our server. Raw data is deleted after {{ config('wake.tracking.retention') }} days.
        @endif
    </p>
@endif
    <h3>{{ $german ? 'Notwendige Cookies' : 'Necessary cookies' }}</h3>
    <ul>
        <li><code>wake_consent</code>: {{ $german ? 'speichert Ihre Auswahl im Cookie-Banner, 12 Monate.' : 'stores your choice in the cookie banner, 12 months.' }}</li>
    </ul>
@if (app(\Wake\Consent\ConsentSettings::class)->get('statistics'))
    <h3>{{ $german ? 'Statistik-Cookie (nur mit Einwilligung)' : 'Statistics cookie (only with consent)' }}</h3>
    <ul>
        <li><code>wake_v</code>: {{ $german ? 'zufällige Kennung, um wiederkehrende Besuche zu erkennen, 12 Monate. Die Daten bleiben auf unserem Server.' : 'random identifier to recognise returning visits, 12 months. The data stays on our server.' }}</li>
    </ul>
@endif
@if ($services !== [])
    <h3>{{ $german ? 'Eingebundene Dienste (nur nach Klick oder Einwilligung)' : 'Embedded services (only after a click or consent)' }}</h3>
    <ul>
    @foreach ($services as $key)
        @php($service = \Wake\Consent\Services::get($key))
        <li><strong>{{ $service['name'] }}</strong>@if ($service['company']), {{ $service['company'] }}@endif @if ($service['privacy'])· <a href="{{ $service['privacy'] }}" rel="noopener">{{ $german ? 'Datenschutzerklärung' : 'Privacy policy' }}</a>@endif</li>
    @endforeach
    </ul>
@endif
</div>

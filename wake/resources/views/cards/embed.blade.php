@php
    $url = $attributes['url'] ?? $value;
    $key = \Wake\Consent\Services::detect($url);
    $service = \Wake\Consent\Services::get($key, $url);
    $texts = app(\Wake\Consent\ConsentSettings::class)->texts(app()->getLocale());
@endphp
<figure class="card card-embed">
    <div class="wake-embed" data-wake-embed data-service="{{ $key }}" data-src="{{ \Wake\Consent\Services::embedUrl($url) }}" data-title="{{ $attributes['title'] ?? $service['name'] }}">
        <div class="wake-embed__inner">
            <strong>{{ $service['name'] }}</strong>
            <span>{{ str_replace(':service', $service['name'], $texts['embed_text']) }}</span>
            <button type="button" class="wake-embed__load" data-wake-embed-load>{{ $texts['embed_load'] }}</button>
            <label class="wake-embed__always"><input type="checkbox" data-wake-embed-always> {{ str_replace(':service', $service['name'], $texts['embed_always']) }}</label>
        </div>
    </div>
    @if (trim(strip_tags($content)) !== '')<figcaption>{{ $content }}</figcaption>@endif
</figure>

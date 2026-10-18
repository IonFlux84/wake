@php($languages = app(\Wake\Localization\Languages::class))
@if ($languages->isMultilingual())
    <span class="languages" role="group" aria-label="{{ __('Language') }}">
        @foreach ($languages->all() as $code => $name)
            @php($translation = $entry?->translation($code))
            @if ($code === ($entry?->requestedLanguage() ?? $site->language()))
                <span aria-current="true" title="{{ $name }}">{{ strtoupper($code) }}</span>
            @elseif (! $entry)
                <a href="{{ $site->url($code) }}" hreflang="{{ $code }}" lang="{{ $code }}" title="{{ $name }}">{{ strtoupper($code) }}</a>
            @elseif ($translation?->isPublished())
                <a href="{{ $translation->url() }}" hreflang="{{ $code }}" lang="{{ $code }}" title="{{ $name }}">{{ strtoupper($code) }}</a>
            @endif
        @endforeach
    </span>
@endif

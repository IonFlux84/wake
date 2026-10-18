<p class="card card-button">
    <a class="button" href="{{ $attributes['href'] ?? '#' }}">{{ $value !== '' ? $value : strip_tags($content) }}</a>
</p>

{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($languages->codes() as $code)
    <url>
        <loc>{{ $languages->url('', $code) }}</loc>
        <lastmod>{{ date('Y-m-d', $updated ?: time()) }}</lastmod>
    </url>
@endforeach
@foreach ($entries as $entry)
    <url>
        <loc>{{ $entry->url() }}</loc>
        <lastmod>{{ $entry->modifiedAt()->format('Y-m-d') }}</lastmod>
@if ($languages->isMultilingual())
@foreach ($entry->translations() as $code)
@if ($entry->translation($code)?->isPublished())
        <xhtml:link rel="alternate" hreflang="{{ $code }}" href="{{ $entry->translation($code)->url() }}"/>
@endif
@endforeach
@endif
    </url>
@endforeach
</urlset>

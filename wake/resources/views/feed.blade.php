{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">
<channel>
    <title>{{ $site->title() }}</title>
    <link>{{ $site->url() }}</link>
    <description>{{ $site->description() }}</description>
    <language>{{ $site->language() }}</language>
    <atom:link href="{{ url()->current() }}" rel="self" type="application/rss+xml"/>
    <generator>Wake</generator>
@foreach ($posts as $post)
    <item>
        <title>{{ $post->title() }}</title>
        <link>{{ $post->url() }}</link>
        <guid isPermaLink="false">{{ $post->id() }}#{{ $post->language() }}</guid>
@if ($post->date())
        <pubDate>{{ $post->date()->toRfc2822String() }}</pubDate>
@endif
        <description>{{ $post->excerpt() }}</description>
        <content:encoded><![CDATA[{!! str_replace(']]>', ']]]]><![CDATA[>', (string) $post->content()) !!}]]></content:encoded>
    </item>
@endforeach
</channel>
</rss>

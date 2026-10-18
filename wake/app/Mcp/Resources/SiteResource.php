<?php

namespace Wake\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;
use Wake\Content\ContentTypes;
use Wake\Content\Site;
use Wake\Localization\Languages;

#[Uri('wake://site')]
#[Description('The website at a glance: title, languages, content types and how posts are written in Wake (front matter, cards). Read this before writing content.')]
#[MimeType('text/markdown')]
class SiteResource extends Resource
{
    public function handle(Request $request): Response
    {
        $site = app(Site::class);
        $languages = app(Languages::class);

        $text = "# {$site->title()}\n\n".($site->description() ? $site->description()."\n\n" : '');
        $text .= '- Address: '.url('/')."\n";
        $text .= '- Languages: '.collect($languages->all())->map(fn ($n, $c) => "{$n} ({$c})")->implode(', ').", default {$languages->default()}\n";
        $text .= '- Content types: '.collect(app(ContentTypes::class)->all())->map(fn ($t) => "{$t->name} (folder {$t->folder})")->implode(', ')."\n\n";
        $text .= <<<'MARKDOWN'
        ## How content is written

        Every entry is Markdown with YAML front matter. Common fields: `title`, `slug`, `status`
        (draft, scheduled, published), `published` (YYYY-MM-DD HH:MM), `tags`, `excerpt`, `cover`.

        Cards are blocks that stay readable as text:

            ::: callout 💡
            Text inside the card.
            :::

            ::: embed https://www.youtube.com/watch?v=…
            Caption
            :::

        Each language is its own file. A translation should get its own `slug`.
        MARKDOWN;

        return Response::text($text);
    }
}

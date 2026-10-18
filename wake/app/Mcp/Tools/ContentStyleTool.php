<?php

namespace Wake\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Wake\Accounts\Permissions;
use Wake\Content\PageStyle;
use Wake\Content\Query;
use Wake\Content\Writer;

#[Name('content_style')]
#[Description('Replace the design of one entry: its style.css, which applies only to that page, inside the <main> element, for every language. Send the complete CSS; an empty string removes it. Wake removes @import, addresses of other sites, image-set() and expression(); images from the media library work with url(media/…). content_read shows the current CSS and the classes used in the text. Changes to a published entry are live immediately. Wake keeps the previous CSS as a version.')]
class ContentStyleTool extends WakeTool
{
    protected string $scope = 'content:write';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Entry id from content_list, e.g. "pages/about".')->required(),
            'css' => $schema->string()->description('The complete new style.css, at most 64 KB.')->required(),
        ];
    }

    protected function run(Request $request): Response
    {
        $entry = (new Query)->find((string) $request->get('id'));

        if (! $entry) {
            return Response::error('There is no entry with this id. content_list shows the ids.');
        }

        if (! Permissions::mayEdit(auth()->user(), $entry)) {
            return Response::error('Your role may not change this entry.');
        }

        if ($entry->isPublished() && ! Permissions::mayPublish(auth()->user(), $entry)) {
            return Response::error('This entry is published, and your role may not change published entries.');
        }

        $css = (string) $request->get('css');

        if (strlen($css) > PageStyle::MAX_BYTES) {
            return Response::error('The CSS is longer than 64 KB.');
        }

        app(Writer::class)->saveStyle($entry->id(), $css);
        $saved = (string) (new Query)->find($entry->id())?->style();

        return Response::json([
            'id' => $entry->id(),
            'css' => $saved,
            'cleaned' => trim($saved) !== trim($css),
            'note' => trim($saved) !== trim($css)
                ? 'Wake removed or changed parts of the CSS (imports, foreign addresses or unbalanced braces). Compare "css" with what you sent.'
                : 'Saved as sent.',
            'url' => $entry->url(),
        ]);
    }
}

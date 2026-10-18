<?php

namespace Wake\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Wake\Accounts\Permissions;
use Wake\Content\Query;
use Wake\Content\Writer;
use Wake\Localization\Languages;

#[Name('content_unpublish')]
#[Description('Take one translation off the website by turning it back into a draft. Nothing is deleted.')]
class ContentUnpublishTool extends WakeTool
{
    protected string $scope = 'content:publish';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'language' => $schema->string()->description('Default is the site\'s default language.'),
        ];
    }

    protected function run(Request $request): Response
    {
        $language = $request->get('language') ?: app(Languages::class)->default();
        $entry = (new Query)->in($language)->find((string) $request->get('id'));

        if (! $entry || ! $entry->hasTranslation($language)) {
            return Response::error('There is no such entry in this language.');
        }

        if (! Permissions::mayPublish(auth()->user(), $entry)) {
            return Response::error('Your role may not unpublish this entry.');
        }

        app(Writer::class)->unpublish($entry->id(), $language);

        return Response::json(['id' => $entry->id(), 'status' => 'draft']);
    }
}

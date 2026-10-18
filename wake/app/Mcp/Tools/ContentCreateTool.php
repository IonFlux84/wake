<?php

namespace Wake\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Wake\Content\Writer;

#[Name('content_create')]
#[Description('Create a new entry. It always starts as a draft and is marked as written by an assistant; publishing is a separate step (content_publish). Read the resource wake://site first for the Markdown and card syntax.')]
class ContentCreateTool extends WakeTool
{
    protected string $scope = 'content:write';

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('post (default), page or a custom type.'),
            'title' => $schema->string()->required(),
            'body' => $schema->string()->description('Markdown body.'),
            'language' => $schema->string()->description('Language code, default is the site\'s default language.'),
            'fields' => $schema->object()->description('More front matter, e.g. {"tags": ["news"], "excerpt": "…", "slug": "…"}. status and author are set by Wake.'),
            'parent' => $schema->string()->description('For pages: id of the parent page.'),
        ];
    }

    protected function run(Request $request): Response
    {
        $fields = array_diff_key((array) $request->get('fields', []), array_flip(['status', 'author', 'via', 'published']));

        try {
            $entry = app(Writer::class)->create(
                $request->get('type', 'post'),
                [...$fields, 'title' => $request->get('title'), 'status' => 'draft', 'author' => auth()->id(), 'via' => 'mcp'],
                (string) $request->get('body', ''),
                $request->get('language'),
                $request->get('parent'),
            );
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        return Response::json([
            'id' => $entry->id(),
            'status' => 'draft',
            'language' => $entry->language(),
            'edit_in_panel' => route('wake.entries.edit', ['type' => $entry->type(), 'id' => $entry->id()]),
            'next_step' => 'Ask the person to review the draft in the panel, or call content_publish if they asked you to publish.',
        ]);
    }
}

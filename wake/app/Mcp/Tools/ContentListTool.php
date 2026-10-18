<?php

namespace Wake\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Wake\Content\ContentTypes;
use Wake\Content\Entry;
use Wake\Content\Query;
use Wake\Localization\Languages;

#[Name('content_list')]
#[Description('List posts, pages or entries of another content type, newest first, with id, title, status, date, URL and languages. Filter by status, tag or a search term in the title.')]
class ContentListTool extends WakeTool
{
    protected string $scope = 'content:read';

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('Content type: post (default), page, tag or a custom type from site_info.'),
            'status' => $schema->string()->enum(['any', 'published', 'draft', 'scheduled'])->description('Default any.'),
            'tag' => $schema->string()->description('Only entries with this tag.'),
            'search' => $schema->string()->description('Words in the title.'),
            'language' => $schema->string()->description('Language code, default is the site\'s default language.'),
            'limit' => $schema->integer()->description('Default 20, at most 100.'),
        ];
    }

    protected function run(Request $request): Response
    {
        $type = $request->get('type', 'post');

        if (! app(ContentTypes::class)->get($type)) {
            return Response::error("Unknown content type {$type}. site_info lists the types.");
        }

        $query = (new Query($type))->in($request->get('language') ?: app(Languages::class)->default())->withFallback(true);

        match ($request->get('status', 'any')) {
            'published' => $query->published(),
            'draft' => $query->drafts(),
            'scheduled' => $query->scheduled(),
            default => null,
        };

        if ($tag = $request->get('tag')) {
            $query->tag($tag);
        }

        if ($search = mb_strtolower(trim((string) $request->get('search')))) {
            $query->where(fn (array $fields, array $row) => str_contains(mb_strtolower((string) ($fields['title'] ?? $row['name'])), $search));
        }

        $entries = $query->limit(max(1, min(100, (int) $request->get('limit', 20))))->get();

        return Response::json([
            'count' => $entries->count(),
            'entries' => $entries->map(fn (Entry $e) => [
                'id' => $e->id(),
                'title' => $e->title(),
                'status' => $e->isScheduled() ? 'scheduled' : ($e->isPublished() ? 'published' : 'draft'),
                'date' => $e->date()?->toIso8601String(),
                'url' => $e->url(),
                'languages' => $e->translations(),
                'tags' => (array) $e->get('tags', []),
                'created_by_assistant' => $e->get('via') === 'mcp',
            ])->values(),
        ]);
    }
}

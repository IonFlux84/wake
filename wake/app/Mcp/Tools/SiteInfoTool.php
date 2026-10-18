<?php

namespace Wake\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Wake\Content\Categories;
use Wake\Content\ContentTypes;
use Wake\Content\Index;
use Wake\Content\Site;
use Wake\Localization\Languages;
use Wake\Mcp\CurrentToken;

#[Name('site_info')]
#[Description('Basic facts about the website: title, address, languages, content types with counts, and what the current token may do. Useful as a first call.')]
class SiteInfoTool extends WakeTool
{
    protected string $scope = '';

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response
    {
        return $this->run($request);
    }

    protected function run(Request $request): Response
    {
        $site = app(Site::class);
        $index = app(Index::class);
        $token = app(CurrentToken::class);

        return Response::json([
            'title' => $site->title(),
            'description' => $site->description(),
            'url' => url('/'),
            'languages' => app(Languages::class)->all(),
            'default_language' => app(Languages::class)->default(),
            'content_types' => collect(app(ContentTypes::class)->all())->map(fn ($type) => [
                'folder' => $type->folder,
                'count' => count($index->ofType($type->name)),
            ])->all(),
            'categories' => collect(app(Categories::class)->all())->map(fn ($c) => [
                'title' => $c->title(),
                'fields' => collect($c->fields())->map(fn ($f, $key) => array_filter([
                    'type' => $f['type'] ?? 'text',
                    'label' => $c->label($key),
                    'required' => $f['required'] ?? null,
                    'same_in_all_languages' => ($f['translate'] ?? true) === false ?: null,
                    'options' => isset($f['options']) ? array_keys($f['options']) : null,
                ]))->all(),
                'note' => 'Set fields.category to the key and fill these fields in content_create. Dates as YYYY-MM-DD HH:MM.',
            ])->all(),
            'you' => auth()->user() ? ['name' => auth()->user()->name(), 'role' => auth()->user()->role()->key, 'capabilities' => auth()->user()->role()->capabilities()] : null,
            'token_scopes' => $token->record()['scopes'] ?? null,
            'timezone' => config('app.timezone'),
        ]);
    }
}

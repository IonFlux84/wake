<?php

namespace Wake\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Wake\Accounts\Permissions;
use Wake\Content\Query;
use Wake\Content\Writer;
use Wake\Localization\Languages;

#[Name('content_update')]
#[Description('Change the title, body or other fields of one translation, or add a translation in another language. Wake keeps the previous version as a revision. Changes to a published entry are live immediately, so prefer drafts unless asked.')]
class ContentUpdateTool extends WakeTool
{
    protected string $scope = 'content:write';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'language' => $schema->string()->description('Language code, default is the site\'s default language. A new language adds a translation.'),
            'title' => $schema->string(),
            'body' => $schema->string()->description('The complete new Markdown body. Leave out to keep the body.'),
            'fields' => $schema->object()->description('Fields to change; null removes a field. status, author and published cannot be changed here.'),
        ];
    }

    protected function run(Request $request): Response
    {
        $id = (string) $request->get('id');
        $language = $request->get('language') ?: app(Languages::class)->default();
        $entry = (new Query)->in($language)->find($id);

        if (! $entry) {
            return Response::error('There is no entry with this id. content_list shows the ids.');
        }

        if (! Permissions::mayEdit(auth()->user(), $entry)) {
            return Response::error('Your role may not change this entry.');
        }

        if ($entry->isPublished() && ! Permissions::mayPublish(auth()->user(), $entry)) {
            return Response::error('This entry is published, and your role may not change published entries.');
        }

        $fields = array_diff_key((array) $request->get('fields', []), array_flip(['status', 'author', 'via', 'published']));

        if ($request->get('title') !== null) {
            $fields['title'] = $request->get('title');
        }

        if (! $entry->hasTranslation($language)) {
            $fields += ['status' => 'draft', 'via' => 'mcp'];
            $fields['title'] ??= $entry->title();
        }

        try {
            $updated = app(Writer::class)->update($id, $language, $fields, $request->get('body'));
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        return Response::json([
            'id' => $updated->id(),
            'language' => $updated->language(),
            'status' => $updated->isPublished() ? 'published' : ($updated->isScheduled() ? 'scheduled' : 'draft'),
            'url' => $updated->url(),
        ]);
    }
}

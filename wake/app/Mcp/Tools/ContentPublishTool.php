<?php

namespace Wake\Mcp\Tools;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Throwable;
use Wake\Accounts\Permissions;
use Wake\Content\Query;
use Wake\Content\Writer;
use Wake\Localization\Languages;

#[Name('content_publish')]
#[Description('Publish one translation now or schedule it for later. If the site requires approval for assistants, the entry is only marked for review and a person publishes it in the panel.')]
class ContentPublishTool extends WakeTool
{
    protected string $scope = 'content:publish';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'language' => $schema->string()->description('Default is the site\'s default language.'),
            'at' => $schema->string()->description('Publish later, at this date and time (YYYY-MM-DD HH:MM, site time zone).'),
        ];
    }

    protected function run(Request $request): Response
    {
        $id = (string) $request->get('id');
        $language = $request->get('language') ?: app(Languages::class)->default();
        $entry = (new Query)->in($language)->find($id);

        if (! $entry || ! $entry->hasTranslation($language)) {
            return Response::error('There is no such entry in this language. content_list shows the ids.');
        }

        if (! Permissions::mayPublish(auth()->user(), $entry)) {
            return Response::error('Your role may not publish this entry.');
        }

        try {
            $at = $request->get('at') ? CarbonImmutable::parse($request->get('at'), config('app.timezone')) : null;
        } catch (Throwable) {
            return Response::error('The date could not be read. Use YYYY-MM-DD HH:MM.');
        }

        if (config('wake.mcp.approval')) {
            app(Writer::class)->update($id, $language, ['review' => $at ? 'scheduled '.$at->format('Y-m-d H:i') : 'requested']);

            return Response::json([
                'id' => $id,
                'status' => 'waiting_for_approval',
                'message' => 'This site requires a person to approve what assistants publish. The entry is marked for review in the panel.',
            ]);
        }

        $published = app(Writer::class)->publish($id, $language, $at);

        return Response::json([
            'id' => $id,
            'status' => $published->isScheduled() ? 'scheduled' : 'published',
            'date' => $published->date()?->toIso8601String(),
            'url' => $published->url(),
        ]);
    }
}

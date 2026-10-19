<?php

namespace RedirectScout\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use RedirectScout\Redirector;
use Wake\Mcp\Tools\WakeTool;

#[Name('redirect_scout_set')]
#[Description('Saves where an old address leads: an entry id, a path like /blog, a media file like media/posts/x/flyer.pdf, a full https:// address, or "gone" for 410. Only set targets the person agreed to.')]
class SetTool extends WakeTool
{
    protected string $scope = 'content:write';

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->description('The old address, e.g. /aktuelles/old-post.html')->required(),
            'target' => $schema->string()->description('Where it leads.')->required(),
        ];
    }

    protected function run(Request $request): Response
    {
        if (! auth()->user()?->can('settings.manage')) {
            return Response::error('Only people who may manage settings can change redirects.');
        }

        $path = trim((string) $request->get('path'), '/');
        $error = app(Redirector::class)->save($path, (string) $request->get('target'));

        return $error === null
            ? Response::text("/{$path} now leads to {$request->get('target')}.")
            : Response::error($error);
    }
}

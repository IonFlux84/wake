<?php

namespace Wake\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Wake\Mcp\CurrentToken;

/**
 * Base of all Wake tools: checks the scope of the token before a tool runs.
 */
abstract class WakeTool extends Tool
{
    /** The token scope this tool needs. */
    protected string $scope = 'stats:read';

    public function handle(Request $request): Response
    {
        $token = app(CurrentToken::class);

        if ($token->record() !== null && ! $token->can($this->scope)) {
            return Response::error(sprintf(
                'This token may not use %s. It needs the scope "%s". Create a token with that scope in the Wake panel under Assistants.',
                $this->name(), $this->scope,
            ));
        }

        return $this->run($request);
    }

    abstract protected function run(Request $request): Response;
}

<?php

namespace Greeter\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Wake\Mcp\Tools\WakeTool;

#[Name('greeter_greet')]
#[Description('Says hello.')]
class GreetTool extends WakeTool
{
    protected string $scope = 'content:read';

    protected function run(Request $request): Response
    {
        return Response::text('Hello from a plugin tool');
    }
}

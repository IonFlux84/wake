<?php

namespace Wake\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Wake\Stats\Stats;

#[Name('stats_realtime')]
#[Description('Visitors on the site right now (the last five minutes) and the pages they are reading.')]
class StatsRealtimeTool extends WakeTool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function run(Request $request): Response
    {
        return Response::json(app(Stats::class)->realtime());
    }
}

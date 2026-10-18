<?php

namespace Wake\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Wake\Stats\Stats;

#[Name('stats_ai_crawlers')]
#[Description('Visits by AI crawlers such as GPTBot, ClaudeBot or PerplexityBot, by crawler and by page, for a period. These visits are not part of the visitor numbers.')]
class StatsAiTool extends WakeTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()->enum(['today', '7d', '30d', '90d'])->description('Default 30d.'),
        ];
    }

    protected function run(Request $request): Response
    {
        $stats = app(Stats::class);
        $report = $stats->period($request->get('period', '30d'));
        $ai = $stats->aiCrawlers($report->from, $report->to);
        $ai['pages'] = array_slice($ai['pages'], 0, 20, true);

        return Response::json(['from' => $report->from->toDateString(), 'to' => $report->to->toDateString(), ...$ai]);
    }
}

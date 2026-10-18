<?php

namespace Wake\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Wake\Stats\Insights;
use Wake\Stats\Stats;

#[Name('stats_overview')]
#[Description('Visitors, page views, reading time and consent share for a period, compared with the period before, plus the most read pages and top sources. Start here for questions like "How was last week?".')]
class StatsOverviewTool extends WakeTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()->enum(['today', 'yesterday', '7d', '30d', '90d', '12m', 'month'])
                ->description('Period, default 7d (the last seven days including today).'),
        ];
    }

    protected function run(Request $request): Response
    {
        $stats = app(Stats::class);
        $report = $stats->period($request->get('period', '7d'));
        $days = $report->from->diffInDays($report->to) + 1;
        $previous = $stats->report($report->from->subDays($days), $report->from->subDay());

        return Response::json([
            'from' => $report->from->toDateString(),
            'to' => $report->to->toDateString(),
            'visitors' => $report->visitors(),
            'pageviews' => $report->pageviews(),
            'average_reading_seconds' => $report->averageDuration(),
            'consent_share' => round($report->consentShare(), 2),
            'change_percent' => [
                'visitors' => Insights::delta($previous->visitors(), $report->visitors()),
                'pageviews' => Insights::delta($previous->pageviews(), $report->pageviews()),
            ],
            'top_pages' => array_map(fn ($p) => [
                'path' => $p['path'], 'title' => $p['entry']?->title(), 'id' => $p['entry']?->id(),
                'visitors' => $p['visitors'], 'pageviews' => $p['pageviews'], 'read_rate' => $p['read_rate'],
            ], $report->pages(5)),
            'top_sources' => $report->top('referrers', 5),
            'note' => 'Visitors are counted without cookies and added up per day. An empty source means direct visits.',
        ]);
    }
}

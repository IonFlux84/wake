<?php

namespace Wake\Mcp\Tools;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Wake\Stats\Stats;

#[Name('stats_query')]
#[Description('Statistics for any date range, broken down by one dimension: days, hours, pages, referrers, campaigns, countries, languages, devices, browsers, os or events.')]
class StatsQueryTool extends WakeTool
{
    public const DIMENSIONS = ['days', 'hours', 'pages', 'referrers', 'campaigns', 'countries', 'languages', 'devices', 'browsers', 'os', 'events'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('First day, YYYY-MM-DD.')->required(),
            'to' => $schema->string()->description('Last day, YYYY-MM-DD. At most 400 days after "from".')->required(),
            'dimension' => $schema->string()->enum(self::DIMENSIONS)->description('How to break the numbers down.')->required(),
            'limit' => $schema->integer()->description('Maximum number of rows, default 20.'),
        ];
    }

    protected function run(Request $request): Response
    {
        $data = $request->validate([
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d|after_or_equal:from',
            'dimension' => 'required|in:'.implode(',', self::DIMENSIONS),
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        if (CarbonImmutable::parse($data['from'])->diffInDays($data['to']) > 400) {
            return Response::error('The range is too long. Ask for at most 400 days at a time.');
        }

        $report = app(Stats::class)->report($data['from'], $data['to']);
        $limit = $data['limit'] ?? 20;

        $rows = match ($data['dimension']) {
            'days' => $report->series(),
            'hours' => $report->hours(),
            'pages' => array_map(fn ($p) => [...$p, 'title' => $p['entry']?->title(), 'entry' => $p['entry']?->id()], $report->pages($limit)),
            'events' => $report->events($limit),
            default => $report->top($data['dimension'], $limit),
        };

        return Response::json([
            'from' => $data['from'],
            'to' => $data['to'],
            'visitors' => $report->visitors(),
            'pageviews' => $report->pageviews(),
            $data['dimension'] => $rows,
        ]);
    }
}

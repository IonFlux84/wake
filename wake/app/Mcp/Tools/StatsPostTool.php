<?php

namespace Wake\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Wake\Content\Entry;
use Wake\Content\Query;
use Wake\Stats\Stats;

#[Name('stats_post')]
#[Description('How one post or page is doing: visitors, reading time, read-through rate, where readers came from, what they opened next, and visitors per day. Identify it by its id (from content_list) or its URL path.')]
class StatsPostTool extends WakeTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Entry id, e.g. "posts/2026-10-17-hello-world".'),
            'path' => $schema->string()->description('URL path, e.g. "/hello-world". Used when no id is given.'),
            'period' => $schema->string()->enum(['7d', '30d', '90d', '12m'])->description('Default 30d.'),
        ];
    }

    protected function run(Request $request): Response
    {
        $paths = [];
        $entry = null;

        if ($id = $request->get('id')) {
            $entry = (new Query)->find($id);

            if (! $entry) {
                return Response::error("There is no entry with the id {$id}. content_list shows the ids.");
            }

            // Every language of an entry has its own address.
            foreach ($entry->translations() as $language) {
                $paths[$language] = parse_url($entry->translation($language)->url(), PHP_URL_PATH) ?: '/';
            }
        } elseif ($path = $request->get('path')) {
            $paths['?'] = '/'.ltrim((string) parse_url($path, PHP_URL_PATH), '/');
        } else {
            return Response::error('Give either an id or a path.');
        }

        $stats = app(Stats::class);
        $report = $stats->period($request->get('period', '30d'));

        $result = [
            'id' => $entry?->id(),
            'title' => $entry?->title(),
            'from' => $report->from->toDateString(),
            'to' => $report->to->toDateString(),
            'languages' => [],
        ];

        foreach ($paths as $language => $path) {
            $page = $report->page($path);
            $result['languages'][$language] = [
                'path' => $path,
                'visitors' => $page['visitors'],
                'pageviews' => $page['pageviews'],
                'average_reading_seconds' => $page['duration'],
                'average_scroll_percent' => $page['scroll'],
                'read_rate' => $page['read_rate'],
                'came_from' => array_slice($page['sources'], 0, 8, true),
                'went_on_to' => array_slice($page['next'], 0, 8, true),
                'per_day' => $stats->pageSeries($path, $report->from, $report->to),
            ];
        }

        return Response::json($result);
    }
}

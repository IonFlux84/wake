<?php

namespace Wake\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;
use Wake\Mcp\CurrentToken;
use Wake\Stats\Stats;

#[Uri('wake://stats/today')]
#[Description('Today so far: visitors, page views, most read pages, sources and visitors right now.')]
#[MimeType('application/json')]
class StatsTodayResource extends Resource
{
    public function handle(Request $request): Response
    {
        $token = app(CurrentToken::class);

        if ($token->record() !== null && ! $token->can('stats:read')) {
            return Response::error('This token may not read statistics.');
        }

        $report = app(Stats::class)->period('today');

        return Response::json([
            'date' => $report->from->toDateString(),
            'visitors' => $report->visitors(),
            'pageviews' => $report->pageviews(),
            'top_pages' => array_map(fn ($p) => ['path' => $p['path'], 'title' => $p['entry']?->title(), 'visitors' => $p['visitors']], $report->pages(5)),
            'top_sources' => $report->top('referrers', 5),
            'right_now' => app(Stats::class)->realtime(),
        ]);
    }
}

<?php

namespace Wake\Stats;

use Carbon\CarbonImmutable;
use JsonSerializable;
use Wake\Content\Entry;
use Wake\Content\Index;
use Wake\Routing\Resolver;

/**
 * Statistics for a range of days, with helpers for the questions people ask.
 */
class Report implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, array{pageviews: int, visitors: int}>  $series
     */
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        protected array $summary,
        protected array $series,
    ) {}

    public function pageviews(): int
    {
        return $this->summary['totals']['pageviews'];
    }

    public function visitors(): int
    {
        return $this->summary['totals']['visitors'];
    }

    /**
     * Average active reading time per page view in seconds.
     */
    public function averageDuration(): int
    {
        $leaves = $this->summary['totals']['leaves'];

        return $leaves > 0 ? (int) round($this->summary['totals']['duration'] / $leaves) : 0;
    }

    /**
     * Share of visitors counted with consent (level 2), from 0 to 1.
     */
    public function consentShare(): float
    {
        return $this->visitors() > 0 ? $this->summary['totals']['level2_visitors'] / $this->visitors() : 0.0;
    }

    public function returning(): int
    {
        return $this->summary['totals']['returning'];
    }

    /**
     * @return array<string, array{pageviews: int, visitors: int}>
     */
    public function series(): array
    {
        return $this->series;
    }

    /**
     * @return array<int, int> page views per hour of day
     */
    public function hours(): array
    {
        return $this->summary['hours'];
    }

    /**
     * Pages with views, visitors, reading time and read-through rate,
     * most viewed first.
     *
     * @return list<array<string, mixed>>
     */
    public function pages(int $limit = 10): array
    {
        $pages = [];

        foreach ($this->summary['pages'] as $path => $page) {
            if ($page['pageviews'] === 0) {
                continue;
            }

            $pages[] = [
                'path' => $path,
                'pageviews' => $page['pageviews'],
                'visitors' => $page['visitors'],
                'duration' => $page['leaves'] > 0 ? (int) round($page['duration'] / $page['leaves']) : 0,
                'read_rate' => $page['leaves'] > 0 ? round($page['read'] / $page['leaves'], 2) : null,
                'entry' => $this->entryFor($path),
            ];
        }

        usort($pages, fn ($a, $b) => $b['pageviews'] <=> $a['pageviews']);

        return array_slice($pages, 0, $limit);
    }

    /**
     * Numbers for one page path.
     *
     * @return array<string, mixed>
     */
    public function page(string $path): array
    {
        $page = $this->summary['pages'][$path] ?? ['pageviews' => 0, 'visitors' => 0, 'duration' => 0, 'leaves' => 0, 'scroll' => 0, 'read' => 0];
        $sources = $this->summary['page_sources'][$path] ?? [];
        $next = $this->summary['next'][$path] ?? [];
        arsort($sources);
        arsort($next);

        return [
            'path' => $path,
            'pageviews' => $page['pageviews'],
            'visitors' => $page['visitors'],
            'duration' => $page['leaves'] > 0 ? (int) round($page['duration'] / $page['leaves']) : 0,
            'scroll' => $page['leaves'] > 0 ? (int) round($page['scroll'] / $page['leaves']) : 0,
            'read_rate' => $page['leaves'] > 0 ? round($page['read'] / $page['leaves'], 2) : null,
            'sources' => $sources,
            'next' => $next,
        ];
    }

    /**
     * Top values of a dimension: referrers, campaigns, countries, languages,
     * devices, browsers or os.
     *
     * @return array<string, int>
     */
    public function top(string $dimension, int $limit = 10): array
    {
        $values = $this->summary[$dimension] ?? [];
        arsort($values);

        return array_slice($values, 0, $limit, true);
    }

    /**
     * @return array<string, array{count: int, labels: array<string, int>}>
     */
    public function events(int $limit = 10): array
    {
        $events = $this->summary['events'];
        uasort($events, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_slice($events, 0, $limit, true);
    }

    /**
     * The entry behind a URL path, if any.
     */
    public function entryFor(string $path): ?Entry
    {
        [$language, $rest] = app(Resolver::class)->split($path);
        $id = app(Index::class)->route($language, $rest);
        $row = $id ? app(Index::class)->find($id) : null;

        return $row ? Entry::make($row, $language) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'pageviews' => $this->pageviews(),
            'visitors' => $this->visitors(),
            'average_reading_seconds' => $this->averageDuration(),
            'consent_share' => round($this->consentShare(), 2),
            'series' => $this->series,
            'pages' => array_map(fn ($p) => [...$p, 'entry' => $p['entry']?->id()], $this->pages()),
            'referrers' => $this->top('referrers'),
            'campaigns' => $this->top('campaigns'),
            'countries' => $this->top('countries'),
            'languages' => $this->top('languages'),
            'devices' => $this->top('devices'),
            'browsers' => $this->top('browsers'),
            'events' => $this->events(),
        ];
    }
}

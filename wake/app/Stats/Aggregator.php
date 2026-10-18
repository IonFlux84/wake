<?php

namespace Wake\Stats;

/**
 * Turns the raw hits of one day into a summary: counters per page, source,
 * campaign, country, language, device, browser and event, plus reading time
 * and scroll depth per page. Summaries are small (usually well below 50 KB)
 * and are what the dashboard reads.
 */
class Aggregator
{
    /** A page counts as "read" when the visitor scrolled at least this far. */
    public const READ_THROUGH = 90;

    public function __construct(protected RawLog $raw) {}

    /**
     * @return array<string, mixed>
     */
    public function day(string $day): array
    {
        $summary = static::empty($day);
        $visitors = [];
        $sets = [];
        $lastPage = [];

        foreach ($this->raw->hits($day) as $hit) {
            $v = $hit->visitor;

            if ($hit->type === 'pageview') {
                $summary['totals']['pageviews']++;
                $summary['hours'][(int) date('G', $hit->time)]++;
                $visitors[$v] = true;

                if ($hit->level === 2) {
                    $sets['level2'][$v] = true;

                    if ($hit->returning === 'returning') {
                        $sets['returning'][$v] = true;
                    }
                }

                $page = &$summary['pages'][$hit->path];
                $page ??= ['pageviews' => 0, 'visitors' => 0, 'duration' => 0, 'leaves' => 0, 'scroll' => 0, 'read' => 0];
                $page['pageviews']++;
                $sets['page'][$hit->path][$v] = true;
                unset($page);

                // Where readers came from, and where they went next.
                if ($hit->referrer !== '') {
                    $this->count($summary['referrers'], $hit->referrer);
                    $this->count($summary['page_sources'][$hit->path], $hit->referrer);
                } elseif (! isset($lastPage[$v])) {
                    $this->count($summary['referrers'], '');
                    $this->count($summary['page_sources'][$hit->path], '');
                }

                if (isset($lastPage[$v]) && $lastPage[$v] !== $hit->path) {
                    $this->count($summary['next'][$lastPage[$v]], $hit->path);
                }

                $lastPage[$v] = $hit->path;

                if ($hit->source !== '' || $hit->campaign !== '') {
                    $this->count($summary['campaigns'], implode(' / ', array_filter([$hit->source, $hit->medium, $hit->campaign], 'strlen')));
                }

                foreach (['countries' => $hit->country, 'languages' => $hit->language, 'devices' => $hit->device,
                    'browsers' => $hit->browser, 'os' => $hit->os] as $dimension => $value) {
                    $sets[$dimension][$value !== '' ? $value : '?'][$v] = true;
                }
            } elseif ($hit->type === 'leave') {
                $page = &$summary['pages'][$hit->path];
                $page ??= ['pageviews' => 0, 'visitors' => 0, 'duration' => 0, 'leaves' => 0, 'scroll' => 0, 'read' => 0];
                $page['leaves']++;
                $page['duration'] += $hit->duration;
                $page['scroll'] += $hit->scroll;
                $page['read'] += $hit->scroll >= self::READ_THROUGH ? 1 : 0;
                unset($page);

                $summary['totals']['duration'] += $hit->duration;
                $summary['totals']['leaves']++;
            } elseif ($hit->type === 'event' && $hit->event !== '') {
                $event = &$summary['events'][$hit->event];
                $event ??= ['count' => 0, 'labels' => []];
                $event['count']++;

                if ($hit->label !== '') {
                    $this->count($event['labels'], $hit->label);
                }

                unset($event);
            }
        }

        $summary['totals']['visitors'] = count($visitors);
        $summary['totals']['level2_visitors'] = count($sets['level2'] ?? []);
        $summary['totals']['returning'] = count($sets['returning'] ?? []);

        foreach ($sets['page'] ?? [] as $path => $set) {
            $summary['pages'][$path]['visitors'] = count($set);
        }

        foreach (['countries', 'languages', 'devices', 'browsers', 'os'] as $dimension) {
            foreach ($sets[$dimension] ?? [] as $value => $set) {
                $summary[$dimension][$value] = count($set);
            }
        }

        foreach (['next', 'page_sources'] as $key) {
            foreach ($summary[$key] as $path => $counts) {
                arsort($counts);
                $summary[$key][$path] = array_slice($counts, 0, 10, true);
            }
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public static function empty(string $day): array
    {
        return [
            'date' => $day,
            'totals' => ['pageviews' => 0, 'visitors' => 0, 'level2_visitors' => 0, 'returning' => 0, 'duration' => 0, 'leaves' => 0],
            'hours' => array_fill(0, 24, 0),
            'pages' => [],
            'referrers' => [],
            'campaigns' => [],
            'countries' => [],
            'languages' => [],
            'devices' => [],
            'browsers' => [],
            'os' => [],
            'events' => [],
            'next' => [],
            'page_sources' => [],
        ];
    }

    /**
     * Add one summary into another. Visitors are added up per day, so a
     * person who comes on two days counts twice, as in most analytics tools
     * without cookies.
     *
     * @param  array<string, mixed>  $into
     * @param  array<string, mixed>  $day
     */
    public static function merge(array &$into, array $day): void
    {
        foreach ($day['totals'] as $key => $value) {
            $into['totals'][$key] = ($into['totals'][$key] ?? 0) + $value;
        }

        foreach ($day['hours'] as $hour => $value) {
            $into['hours'][$hour] += $value;
        }

        foreach ($day['pages'] as $path => $page) {
            foreach ($page as $key => $value) {
                $into['pages'][$path][$key] = ($into['pages'][$path][$key] ?? 0) + $value;
            }
        }

        foreach (['referrers', 'campaigns', 'countries', 'languages', 'devices', 'browsers', 'os'] as $dimension) {
            foreach ($day[$dimension] as $key => $value) {
                $into[$dimension][$key] = ($into[$dimension][$key] ?? 0) + $value;
            }
        }

        foreach (['next', 'page_sources'] as $dimension) {
            foreach ($day[$dimension] as $path => $counts) {
                foreach ($counts as $key => $value) {
                    $into[$dimension][$path][$key] = ($into[$dimension][$path][$key] ?? 0) + $value;
                }
            }
        }

        foreach ($day['events'] as $name => $event) {
            $into['events'][$name]['count'] = ($into['events'][$name]['count'] ?? 0) + $event['count'];

            foreach ($event['labels'] as $label => $value) {
                $into['events'][$name]['labels'][$label] = ($into['events'][$name]['labels'][$label] ?? 0) + $value;
            }

            $into['events'][$name]['labels'] ??= [];
        }
    }

    /**
     * @param  array<string, int>|null  $counter
     */
    protected function count(?array &$counter, string $key): void
    {
        $counter ??= [];
        $counter[$key] = ($counter[$key] ?? 0) + 1;
    }
}

<?php

namespace Wake\Stats;

use Carbon\CarbonImmutable;

/**
 * Turns numbers into sentences, e.g. "Hello world is doing three times as
 * well as usual today. Most readers come from mastodon.social."
 */
class Insights
{
    public function __construct(protected Stats $stats) {}

    /**
     * The most remarkable thing about today, or null if nothing stands out.
     */
    public function today(): ?string
    {
        $today = $this->stats->period('today');
        $top = $today->pages(1)[0] ?? null;

        if ($top === null || $top['visitors'] < 3) {
            return null;
        }

        $week = $this->stats->report(CarbonImmutable::now()->subDays(7), CarbonImmutable::now()->subDay());
        $usual = $week->page($top['path'])['visitors'] / 7;
        $factor = $usual > 0 ? $top['visitors'] / $usual : null;
        $title = $top['entry']?->title() ?? $top['path'];
        $source = array_key_first(array_filter($today->page($top['path'])['sources'], fn ($k) => $k !== '', ARRAY_FILTER_USE_KEY));

        if ($factor !== null && $factor < 1.5) {
            return null;
        }

        $sentence = $factor === null
            ? __(':title is new in the top spot today.', ['title' => $title])
            : __(':title is doing :factor times as well as usual today.', ['title' => $title, 'factor' => number_format($factor, $factor < 10 ? 1 : 0, app()->getLocale() === 'de' ? ',' : '.', '')]);

        if ($source) {
            $sentence .= ' '.__('Most readers come from :source.', ['source' => $source]);
        }

        return $sentence;
    }

    /**
     * Change from one value to another in percent, or null without a base.
     */
    public static function delta(int|float $previous, int|float $current): ?int
    {
        return $previous > 0 ? (int) round(($current - $previous) / $previous * 100) : null;
    }
}

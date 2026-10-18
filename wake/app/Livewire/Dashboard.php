<?php

namespace Wake\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Wake\Content\Models\Post;
use Wake\Stats\Insights;
use Wake\Stats\Stats;

/**
 * The page you open every morning: visitors, what is being read, where
 * readers come from and what is happening right now.
 */
class Dashboard extends Component
{
    public const PERIODS = ['7d' => 7, '30d' => 30, '90d' => 90];

    #[Url]
    public string $period = '7d';

    public function setPeriod(string $period): void
    {
        $this->period = array_key_exists($period, self::PERIODS) ? $period : '7d';
    }

    public function render(Stats $stats, Insights $insights): View
    {
        $days = self::PERIODS[$this->period] ?? 7;
        $today = CarbonImmutable::now()->startOfDay();
        $report = $stats->report($today->subDays($days - 1), $today);
        $previous = $stats->report($today->subDays(2 * $days - 1), $today->subDays($days));

        return view('wake::livewire.dashboard', [
            'report' => $report,
            'previous' => $previous,
            'deltas' => [
                'visitors' => Insights::delta($previous->visitors(), $report->visitors()),
                'pageviews' => Insights::delta($previous->pageviews(), $report->pageviews()),
                'duration' => Insights::delta($previous->averageDuration(), $report->averageDuration()),
            ],
            'insight' => $insights->today(),
            'live' => $stats->realtime(),
            'drafts' => Post::drafts()->latest()->limit(3)->get(),
            'scheduled' => Post::query()->scheduled()->oldest()->limit(3)->get(),
            'latest' => Post::published()->latest()->limit(3)->get(),
        ]);
    }
}

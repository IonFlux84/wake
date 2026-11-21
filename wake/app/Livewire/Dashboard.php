<?php

namespace Wake\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Wake\Content\LegalPages;
use Wake\Content\Models\Post;
use Wake\Plugins\Plugins;
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

    /** Creates the missing imprint and privacy policy as drafts. */
    public function createLegalPages(LegalPages $legal): void
    {
        abort_unless(auth()->user()->can('content.edit_all') || auth()->user()->can('settings.manage'), 403);

        $legal->ensure();
    }

    public function setPeriod(string $period): void
    {
        $this->period = array_key_exists($period, self::PERIODS) ? $period : '7d';
    }

    public function render(Stats $stats, Insights $insights, Plugins $plugins): View
    {
        $user = auth()->user();
        $boards = array_values(array_filter($plugins->dashboard(), fn (array $board) => $board['capability'] === null || $user->can($board['capability'])));
        $statsReplaced = in_array('stats', array_column($boards, 'replaces'), true);

        $data = [
            'boards' => $boards,
            'statsReplaced' => $statsReplaced,
            'insight' => $insights->today(),
            'live' => $stats->realtime(),
            'drafts' => Post::drafts()->latest()->limit(3)->get(),
            'scheduled' => Post::query()->scheduled()->oldest()->limit(3)->get(),
            'latest' => Post::published()->latest()->limit(3)->get(),
        ];

        // A plugin board shows the numbers instead: skip reading the period twice.
        if (! $statsReplaced && $user->can('stats.view')) {
            $days = self::PERIODS[$this->period] ?? 7;
            $today = CarbonImmutable::now()->startOfDay();
            $report = $stats->report($today->subDays($days - 1), $today);
            $previous = $stats->report($today->subDays(2 * $days - 1), $today->subDays($days));

            $data += [
                'report' => $report,
                'previous' => $previous,
                'deltas' => [
                    'visitors' => Insights::delta($previous->visitors(), $report->visitors()),
                    'pageviews' => Insights::delta($previous->pageviews(), $report->pageviews()),
                    'duration' => Insights::delta($previous->averageDuration(), $report->averageDuration()),
                ],
            ];
        }

        return view('wake::livewire.dashboard', $data);
    }
}

<?php

namespace Wake\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Wake\Stats\Stats;

/**
 * The statistics page: every number for a period, by page, source,
 * campaign, country, device, language and event, plus AI crawlers.
 */
class Statistics extends Component
{
    public const PERIODS = ['today', '7d', '30d', '90d', '12m'];

    #[Url]
    public string $period = '30d';

    public function setPeriod(string $period): void
    {
        $this->period = in_array($period, self::PERIODS, true) ? $period : '30d';
    }

    public function render(Stats $stats): View
    {
        $report = $stats->period($this->period);

        return view('wake::livewire.statistics', [
            'report' => $report,
            'ai' => $stats->aiCrawlers($report->from, $report->to),
        ]);
    }
}

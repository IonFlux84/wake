<?php

namespace Wake\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Wake\Stats\Stats;

/**
 * The wake of a page ("Kielwasser"): where its readers came from, how they
 * read it and what they opened next.
 */
class Journey extends Component
{
    #[Url]
    public string $path = '/';

    #[Url]
    public string $period = '30d';

    public function setPeriod(string $period): void
    {
        $this->period = in_array($period, Statistics::PERIODS, true) ? $period : '30d';
    }

    public function render(Stats $stats): View
    {
        $report = $stats->period($this->period);

        return view('wake::livewire.journey', [
            'report' => $report,
            'page' => $report->page($this->path),
            'entry' => $report->entryFor($this->path),
            'series' => $stats->pageSeries($this->path, $report->from, $report->to),
        ]);
    }
}

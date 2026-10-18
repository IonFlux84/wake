<?php

namespace Wake\Livewire;

use Illuminate\View\View;
use Livewire\Component;
use Wake\Stats\Stats;

/**
 * "Right now": visitors of the last five minutes, refreshed every 15 seconds.
 */
class LiveVisitors extends Component
{
    public function render(Stats $stats): View
    {
        return view('wake::livewire.live-visitors', ['live' => $stats->realtime()]);
    }
}

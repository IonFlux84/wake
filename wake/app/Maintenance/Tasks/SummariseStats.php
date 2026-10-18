<?php

namespace Wake\Maintenance\Tasks;

use Wake\Maintenance\Deadline;
use Wake\Maintenance\Task;
use Wake\Stats\Stats;

/**
 * Summarises finished days and months, then deletes old raw data and
 * yesterday's salt.
 */
class SummariseStats implements Task
{
    public function __construct(protected Stats $stats) {}

    public function run(Deadline $deadline): void
    {
        $this->stats->summarise(fn () => $deadline->expired());

        if (! $deadline->expired()) {
            $this->stats->prune();
        }
    }
}

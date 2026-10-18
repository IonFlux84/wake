<?php

namespace Wake\Maintenance;

/**
 * A piece of housekeeping. Tasks must be quick or able to stop early:
 * check $deadline->expired() inside loops and continue on the next run.
 */
interface Task
{
    public function run(Deadline $deadline): void;
}

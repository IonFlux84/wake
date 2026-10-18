<?php

namespace Wake\Maintenance;

class Deadline
{
    protected float $end;

    public function __construct(float $seconds)
    {
        $this->end = microtime(true) + $seconds;
    }

    public function expired(): bool
    {
        return microtime(true) >= $this->end;
    }
}

<?php

namespace Wake\Console\Commands;

use Illuminate\Console\Command;
use Wake\Maintenance\Maintenance;

class MaintenanceCommand extends Command
{
    protected $signature = 'wake:maintenance';

    protected $description = 'Run the maintenance tasks now (scheduled publishing and clean-up)';

    public function handle(Maintenance $maintenance): int
    {
        $maintenance->run();

        $this->components->info('Maintenance finished.');

        return self::SUCCESS;
    }
}

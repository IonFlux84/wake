<?php

namespace Wake\Console\Commands;

use Illuminate\Console\Command;
use Wake\Backup\Backups;

class BackupCommand extends Command
{
    protected $signature = 'wake:backup {--stats : Include the statistics}';

    protected $description = 'Write a backup ZIP to data/backups';

    public function handle(Backups $backups): int
    {
        $name = $backups->create((bool) $this->option('stats'));
        $this->components->info('Backup written: '.$backups->directory().'/'.$name);

        return self::SUCCESS;
    }
}

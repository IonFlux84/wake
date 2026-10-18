<?php

namespace Wake\Console\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Wake\Backup\Backups;

class RestoreCommand extends Command
{
    protected $signature = 'wake:restore {file : A backup ZIP, as a path or a name in data/backups} {--force : Do not ask}';

    protected $description = 'Replace content, settings and accounts with a backup';

    public function handle(Backups $backups): int
    {
        $file = $this->argument('file');
        $file = is_file($file) ? $file : $backups->path($file);

        if ($file === null) {
            $this->components->error('There is no such backup.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Replace content, settings and accounts with this backup? The current state is saved as a backup first.')) {
            return self::FAILURE;
        }

        try {
            $safety = $backups->restore($file);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Backup restored. The previous state is in data/backups/{$safety}.");

        return self::SUCCESS;
    }
}

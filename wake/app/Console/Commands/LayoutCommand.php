<?php

namespace Wake\Console\Commands;

use Illuminate\Console\Command;
use Wake\Content\Index;
use Wake\Support\LayoutMigration;
use Wake\Support\Paths;

class LayoutCommand extends Command
{
    protected $signature = 'wake:layout {--apply : Move the files (without it, only list what would happen)} {--no-backup : Skip the backup before moving} {--root= : The site folder to move (default: the folder around wake/)}';

    protected $description = 'Move a site from the old folder layout to the current one';

    public function handle(): int
    {
        $root = $this->option('root') ?: Paths::root();
        $migration = new LayoutMigration(rtrim($root, '/'));
        $steps = $migration->run(false);

        if ($steps === []) {
            $this->components->info('This site already uses the current folder layout.');

            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->line(implode(PHP_EOL, $steps));
            $this->newLine();
            $this->components->info(count($steps).' steps. Run again with --apply to do them.');

            return self::SUCCESS;
        }

        if (! $this->option('no-backup') && ($zip = $migration->backup())) {
            $this->components->info('Backup of the old layout written: '.substr($zip, strlen($root) + 1));
        }

        $migration->run(true);

        foreach ($migration->warnings as $warning) {
            $this->components->warn($warning);
        }

        app(Index::class)->forget();
        app(Index::class)->refresh(true);
        $this->call('wake:clear'); // also clears compiled views, which may have come along from storage/
        $this->components->info(count($migration->steps).' steps done.');

        return self::SUCCESS;
    }
}

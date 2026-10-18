<?php

namespace Wake\Console\Commands;

use Illuminate\Console\Command;
use Wake\Content\Index;

class IndexCommand extends Command
{
    protected $signature = 'wake:index {--force : Parse every file again, even unchanged ones}';

    protected $description = 'Update the content index after changing files';

    public function handle(Index $index): int
    {
        $changed = $index->refresh((bool) $this->option('force'));
        $entries = count($index->entries());

        $this->components->info($changed ? "Index updated: {$entries} entries." : "Index is up to date: {$entries} entries.");

        foreach ($index->warnings() as $warning) {
            $this->components->warn($warning);
        }

        return self::SUCCESS;
    }
}

<?php

namespace Wake\Console\Commands;

use Illuminate\Console\Command;
use Wake\Cache\PageCache;

class ClearCacheCommand extends Command
{
    protected $signature = 'wake:clear';

    protected $description = 'Remove all cached pages';

    public function handle(PageCache $cache): int
    {
        $cache->flush();

        $this->components->info('Page cache cleared.');

        return self::SUCCESS;
    }
}

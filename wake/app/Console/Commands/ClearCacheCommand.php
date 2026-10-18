<?php

namespace Wake\Console\Commands;

use Illuminate\Console\Command;
use Wake\Cache\PageCache;

class ClearCacheCommand extends Command
{
    protected $signature = 'wake:clear';

    protected $description = 'Remove all cached pages and compiled templates';

    public function handle(PageCache $cache): int
    {
        $cache->flush();
        $this->callSilently('view:clear'); // templates edited by FTP are compiled again on the next request

        $this->components->info('Page cache and compiled templates cleared.');

        return self::SUCCESS;
    }
}

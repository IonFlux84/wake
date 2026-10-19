<?php

namespace RedirectScout\Console;

use Illuminate\Console\Command;
use RedirectScout\Scanner;
use RedirectScout\Store;

class ScanCommand extends Command
{
    protected $signature = 'redirect-scout:scan
        {site? : The old website, e.g. https://www.example.org (default: the last one)}
        {--wayback : Also ask the Wayback Machine}
        {--no-sitemap : Skip robots.txt and sitemaps}
        {--no-crawl : Do not follow links}
        {--list= : A file with one address per line}
        {--check : Only check the addresses found before against this site}';

    protected $description = 'Find the addresses of the old website and check which ones need a redirect';

    public function handle(Scanner $scanner, Store $store): int
    {
        if ($this->option('check')) {
            $scanner->recheck();
        } else {
            $site = (string) ($this->argument('site') ?: $store->get('site'));

            if (! preg_match('#^https?://#', $site)) {
                $this->error('Give the address of the old website, e.g. https://www.example.org');

                return self::FAILURE;
            }

            $list = $this->option('list');
            $plugin = app('redirect-scout');

            $scanner->start($site, [
                'sitemap' => ! $this->option('no-sitemap'),
                'crawl' => ! $this->option('no-crawl'),
                'wayback' => (bool) $this->option('wayback'),
                'list' => $list && is_file($list) ? array_values(array_filter(array_map('trim', file($list)))) : [],
                'limit' => (int) $plugin->option('limit', 5000),
                'delay' => (int) $plugin->option('delay', 250),
            ]);
        }

        while ($scanner->running()) {
            $scanner->work(5);
            $store->reload();
            $this->output->write("\r".str_pad($store->get('phase').': '.count($store->addresses()).' addresses, '.$store->get('fetched').' visited', 60));
        }

        $this->newLine(2);
        $counts = $store->counts();
        $this->table(['Need a target', 'Redirected', 'Exist here', 'Gone', 'Ignored', 'All'], [[
            $counts['missing'], $counts['redirect'], $counts['exists'], $counts['gone'], $counts['ignored'], $counts['all'],
        ]]);
        $this->line('Give the missing ones a target in the panel under "Old addresses".');

        return self::SUCCESS;
    }
}

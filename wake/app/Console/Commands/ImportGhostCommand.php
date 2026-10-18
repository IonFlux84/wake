<?php

namespace Wake\Console\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Wake\Import\GhostImporter;

class ImportGhostCommand extends Command
{
    protected $signature = 'wake:import:ghost
        {file : The JSON file exported from Ghost (Settings → Labs → Export)}
        {--url= : Address of the old Ghost site, to download images}
        {--language= : Language of the imported content (default: the site\'s default language)}';

    protected $description = 'Import posts, pages and tags from a Ghost export';

    public function handle(GhostImporter $importer): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->components->error("File not found: {$file}");

            return self::FAILURE;
        }

        try {
            $importer->import((string) file_get_contents($file), $this->option('url'), $this->option('language'));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $c = $importer->counts;
        $this->components->info("Imported {$c['posts']} posts, {$c['pages']} pages, {$c['tags']} tags and {$c['images']} images. Skipped {$c['skipped']}.");

        foreach ($importer->warnings as $warning) {
            $this->components->warn($warning);
        }

        return self::SUCCESS;
    }
}

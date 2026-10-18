<?php

namespace Wake\Maintenance\Tasks;

use Wake\Content\FrontMatter;
use Wake\Content\Index;
use Wake\Content\Query;
use Wake\Maintenance\Deadline;
use Wake\Maintenance\Task;
use Wake\Support\Files;
use Wake\Support\Paths;

/**
 * Scheduled entries become visible on their own as soon as their date has
 * passed. This task also writes "status: published" into their files, so
 * the files tell the truth, and the resulting index change clears the page
 * cache.
 */
class PublishScheduled implements Task
{
    public function __construct(protected Index $index) {}

    public function run(Deadline $deadline): void
    {
        $changed = false;

        foreach ($this->index->entries() as $row) {
            foreach ($row['languages'] as $translation) {
                if (($translation['fields']['status'] ?? null) !== 'scheduled' || ! Query::isVisible($translation['fields'])) {
                    continue;
                }

                $path = Paths::content($translation['file']);

                Files::lock('entry-'.md5($row['id']), function () use ($path) {
                    [$fields, $body] = FrontMatter::parse((string) file_get_contents($path), $path);

                    if (($fields['status'] ?? null) === 'scheduled') {
                        $fields['status'] = 'published';
                        Files::write($path, FrontMatter::dump($fields, $body));
                    }
                });

                $changed = true;
                $this->index->invalidate([$path]);

                if ($deadline->expired()) {
                    break 2;
                }
            }
        }

        if ($changed) {
            $this->index->refresh();
        }
    }
}

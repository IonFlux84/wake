<?php

namespace Wake\Content;

use Carbon\CarbonImmutable;
use Wake\Support\Files;
use Wake\Support\Paths;

/**
 * Earlier versions of content files in data/versions/{file hash}/.
 * Wake keeps the last 20 versions, and anything younger than 30 days.
 */
class Revisions
{
    public const KEEP = 20;

    public const DAYS = 30;

    public function keep(string $file, string $contents): void
    {
        $dir = $this->directory($file);
        $name = CarbonImmutable::now()->format('Ymd-His').'-'.substr(md5($contents), 0, 6).'.md';

        Files::write($dir.'/'.$name, $contents);
        Files::write($dir.'/.file', $file);
        $this->prune($file);
    }

    /**
     * @return list<array{id: string, time: CarbonImmutable, size: int}> newest first
     */
    public function of(string $file): array
    {
        $revisions = [];

        foreach (glob($this->directory($file).'/*.md') ?: [] as $path) {
            $id = basename($path, '.md');
            $revisions[] = [
                'id' => $id,
                'time' => CarbonImmutable::createFromFormat('Ymd-His', substr($id, 0, 15), config('app.timezone')),
                'size' => (int) filesize($path),
            ];
        }

        usort($revisions, fn ($a, $b) => strcmp($b['id'], $a['id']));

        return $revisions;
    }

    public function get(string $file, string $id): ?string
    {
        $path = $this->directory($file).'/'.preg_replace('/[^0-9a-f-]/', '', $id).'.md';

        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    protected function prune(string $file): void
    {
        $limit = CarbonImmutable::now()->subDays(self::DAYS);

        foreach (array_slice($this->of($file), self::KEEP) as $revision) {
            if ($revision['time'] < $limit) {
                @unlink($this->directory($file).'/'.$revision['id'].'.md');
            }
        }
    }

    /**
     * Keeps the versions of a file that was moved to another place.
     */
    public function move(string $from, string $to): void
    {
        if ($from !== $to && is_dir($old = $this->directory($from)) && ! is_dir($new = $this->directory($to))) {
            rename($old, $new);
        }
    }

    protected function directory(string $file): string
    {
        return Paths::data('versions/'.md5($file));
    }
}

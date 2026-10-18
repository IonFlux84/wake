<?php

namespace Wake\Backup;

use Carbon\CarbonImmutable;
use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Wake\Cache\PageCache;
use Wake\Content\Index;
use Wake\Support\Files;
use Wake\Support\Paths;
use Wake\Wake;
use ZipArchive;

/**
 * Backups as ZIP files in data/backups: content, settings, themes, plugins,
 * accounts, consent records, versions, history and mail settings, and
 * optionally the statistics. temp/, the trash and .env are left out.
 *
 *     wake-backup.json        format, Wake version, date, contents
 *     content/…
 *     settings/…
 *     data/accounts/…
 */
class Backups
{
    public const FORMAT = 1;

    /** Folders in data/ that belong in every backup (statistics are optional). */
    public const DATA = ['accounts', 'consent', 'history', 'versions', 'secrets'];

    public static function available(): bool
    {
        return class_exists(ZipArchive::class);
    }

    /**
     * Stored backups, newest first.
     *
     * @return list<array{name: string, size: int, time: int}>
     */
    public function all(): array
    {
        $files = glob($this->directory().'/*.zip') ?: [];
        $backups = array_map(fn (string $file) => ['name' => basename($file), 'size' => (int) filesize($file), 'time' => (int) filemtime($file)], $files);
        usort($backups, fn ($a, $b) => $b['time'] <=> $a['time'] ?: strcmp($b['name'], $a['name']));

        return $backups;
    }

    /**
     * Writes a new backup and returns its file name.
     */
    public function create(bool $withStats = false, string $note = ''): string
    {
        $this->guard();
        @set_time_limit(0);

        $base = 'wake-backup-'.CarbonImmutable::now()->format('Y-m-d-His').($note !== '' ? '-'.$note : '');
        for ($name = $base.'.zip', $i = 2; is_file($this->directory().'/'.$name); $i++) {
            $name = $base.'-'.$i.'.zip';
        }
        $target = $this->directory().'/'.$name;
        @mkdir($this->directory(), 0775, true);

        $temp = $target.'.part';
        $archive = new ZipArchive;

        if ($archive->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not write {$temp}.");
        }

        $areas = $this->areas($withStats);

        foreach ($areas as $prefix => $dir) {
            $this->addFolder($archive, $dir, $prefix);
        }

        $archive->addFromString('wake-backup.json', json_encode([
            'format' => self::FORMAT,
            'wake' => Wake::VERSION,
            'created' => CarbonImmutable::now()->toIso8601String(),
            'site' => config('app.name'),
            'contains' => array_keys($areas),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $archive->close();
        rename($temp, $target);

        return $name;
    }

    /**
     * The full path of a stored backup, or null for names that are not one.
     */
    public function path(string $name): ?string
    {
        if (! preg_match('/^[a-z0-9][a-z0-9._-]*\.zip$/i', $name) || ! is_file($file = $this->directory().'/'.$name)) {
            return null;
        }

        return $file;
    }

    /**
     * Moves a backup to the trash.
     */
    public function delete(string $name): void
    {
        if ($file = $this->path($name)) {
            @mkdir(Paths::data('trash'), 0775, true);
            rename($file, Paths::data('trash/'.CarbonImmutable::now()->format('Y-m-d-His').'-'.$name));
        }
    }

    /**
     * Keeps an uploaded ZIP in data/backups after checking it.
     */
    public function store(string $source, string $originalName): string
    {
        $this->inspect($source);
        $name = preg_replace('/[^a-z0-9._-]+/i', '-', pathinfo($originalName, PATHINFO_FILENAME)) ?: 'upload';
        $name = 'uploaded-'.CarbonImmutable::now()->format('Y-m-d-His').'-'.trim($name, '-').'.zip';
        @mkdir($this->directory(), 0775, true);
        copy($source, $this->directory().'/'.$name);

        return $name;
    }

    /**
     * Reads and checks the manifest of a backup.
     *
     * @return array<string, mixed>
     */
    public function inspect(string $file): array
    {
        $this->guard();
        $archive = new ZipArchive;

        if ($archive->open($file) !== true) {
            throw new InvalidArgumentException(__('This is not a ZIP file.'));
        }

        $manifest = json_decode((string) $archive->getFromName('wake-backup.json'), true);

        if (! is_array($manifest) || ($manifest['format'] ?? null) !== self::FORMAT) {
            $archive->close();

            throw new InvalidArgumentException(__('This ZIP is not a Wake backup.'));
        }

        $allowed = array_keys($this->areas(true));

        for ($i = 0; $i < $archive->numFiles; $i++) {
            $entry = (string) $archive->getNameIndex($i);

            if ($entry === 'wake-backup.json') {
                continue;
            }

            $safe = ! str_contains($entry, '..') && ! str_starts_with($entry, '/') && ! str_contains($entry, '\\') && ! str_contains($entry, ':');
            $known = collect($allowed)->contains(fn (string $prefix) => str_starts_with($entry, $prefix.'/'));

            if (! $safe || ! $known) {
                $archive->close();

                throw new InvalidArgumentException(__('The ZIP contains unsafe paths.'));
            }
        }

        $archive->close();

        return $manifest;
    }

    /**
     * Replaces the site with a backup. The current state is saved as a backup
     * first, and the replaced folders go to the trash.
     */
    public function restore(string $file): string
    {
        $manifest = $this->inspect($file);
        @set_time_limit(0);

        $safety = $this->create(in_array('data/stats', $manifest['contains'] ?? [], true), 'before-restore');
        $temp = storage_path('updates/restore-'.bin2hex(random_bytes(4)));
        $trash = Paths::data('trash/'.CarbonImmutable::now()->format('Y-m-d-His').'-before-restore');

        $archive = new ZipArchive;
        $archive->open($file);
        $archive->extractTo($temp);
        $archive->close();

        Files::lock('restore', function () use ($temp, $trash) {
            foreach ($this->areas(true) as $prefix => $dir) {
                if (! is_dir($temp.'/'.$prefix)) {
                    continue; // e.g. a backup without statistics keeps the current ones
                }

                if (is_dir($dir)) {
                    @mkdir(dirname($trash.'/'.$prefix), 0775, true);
                    rename($dir, $trash.'/'.$prefix);
                }

                @mkdir(dirname($dir), 0775, true);
                rename($temp.'/'.$prefix, $dir);
            }
        });

        Files::deleteDirectory($temp);
        app(Index::class)->refresh(true);
        app(PageCache::class)->flush();

        return $safety;
    }

    public function directory(): string
    {
        return Paths::data('backups');
    }

    /**
     * What goes into a backup: path in the ZIP => folder on disk.
     *
     * @return array<string, string>
     */
    protected function areas(bool $withStats): array
    {
        $areas = [
            'content' => Paths::content(),
            'settings' => Paths::settings(),
            'themes' => Paths::themes(),
            'plugins' => Paths::plugins(),
        ];

        foreach ([...self::DATA, ...($withStats ? ['stats'] : [])] as $folder) {
            $areas['data/'.$folder] = Paths::data($folder);
        }

        return $areas;
    }

    protected function addFolder(ZipArchive $archive, string $dir, string $prefix): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->isFile()) {
                $archive->addFile($file->getPathname(), $prefix.'/'.str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1)));
            }
        }
    }

    protected function guard(): void
    {
        if (! self::available()) {
            throw new RuntimeException(__('Backups need the PHP extension zip, which is missing on this server.'));
        }
    }
}

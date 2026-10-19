<?php

namespace Wake\Update;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Wake\Cache\PageCache;
use Wake\Support\Files;
use Wake\Support\Paths;
use Wake\Wake;
use ZipArchive;

/**
 * Updates Wake from a GitHub release: the installation ZIP and its SHA-256
 * checksum. Only the wake/ folder, the files Wake keeps in public/ and the
 * main folder, and the folders of the plugins that ship with Wake are
 * replaced. Wake only contacts GitHub when someone clicks "Check for updates".
 */
class Updater
{
    /** Files outside wake/ that belong to Wake and are replaced as well. */
    public const OWN_FILES = ['public/index.php', 'public/hit.php', 'public/.htaccess', '.htaccess'];

    /** The site folder that contains wake/. Only tests point it elsewhere. */
    public ?string $root = null;

    /**
     * The latest release, or null when Wake is up to date.
     *
     * @return array{version: string, notes: string, zip: string, checksum: string}|null
     */
    public function check(): ?array
    {
        $response = Http::timeout(15)->acceptJson()
            ->get('https://api.github.com/repos/'.config('wake.update.repository').'/releases/latest');

        if (! $response->successful()) {
            throw new RuntimeException(__('GitHub could not be reached (status :status).', ['status' => $response->status()]));
        }

        $version = ltrim((string) $response->json('tag_name'), 'v');
        $assets = collect($response->json('assets', []));
        $zip = $assets->first(fn ($a) => str_ends_with($a['name'], '.zip'))['browser_download_url'] ?? null;
        $checksum = $assets->first(fn ($a) => str_ends_with($a['name'], '.zip.sha256'))['browser_download_url'] ?? null;

        if (! $zip || ! $checksum || version_compare($version, Wake::VERSION, '<=')) {
            return null;
        }

        return ['version' => $version, 'notes' => (string) $response->json('body'), 'zip' => $zip, 'checksum' => $checksum];
    }

    /**
     * Download, verify, unpack and switch. Returns the folder with the backup
     * of the previous version.
     *
     * @param  array{version: string, zip: string, checksum: string}  $release
     */
    public function install(array $release): string
    {
        return Files::lock('update', function () use ($release) {
            $work = storage_path('updates/'.$release['version']);
            Files::deleteDirectory($work);
            @mkdir($work, 0775, true);

            $zip = $work.'/wake.zip';
            Http::timeout(120)->sink($zip)->get($release['zip'])->throw();
            $expected = strtolower(strtok(trim((string) Http::timeout(15)->get($release['checksum'])->throw()->body()), " \t"));

            if (! hash_equals($expected, hash_file('sha256', $zip))) {
                throw new RuntimeException(__('The download does not match its checksum. Nothing was changed.'));
            }

            $archive = new ZipArchive;

            if ($archive->open($zip) !== true || ! $archive->extractTo($work.'/unpacked')) {
                throw new RuntimeException(__('The update could not be unpacked. Nothing was changed.'));
            }

            $archive->close();
            $source = glob($work.'/unpacked/*', GLOB_ONLYDIR)[0] ?? '';

            if (! is_file($source.'/wake/bootstrap/app.php') || ! is_file($source.'/wake/vendor/autoload.php')) {
                throw new RuntimeException(__('The update is incomplete. Nothing was changed.'));
            }

            return $this->switch($source, storage_path('updates/backup-'.Wake::VERSION.'-'.date('YmdHis')));
        });
    }

    /**
     * Put the new wake/ folder in place. Renames are atomic, so the site is
     * never half updated.
     */
    protected function switch(string $source, string $backup): string
    {
        $root = $this->root ?? Paths::root();
        @mkdir($backup, 0775, true);

        foreach (self::OWN_FILES as $file) {
            if (is_file($root.'/'.$file)) {
                Files::copy($root.'/'.$file, $backup.'/'.str_replace('/', '__', $file));
            }
        }

        if (! rename($root.'/wake', $backup.'/wake')) {
            throw new RuntimeException(__('The current version could not be moved aside. Nothing was changed.'));
        }

        if (! rename($source.'/wake', $root.'/wake')) {
            rename($backup.'/wake', $root.'/wake');

            throw new RuntimeException(__('The new version could not be put in place. The old one is back.'));
        }

        foreach (self::OWN_FILES as $file) {
            if (is_file($source.'/'.$file)) {
                Files::copy($source.'/'.$file, $root.'/'.$file);
            }
        }

        $this->switchPlugins($source, $backup);

        Files::deleteDirectory(storage_path('framework/views'));
        @mkdir(storage_path('framework/views'), 0775, true);
        app(PageCache::class)->flush();

        return $backup;
    }

    /**
     * The plugins in the release's plugins/ folder replace their folders in
     * the site; the old folders go into the backup. Whether a plugin is
     * switched on, and its settings, stay as they were.
     */
    protected function switchPlugins(string $source, string $backup): void
    {
        $plugins = $this->root !== null ? $this->root.'/plugins' : Paths::plugins();
        @mkdir($plugins, 0775, true);

        foreach (glob($source.'/plugins/*/plugin.php') ?: [] as $file) {
            $folder = basename(dirname($file));

            if (is_dir($plugins.'/'.$folder)) {
                @mkdir($backup.'/plugins', 0775, true);

                if (! rename($plugins.'/'.$folder, $backup.'/plugins/'.$folder)) {
                    continue;
                }
            }

            rename(dirname($file), $plugins.'/'.$folder);
        }
    }
}

<?php

namespace Wake\Theme;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;
use Wake\Support\Files;
use Wake\Support\Paths;
use ZipArchive;

/**
 * All themes in site/themes/: list, copy, install from a ZIP, remove.
 */
class Themes
{
    /**
     * @return array<string, ThemeInfo>
     */
    public function all(): array
    {
        $themes = [];

        foreach (glob(Paths::site('themes/*'), GLOB_ONLYDIR) ?: [] as $dir) {
            if (is_dir($dir.'/templates')) {
                $file = $dir.'/theme.yml';
                $themes[basename($dir)] = new ThemeInfo(basename($dir), $dir, is_file($file) ? (Yaml::parseFile($file) ?: []) : []);
            }
        }

        ksort($themes);

        return $themes;
    }

    public function get(string $folder): ?ThemeInfo
    {
        return $this->all()[$folder] ?? null;
    }

    /**
     * Copy a theme to adjust it without touching the original.
     */
    public function duplicate(string $folder, string $name): ThemeInfo
    {
        $source = $this->get($folder) ?? throw new InvalidArgumentException('Unknown theme.');
        $target = $this->freeFolder(Str::slug($name) ?: $folder.'-copy');

        $this->copy($source->path, Paths::site('themes/'.$target));

        $file = Paths::site("themes/{$target}/theme.yml");
        $data = is_file($file) ? (Yaml::parseFile($file) ?: []) : [];
        $data['name'] = $name;
        Files::write($file, Yaml::dump($data, 4, 2));

        return $this->get($target);
    }

    /**
     * Install a theme from a ZIP file. The ZIP must contain templates/ and
     * may contain everything inside one top folder.
     */
    public function install(string $zip): ThemeInfo
    {
        $archive = new ZipArchive;

        if ($archive->open($zip) !== true) {
            throw new InvalidArgumentException(__('This is not a ZIP file.'));
        }

        $prefix = null;

        for ($i = 0; $i < $archive->numFiles; $i++) {
            $name = $archive->getNameIndex($i);

            if (str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\')) {
                throw new InvalidArgumentException(__('The ZIP contains unsafe paths.'));
            }

            if (preg_match('#^(.*?)templates/[^/]+\.blade\.php$#', $name, $m)) {
                $prefix ??= $m[1];
            }
        }

        if ($prefix === null) {
            throw new InvalidArgumentException(__('The ZIP contains no theme: a templates folder with Blade templates is missing.'));
        }

        $temp = storage_path('updates/theme-'.bin2hex(random_bytes(4)));
        $archive->extractTo($temp);
        $archive->close();

        $source = rtrim($temp.'/'.$prefix, '/');
        $data = is_file($source.'/theme.yml') ? (Yaml::parseFile($source.'/theme.yml') ?: []) : [];
        $folder = $this->freeFolder(Str::slug($data['name'] ?? basename($prefix ?: 'theme')) ?: 'theme');

        $this->copy($source, Paths::site('themes/'.$folder));
        Files::deleteDirectory($temp);

        return $this->get($folder);
    }

    /**
     * Move an inactive theme to the trash.
     */
    public function trash(string $folder): void
    {
        if ($folder === config('wake.theme')) {
            throw new InvalidArgumentException(__('The active theme cannot be removed.'));
        }

        $target = storage_path('trash/'.date('Y-m-d-His').'-theme--'.$folder);
        @mkdir(dirname($target), 0775, true);
        rename(Paths::site('themes/'.$folder), $target);
    }

    protected function freeFolder(string $folder): string
    {
        $candidate = $folder;

        for ($i = 2; is_dir(Paths::site('themes/'.$candidate)); $i++) {
            $candidate = $folder.'-'.$i;
        }

        return $candidate;
    }

    protected function copy(string $from, string $to): void
    {
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        @mkdir($to, 0775, true);

        foreach ($items as $item) {
            $target = $to.'/'.substr($item->getPathname(), strlen($from) + 1);
            $item->isDir() ? @mkdir($target, 0775, true) : Files::copy($item->getPathname(), $target);
        }
    }
}

<?php

namespace Wake\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * File helpers that never leave a half-written file behind.
 */
class Files
{
    /**
     * Write a file atomically: write to a temporary file in the same folder,
     * then rename it over the target. Readers see the old or the new file,
     * never a mix of both.
     */
    public static function write(string $path, string $contents): void
    {
        $dir = dirname($path);

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException("Cannot create folder {$dir}.");
        }

        $temp = $dir.'/.'.basename($path).'.'.bin2hex(random_bytes(4)).'.tmp';

        if (file_put_contents($temp, $contents, LOCK_EX) === false) {
            throw new RuntimeException("Cannot write {$path}.");
        }

        @chmod($temp, 0664);

        if (! @rename($temp, $path)) {
            @unlink($temp);

            throw new RuntimeException("Cannot replace {$path}.");
        }

        if (function_exists('opcache_invalidate') && str_ends_with($path, '.php')) {
            opcache_invalidate($path, true);
        }
    }

    /**
     * Copy a file atomically.
     */
    public static function copy(string $source, string $target): void
    {
        if (! is_dir($dir = dirname($target))) {
            @mkdir($dir, 0775, true);
        }

        $temp = $dir.'/.'.basename($target).'.'.bin2hex(random_bytes(4)).'.tmp';

        if (! @copy($source, $temp) || ! @rename($temp, $target)) {
            @unlink($temp);

            throw new RuntimeException("Cannot copy {$source} to {$target}.");
        }
    }

    /**
     * Write a PHP file that returns the given array. OPcache keeps such
     * files in memory, which makes them the fastest storage Wake has.
     */
    public static function writePhp(string $path, array $data): void
    {
        static::write($path, '<?php return '.var_export($data, true).';'.PHP_EOL);
    }

    /**
     * Run a callback while holding an exclusive lock with the given name.
     * Returns null without running the callback when $wait is false and
     * someone else holds the lock.
     */
    public static function lock(string $name, callable $callback, bool $wait = true): mixed
    {
        $file = storage_path('locks/'.preg_replace('/[^a-z0-9._-]/i', '-', $name).'.lock');

        if (! is_dir(dirname($file))) {
            @mkdir(dirname($file), 0775, true);
        }

        $handle = fopen($file, 'c');

        try {
            if (! flock($handle, $wait ? LOCK_EX : LOCK_EX | LOCK_NB)) {
                return null;
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Delete a folder with everything in it.
     */
    public static function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }
}

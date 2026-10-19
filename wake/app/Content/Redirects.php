<?php

namespace Wake\Content;

use Symfony\Component\Yaml\Yaml;
use Wake\Support\Files;
use Wake\Support\Paths;

/**
 * Old addresses, kept in settings/redirects.yml. When the slug of a
 * published entry changes, Wake remembers the old address and answers it
 * with a permanent redirect to the new one. A target is an entry id, a path
 * of this site, a file in media/, another website or "gone" for 410 Gone.
 *
 *     en:
 *       old-slug: posts/2026-10-17-hello-world
 *       old/archive: /blog
 *       old/flyer.pdf: media/downloads/flyer.pdf
 *       old/page: https://example.org/elsewhere
 *       old/offer: gone
 *       strasse/*: /strassen/*
 */
class Redirects
{
    /** The target of an address that was removed on purpose (410 Gone). */
    public const GONE = 'gone';

    /** @var array<string, array<string, string>>|null */
    protected ?array $map = null;

    /**
     * @return array<string, array<string, string>>
     */
    public function all(): array
    {
        if ($this->map === null) {
            $file = $this->file();
            $this->map = is_file($file) ? (Yaml::parseFile($file) ?: []) : [];
        }

        return $this->map;
    }

    /**
     * The target for an old path. An exact address wins; otherwise the
     * longest pattern like "old/*" that covers the path. A target ending in
     * "/*" gets the rest of the path: "strasse/*: /strassen/*" leads
     * /strasse/lindenweg to /strassen/lindenweg.
     */
    public function target(string $language, string $path): ?string
    {
        $path = trim($path, '/');
        $map = $this->all()[$language] ?? [];

        if (isset($map[$path])) {
            return $map[$path];
        }

        $best = null;

        foreach ($map as $from => $target) {
            $from = (string) $from;

            // "strasse/*" also covers /strasse itself.
            if (str_ends_with($from, '/*') && str_starts_with($path.'/', substr($from, 0, -1))
                && ($best === null || strlen($from) > strlen($best))) {
                $best = $from;
            }
        }

        if ($best === null) {
            return null;
        }

        $target = (string) $map[$best];

        return str_ends_with($target, '/*')
            ? rtrim(substr($target, 0, -1).substr($path, strlen($best) - 1), '/')
            : $target;
    }

    /**
     * Whether an old address is a pattern like "old/*".
     */
    public static function isPattern(string $from): bool
    {
        return str_ends_with(trim($from, '/'), '/*');
    }

    /**
     * What a target is: "entry", "path", "media", "url" or "gone".
     */
    public static function kind(string $target): string
    {
        return match (true) {
            $target === self::GONE => 'gone',
            (bool) preg_match('#^https?://#', $target) => 'url',
            str_starts_with($target, '/') => 'path',
            str_starts_with($target, 'media/') => 'media',
            default => 'entry',
        };
    }

    public function add(string $language, string $path, string $target): void
    {
        $map = $this->all();
        $map[$language][trim($path, '/')] = $target;

        Files::write($this->file(), Yaml::dump($map, 3, 2));
        $this->map = $map;
    }

    public function remove(string $language, string $path): void
    {
        $map = $this->all();
        unset($map[$language][trim($path, '/')]);
        $map = array_filter($map);

        Files::write($this->file(), Yaml::dump($map, 3, 2));
        $this->map = $map;
    }

    /**
     * Updates targets after entries were moved; $rename maps an old id to the new one.
     *
     * @param  callable(string): string  $rename
     */
    public function renameTargets(callable $rename): void
    {
        $map = $this->all();
        $changed = false;

        foreach ($map as $language => $paths) {
            foreach ($paths as $path => $target) {
                if (self::kind($target) === 'entry' && ($new = $rename($target)) !== $target) {
                    $map[$language][$path] = $new;
                    $changed = true;
                }
            }
        }

        if ($changed) {
            Files::write($this->file(), Yaml::dump($map, 3, 2));
            $this->map = $map;
        }
    }

    public function file(): string
    {
        return Paths::settings('redirects.yml');
    }
}

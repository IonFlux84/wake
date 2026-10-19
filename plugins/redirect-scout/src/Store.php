<?php

namespace RedirectScout;

use Wake\Support\Files;
use Wake\Support\Paths;

/**
 * The search and every address it found, in data/redirect-scout/scan.json.
 *
 * Each address: path, url, sources (sitemap, link, wayback, list), the old
 * site's status and title, its state on this site (exists, redirect, gone,
 * missing, error), the redirect target, a suggestion and whether someone
 * chose to ignore it.
 */
class Store
{
    /** @var array<string, mixed>|null */
    protected ?array $data = null;

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->data === null) {
            $file = $this->file();
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            $this->data = is_array($data) ? $data + self::empty() : self::empty();
        }

        return $this->data;
    }

    /**
     * Read the file again on next use, e.g. after another request changed it.
     */
    public function reload(): void
    {
        $this->data = null;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function put(array $values): void
    {
        $this->data = array_replace($this->all(), $values);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function addresses(): array
    {
        return $this->all()['addresses'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function address(string $path): ?array
    {
        return $this->all()['addresses'][$path] ?? null;
    }

    /**
     * Adds an address, or merges what is new about it.
     *
     * @param  array<string, mixed>  $values
     */
    public function remember(string $path, array $values): void
    {
        $this->all();
        $current = $this->data['addresses'][$path] ?? ['path' => $path, 'sources' => [], 'state' => null];

        if (isset($values['sources'])) {
            $values['sources'] = array_values(array_unique([...$current['sources'], ...$values['sources']]));
        }

        $this->data['addresses'][$path] = array_replace($current, $values);
    }

    public function save(): void
    {
        Files::write($this->file(), json_encode($this->all(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // The search is working data of this copy of the site, never part of its Git history.
        if (! is_file($ignore = dirname($this->file()).'/.gitignore')) {
            Files::write($ignore, "*\n!.gitignore\n");
        }
    }

    /**
     * Forget the search and everything it found.
     */
    public function clear(): void
    {
        $this->data = self::empty();
        $this->save();
    }

    public function file(): string
    {
        return Paths::data('redirect-scout/scan.json');
    }

    /**
     * How many addresses are in each state, plus "all" and "ignored".
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = ['all' => 0, 'missing' => 0, 'redirect' => 0, 'exists' => 0, 'gone' => 0, 'ignored' => 0, 'unchecked' => 0, 'error' => 0];

        foreach ($this->addresses() as $address) {
            $counts['all']++;
            $counts[self::filterOf($address)]++;
        }

        return $counts;
    }

    /**
     * The filter an address shows up under.
     *
     * @param  array<string, mixed>  $address
     */
    public static function filterOf(array $address): string
    {
        return match (true) {
            ! empty($address['ignored']) => 'ignored',
            $address['state'] === null => 'unchecked',
            default => $address['state'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function empty(): array
    {
        return [
            'site' => '',
            'phase' => 'idle',
            'options' => [],
            'started_at' => null,
            'finished_at' => null,
            'fetch' => [],
            'check' => [],
            'fetched' => 0,
            'disallow' => [],
            'messages' => [],
            'addresses' => [],
        ];
    }
}

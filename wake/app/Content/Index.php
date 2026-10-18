<?php

namespace Wake\Content;

use DateTimeInterface;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use Wake\Consent\Services;
use Wake\Localization\Languages;
use Wake\Support\Files;
use Wake\Support\Paths;

/**
 * What a database would do for Wake: a list of every entry with its fields,
 * kept in storage/cache/index.php. OPcache holds that file in memory, so
 * queries never touch the content files. Only the body of an entry is read
 * from disk, and only when a template asks for it.
 *
 * The index notices changes made via FTP or Git by comparing modification
 * times, and only parses the files that changed.
 */
class Index
{
    public const VERSION = 3;

    /** @var array<string, mixed>|null */
    protected ?array $data = null;

    protected bool $checked = false;

    public function __construct(
        protected ContentTypes $types,
        protected Languages $languages,
    ) {}

    /**
     * @return array<string, array<string, mixed>> all entries, keyed by id
     */
    public function entries(): array
    {
        return $this->load()['entries'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function ofType(string $type): array
    {
        return array_filter($this->entries(), fn (array $row) => $row['type'] === $type);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        return $this->entries()[$id] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByHash(string $hash): ?array
    {
        $id = $this->load()['hashes'][$hash] ?? null;

        return $id === null ? null : $this->find($id);
    }

    /**
     * The id of the entry that answers a path in a language, if any.
     */
    public function route(string $language, string $path): ?string
    {
        return $this->load()['routes'][$language][trim($path, '/')] ?? null;
    }

    /**
     * Site-wide fields from content/site.{lang}.md.
     *
     * @return array<string, mixed>
     */
    public function site(string $language): array
    {
        $site = $this->load()['site'];

        return $site[$language] ?? $site[$this->languages->default()] ?? [];
    }

    /**
     * External services embedded anywhere in the content, e.g. ["youtube"].
     * They are listed for the privacy policy.
     *
     * @return list<string>
     */
    public function services(): array
    {
        $services = [];

        foreach ($this->load()['docs'] as $doc) {
            array_push($services, ...($doc['services'] ?? []));
        }

        $services = array_values(array_unique($services));
        sort($services);

        return $services;
    }

    /**
     * Problems found while indexing, e.g. two entries with the same address.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->load()['warnings'];
    }

    /**
     * The newest modification time of any content file. Used for cache
     * validation and the "last updated" display.
     */
    public function updatedAt(): int
    {
        return $this->load()['updated'];
    }

    /**
     * Look for changed files and update the index. Returns true when
     * something changed.
     */
    public function refresh(bool $force = false): bool
    {
        $this->data ??= $this->read();
        $this->checked = true;

        $files = $this->listFiles();
        $settings = $this->settings();

        if (! $force && $this->data !== null
            && $this->data['files'] === $files
            && $this->data['settings'] === $settings) {
            return false;
        }

        return (bool) Files::lock('index', function () use ($files, $settings, $force) {
            $this->data = $this->build($files, $settings, $force ? [] : ($this->data['docs'] ?? []));
            Files::writePhp($this->file(), $this->data);

            event(new ContentChanged);

            return true;
        });
    }

    /**
     * Mark files as changed, so the next refresh parses them again even if
     * their modification time did not change (two writes in one second).
     *
     * @param  list<string>  $paths  absolute paths or paths below content/
     */
    public function invalidate(array $paths): void
    {
        $this->data ??= $this->read();

        if ($this->data === null) {
            return;
        }

        $root = Paths::content().'/';

        foreach ($paths as $path) {
            $path = str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
            unset($this->data['docs'][$path]);
            $this->data['files'][$path] = -1;
        }
    }

    /**
     * Look at the files again on next use. Called after every request, for
     * servers that keep the application in memory between requests.
     */
    public function recheck(): void
    {
        $this->checked = false;
    }

    /**
     * Forget the index in memory, e.g. after writing content in the same request.
     */
    public function forget(): void
    {
        $this->data = null;
        $this->checked = false;
    }

    public function file(): string
    {
        return storage_path('cache/index.php');
    }

    /**
     * @return array<string, mixed>
     */
    protected function load(): array
    {
        if (! $this->checked && ($this->data === null || config('wake.index.check') === 'always')) {
            $this->data ??= $this->read();

            if ($this->data === null || config('wake.index.check') === 'always') {
                $this->refresh();
            }

            $this->checked = true;
        }

        return $this->data;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function read(): ?array
    {
        $file = $this->file();

        if (! is_file($file)) {
            return null;
        }

        $data = @include $file;

        return is_array($data) && ($data['version'] ?? null) === self::VERSION ? $data : null;
    }

    /**
     * Everything in the index that depends on settings rather than files.
     *
     * @return array<string, mixed>
     */
    protected function settings(): array
    {
        return [
            'languages' => $this->languages->codes(),
            'types' => array_map(fn (ContentType $type) => [$type->folder, $type->url], $this->types->all()),
            'categories' => app(Categories::class)->fingerprint(),
        ];
    }

    /**
     * Every file below content/ with its modification time.
     *
     * @return array<string, int>
     */
    protected function listFiles(): array
    {
        clearstatcache();
        $root = Paths::content();
        $files = [];

        if (! is_dir($root)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            $name = $file->getFilename();

            if ($name[0] === '.' || $file->isDir()) {
                continue;
            }

            $files[substr($file->getPathname(), strlen($root) + 1)] = $file->getMTime();
        }

        ksort($files);

        return $files;
    }

    /**
     * @param  array<string, int>  $files
     * @param  array<string, mixed>  $settings
     * @param  array<string, array<string, mixed>>  $previousDocs  parsed files from the last run
     * @return array<string, mixed>
     */
    protected function build(array $files, array $settings, array $previousDocs): array
    {
        $docs = [];
        $warnings = [];

        foreach ($files as $path => $mtime) {
            if (! str_ends_with($path, '.md')) {
                continue;
            }

            if (isset($previousDocs[$path]) && $previousDocs[$path]['mtime'] === $mtime) {
                $docs[$path] = $previousDocs[$path];

                continue;
            }

            try {
                [$fields, $body] = FrontMatter::parse((string) file_get_contents(Paths::content($path)), $path);
                $docs[$path] = ['mtime' => $mtime, 'fields' => $this->normalize($fields), 'services' => $this->servicesIn($body)];
            } catch (InvalidContentException $e) {
                $warnings[] = $e->getMessage();
            }
        }

        $entries = [];
        $site = [];

        foreach ($docs as $path => $doc) {
            if (preg_match('#^site(?:\.([a-z]{2}(?:-[a-z]{2})?))?\.md$#i', $path, $m)) {
                $site[$this->languageOf($m[1] ?? '')] = $doc['fields'];

                continue;
            }

            $this->addDocument($entries, $path, $doc, $files);
        }

        $this->linkParents($entries);
        $routes = $this->buildRoutes($entries, $warnings);

        $hashes = [];
        foreach ($entries as $id => $row) {
            $hashes[$row['hash']] = $id;
        }

        return [
            'version' => self::VERSION,
            'built' => time(),
            'updated' => $files === [] ? 0 : max($files),
            'settings' => $settings,
            'files' => $files,
            'docs' => $docs,
            'site' => $site,
            'entries' => $entries,
            'routes' => $routes,
            'hashes' => $hashes,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $entries
     * @param  array<string, mixed>  $doc
     * @param  array<string, int>  $files
     */
    protected function addDocument(array &$entries, string $path, array $doc, array $files): void
    {
        $segments = explode('/', $path);
        $type = $this->types->forFolder($segments[0]);

        if ($type === null || count($segments) < 2) {
            return;
        }

        $file = array_pop($segments);

        if ($type->layout === ContentType::FILES) {
            if (count($segments) !== 1 || ! preg_match('#^(.+?)(?:\.([a-z]{2}(?:-[a-z]{2})?))?\.md$#i', $file, $m)) {
                return;
            }

            $dir = $segments[0].'/'.$m[1];
            $language = $this->languageOf($m[2] ?? '');
            $media = [];
        } else {
            if (count($segments) < 2 || ($type->layout === ContentType::FOLDERS && count($segments) !== 2)
                || ! preg_match('#^index(?:\.([a-z]{2}(?:-[a-z]{2})?))?\.md$#i', $file, $m)) {
                return;
            }

            $dir = implode('/', $segments);
            $language = $this->languageOf($m[1] ?? '');
            $media = $this->mediaOf($dir, $files);
        }

        if ($language === null) {
            return;
        }

        $entries[$dir] ??= $this->newRow($type, $dir, $media);
        $entries[$dir]['languages'][$language] = [
            'file' => $path,
            'mtime' => $doc['mtime'],
            'fields' => $doc['fields'],
        ];
    }

    /**
     * @param  array<string, int>  $media
     * @return array<string, mixed>
     */
    protected function newRow(ContentType $type, string $dir, array $media): array
    {
        $folder = basename($dir);
        $num = null;
        $date = null;

        if ($type->layout === ContentType::TREE && preg_match('/^(\d+)_(.+)$/', $folder, $m)) {
            [$num, $folder] = [(int) $m[1], $m[2]];
        }

        if ($type->layout === ContentType::FOLDERS && preg_match('/^(\d{4}-\d{2}-\d{2})-(.+)$/', $folder, $m)) {
            [$date, $folder] = [$m[1], $m[2]];
        }

        return [
            'id' => $dir,
            'type' => $type->name,
            'name' => $folder,
            'num' => $num,
            'date' => $date,
            'parent' => null,
            'hash' => substr(md5($dir), 0, 10),
            'media' => $media,
            'media_meta' => $this->mediaMeta($dir, $media),
            'languages' => [],
        ];
    }

    /**
     * Files next to an entry's text files: images, downloads and so on.
     *
     * @param  array<string, int>  $files
     * @return array<string, int>
     */
    protected function mediaOf(string $dir, array $files): array
    {
        $media = [];
        $prefix = $dir.'/';

        foreach ($files as $path => $mtime) {
            if (str_starts_with($path, $prefix) && ! str_contains($rest = substr($path, strlen($prefix)), '/')
                && ! str_ends_with($rest, '.md') && ! str_ends_with($rest, '.yml')) {
                $media[$rest] = $mtime;
            }
        }

        return $media;
    }

    /**
     * Details of media files from their sidecar files: photo.jpg.yml holds
     * alt, caption, credit and focus of photo.jpg.
     *
     * @param  array<string, int>  $media
     * @return array<string, array<string, mixed>>
     */
    protected function mediaMeta(string $dir, array $media): array
    {
        $meta = [];

        foreach (array_keys($media) as $name) {
            $sidecar = Paths::content($dir.'/'.$name.'.yml');

            if (! is_file($sidecar)) {
                continue;
            }

            try {
                $data = Yaml::parseFile($sidecar);
            } catch (Throwable) {
                continue;
            }

            if (is_array($data) && $data !== []) {
                $meta[$name] = $data;
            }
        }

        return $meta;
    }

    /**
     * @param  array<string, array<string, mixed>>  $entries
     */
    protected function linkParents(array &$entries): void
    {
        foreach ($entries as $id => $row) {
            if ($this->types->get($row['type'])->layout !== ContentType::TREE) {
                continue;
            }

            $parent = dirname($id);

            while (str_contains($parent, '/')) {
                if (isset($entries[$parent])) {
                    $entries[$id]['parent'] = $parent;

                    break;
                }

                $parent = dirname($parent);
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $entries
     * @param  list<string>  $warnings
     * @return array<string, array<string, string>> language => path => id
     */
    protected function buildRoutes(array $entries, array &$warnings): array
    {
        $routes = array_fill_keys($this->languages->codes(), []);

        foreach ($entries as $id => $row) {
            foreach ($row['languages'] as $language => $translation) {
                if (! isset($routes[$language])) {
                    continue;
                }

                $path = Addresses::path($row, $language, $entries, $this->types->get($row['type']), $this->languages->default());

                if (isset($routes[$language][$path])) {
                    $warnings[] = "Two entries share the address /{$path} ({$language}): {$routes[$language][$path]} and {$id}.";

                    continue;
                }

                $routes[$language][$path] = $id;
            }
        }

        return $routes;
    }

    protected function languageOf(string $code): ?string
    {
        if ($code === '') {
            return $this->languages->default();
        }

        $code = strtolower($code);

        return $this->languages->exists($code) ? $code : null;
    }

    /**
     * @return list<string>
     */
    protected function servicesIn(string $body): array
    {
        preg_match_all('/^:::[ \t]*embed[ \t]+(?:url=)?["\']?(\S+?)["\']?[ \t]*$/mi', $body, $matches);

        return array_values(array_unique(array_map(Services::detect(...), $matches[1])));
    }

    /**
     * Make front matter safe for var_export and consistent for queries.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    protected function normalize(array $fields): array
    {
        foreach ($fields as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $fields[$key] = $value->format($value->format('H:i:s') === '00:00:00' ? 'Y-m-d' : 'Y-m-d H:i');
            } elseif (is_array($value)) {
                $fields[$key] = $this->normalize($value);
            } elseif (is_object($value)) {
                $fields[$key] = (string) json_encode($value);
            }
        }

        return $fields;
    }
}

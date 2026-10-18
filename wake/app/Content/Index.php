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
 * kept in temp/cache/index.php. OPcache holds that file in memory, so
 * queries never touch the content files. Only the body of an entry is read
 * from disk, and only when a template asks for it.
 *
 * The index notices changes made via FTP or Git by comparing modification
 * times, and only parses the files that changed.
 */
class Index
{
    public const VERSION = 4;

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
     * A file in content/media/, by its path below media/ ("posts/x/photo.jpg").
     *
     * @return array{mtime: int, meta: array<string, mixed>}|null
     */
    public function media(string $path): ?array
    {
        $data = $this->load();

        return isset($data['media'][$path]) ? ['mtime' => $data['media'][$path], 'meta' => $data['media_meta'][$path] ?? []] : null;
    }

    /**
     * Every file in content/media/: path below media/ => modification time.
     *
     * @return array<string, int>
     */
    public function allMedia(): array
    {
        return $this->load()['media'];
    }

    /**
     * The entries that show or link a media file.
     *
     * @return list<string> entry ids
     */
    public function mediaUsage(string $path): array
    {
        return $this->load()['media_usage'][$path] ?? [];
    }

    /**
     * The path of a media file by the hash in its address.
     */
    public function mediaByHash(string $hash): ?string
    {
        return $this->load()['media_hashes'][$hash] ?? null;
    }

    /**
     * Site-wide fields from content/website.md (and website.{lang}.md).
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
            // Hand-edited menus and redirects must reach cached pages, too.
            'site_files' => array_map(fn (string $file) => @filemtime(Paths::settings($file)) ?: 0, ['menus.yml', 'redirects.yml']),
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
                $docs[$path] = ['mtime' => $mtime, 'fields' => $this->normalize($fields), 'services' => $this->servicesIn($body), 'refs' => $this->mediaIn($fields, $body)];
            } catch (InvalidContentException $e) {
                $warnings[] = $e->getMessage();
            }
        }

        $entries = [];
        $site = [];

        foreach ($docs as $path => $doc) {
            if (preg_match('#^website(?:\.([a-z]{2}(?:-[a-z]{2})?))?\.md$#i', $path, $m)) {
                $site[$this->languageOf($m[1] ?? '')] = $doc['fields'];

                continue;
            }

            $this->addDocument($entries, $path, $doc, $files);
        }

        $this->linkParents($entries);
        $this->settleRows($entries, $files);
        [$media, $mediaMeta, $mediaHashes] = $this->mediaLibrary($files);
        $usage = $this->usageOf($entries, $docs, $media);
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
            'media' => $media,
            'media_meta' => $mediaMeta,
            'media_hashes' => $mediaHashes,
            'media_usage' => $usage,
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
        $file = array_pop($segments);

        // An entry is a folder with content.md (default language) or content.<lang>.md.
        if ($type === null || count($segments) < 2 || ! preg_match('#^content(?:\.([a-z]{2}(?:-[a-z]{2})?))?\.md$#i', $file, $m)
            || ($type->layout === ContentType::FOLDERS && count($segments) !== 2)) {
            return;
        }

        $language = $this->languageOf($m[1] ?? '');

        if ($language === null) {
            return;
        }

        $dir = implode('/', $segments);
        $entries[$dir] ??= $this->newRow($type, $dir, $files);
        $entries[$dir]['languages'][$language] = [
            'file' => $path,
            'mtime' => $doc['mtime'],
            'fields' => $doc['fields'],
        ];
    }

    /**
     * @param  array<string, int>  $files
     * @return array<string, mixed>
     */
    protected function newRow(ContentType $type, string $dir, array $files): array
    {
        return [
            'id' => $dir,
            'type' => $type->name,
            'name' => basename($dir),
            'order' => null,
            'menu' => false,
            'parent' => null,
            'hash' => substr(md5($dir), 0, 10),
            'style' => isset($files[$dir.'/style.css']) ? $files[$dir.'/style.css'] : null,
            'languages' => [],
        ];
    }

    /**
     * Order and menu come from the default language's front matter.
     *
     * @param  array<string, array<string, mixed>>  $entries
     * @param  array<string, int>  $files
     */
    protected function settleRows(array &$entries, array $files): void
    {
        $default = $this->languages->default();

        foreach ($entries as $id => $row) {
            $fields = ($row['languages'][$default] ?? reset($row['languages']) ?: [])['fields'] ?? [];
            $entries[$id]['order'] = is_numeric($fields['order'] ?? null) ? (int) $fields['order'] : null;
            $entries[$id]['menu'] = filter_var($fields['menu'] ?? false, FILTER_VALIDATE_BOOL);
        }
    }

    /**
     * Files in content/media/ and their details from sidecar files: photo.jpg.yml
     * holds alt, caption, credit and focus of photo.jpg.
     *
     * @param  array<string, int>  $files
     * @return array{0: array<string, int>, 1: array<string, array<string, mixed>>, 2: array<string, string>}
     */
    protected function mediaLibrary(array $files): array
    {
        $media = $meta = $hashes = [];

        foreach ($files as $path => $mtime) {
            if (! str_starts_with($path, 'media/') || str_ends_with($path, '.yml')) {
                continue;
            }

            $name = substr($path, 6);
            $media[$name] = $mtime;
            $hashes[substr(md5('media/'.$name), 0, 10)] = $name;

            if (isset($files[$path.'.yml'])) {
                try {
                    $data = Yaml::parseFile(Paths::content($path.'.yml'));
                } catch (Throwable) {
                    $data = null;
                }

                if (is_array($data) && $data !== []) {
                    $meta[$name] = $data;
                }
            }
        }

        return [$media, $meta, $hashes];
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
     * Files a document refers to: "media/…" anywhere, or plain names in
     * Markdown links and images, which mean the entry's own media folder.
     *
     * @param  array<string, mixed>  $fields
     * @return list<string>
     */
    protected function mediaIn(array $fields, string $body): array
    {
        $refs = [];

        foreach (['cover', 'thumbnail'] as $key) {
            if (is_string($fields[$key] ?? null) && $fields[$key] !== '') {
                $refs[] = $fields[$key];
            }
        }

        preg_match_all('#\]\(([^)\s]+)#', $body, $links);
        preg_match_all('#\bmedia/[^\s)"\']+#', $body, $mentions);

        foreach ([...$links[1], ...$mentions[0]] as $ref) {
            if (! preg_match('#^([a-z][a-z0-9+.-]*:|/|\#|\?)#i', $ref)) {
                $refs[] = rawurldecode($ref);
            }
        }

        return array_values(array_unique($refs));
    }

    /**
     * @param  array<string, array<string, mixed>>  $entries
     * @param  array<string, array<string, mixed>>  $docs
     * @param  array<string, int>  $media
     * @return array<string, list<string>> media path => entry ids
     */
    protected function usageOf(array $entries, array $docs, array $media): array
    {
        $usage = [];

        foreach ($entries as $id => $row) {
            foreach ($row['languages'] as $translation) {
                foreach ($docs[$translation['file']]['refs'] ?? [] as $ref) {
                    $path = str_starts_with($ref, 'media/') ? substr($ref, 6) : $id.'/'.$ref;

                    if (isset($media[$path]) && ! in_array($id, $usage[$path] ?? [], true)) {
                        $usage[$path][] = $id;
                    }
                }
            }
        }

        return $usage;
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

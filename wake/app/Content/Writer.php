<?php

namespace Wake\Content;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Wake\Localization\Languages;
use Wake\Support\Files;
use Wake\Support\Paths;

/**
 * Every change to content goes through here: the panel, the MCP server and
 * the importers use the same code. The writer
 *
 * - writes files atomically and under a lock per entry,
 * - keeps the previous version as a revision,
 * - remembers old addresses of published entries as redirects,
 * - moves deleted entries to data/trash instead of deleting them,
 * - and updates the index, which clears the page cache.
 */
class Writer
{
    public function __construct(
        protected Index $index,
        protected ContentTypes $types,
        protected Languages $languages,
        protected Revisions $revisions,
        protected Redirects $redirects,
        protected History $history,
    ) {}

    /**
     * Create a new entry with its first translation.
     *
     * @param  array<string, mixed>  $fields  front matter; "title" is required
     * @param  string|null  $parent  id of the parent page (pages only)
     */
    public function create(string $type, array $fields, string $body = '', ?string $language = null, ?string $parent = null): Entry
    {
        $contentType = $this->types->get($type) ?? throw new InvalidArgumentException("Unknown content type {$type}.");
        $language ??= $this->languages->default();
        $title = trim((string) ($fields['title'] ?? ''));

        if ($title === '') {
            throw new InvalidArgumentException('An entry needs a title.');
        }

        $this->guardLanguage($language);

        $fields['status'] ??= 'draft';
        $slug = Str::slug((string) ($fields['slug'] ?? $title), language: $language) ?: 'entry';

        $base = $contentType->layout === ContentType::TREE && $parent !== null
            ? $this->existing($parent)['id']
            : $contentType->folder;

        // One folder per entry, named like its address; the date and the
        // menu position live in the file, not in the name.
        $id = $this->uniqueId($base.'/'.$slug);
        $file = $id.'/content'.($language === $this->languages->default() ? '' : '.'.$language).'.md';

        Files::lock('entry-'.md5($id), fn () => Files::write(Paths::content($file), FrontMatter::dump($this->clean($fields), $body)));
        $this->history->record('create', $id, $language);

        return $this->fresh($id, $language);
    }

    /**
     * Change fields and/or the body of one translation. Fields set to null
     * are removed. Creates the translation if it does not exist yet.
     *
     * @param  array<string, mixed>  $fields
     */
    public function update(string $id, string $language, array $fields = [], ?string $body = null): Entry
    {
        $row = $this->existing($id);
        $this->guardLanguage($language);

        $before = isset($row['languages'][$language]) ? Entry::make($row, $language) : null;
        $oldPath = $before?->isPublished() ? $before->path() : null;
        $file = $this->fileFor($row, $language);

        Files::lock('entry-'.md5($id), function () use ($file, $fields, $body) {
            $path = Paths::content($file);
            [$current, $currentBody] = is_file($path) ? FrontMatter::parse((string) file_get_contents($path), $file) : [[], ''];

            if (is_file($path)) {
                $this->revisions->keep($file, (string) file_get_contents($path));
            }

            $merged = $this->clean(array_merge($current, $fields));
            Files::write($path, FrontMatter::dump($merged, $body ?? $currentBody));
            $this->index->invalidate([$file]);
        });

        $entry = $this->fresh($id, $language);
        $this->history->record(match ($fields['status'] ?? null) {
            'published' => 'publish', 'scheduled' => 'schedule', 'draft' => $before?->isPublished() ? 'unpublish' : 'update', default => 'update',
        }, $id, $language);

        if ($oldPath !== null && $entry->path() !== $oldPath) {
            $this->redirects->add($language, $oldPath, $id);
        }

        return $entry;
    }

    public function publish(string $id, string $language, ?CarbonImmutable $at = null): Entry
    {
        $row = $this->existing($id);
        $published = $row['languages'][$language]['fields']['published'] ?? null;

        return $this->update($id, $language, [
            'status' => $at !== null && $at->isFuture() ? 'scheduled' : 'published',
            'published' => ($at ?? ($published ? CarbonImmutable::parse((string) $published) : CarbonImmutable::now()))->format('Y-m-d H:i'),
            'review' => null,
        ]);
    }

    /**
     * Put an earlier version of a translation back. The current version is
     * kept as a revision too, so restoring can be undone.
     */
    public function restore(string $id, string $language, string $revision): Entry
    {
        $row = $this->existing($id);
        $file = $this->fileFor($row, $language);
        $contents = $this->revisions->get($file, $revision) ?? throw new InvalidArgumentException('There is no such revision.');
        [$fields, $body] = FrontMatter::parse($contents, $file);

        $current = $row['languages'][$language]['fields'] ?? [];
        $removed = array_fill_keys(array_keys(array_diff_key($current, $fields)), null);

        $entry = $this->update($id, $language, [...$removed, ...$fields], $body);
        $this->history->record('restore', $id, $language);

        return $entry;
    }

    public function unpublish(string $id, string $language): Entry
    {
        return $this->update($id, $language, ['status' => 'draft']);
    }

    /**
     * Move the whole entry (all languages and media) to data/trash.
     * Nothing is deleted for good; the folder can be moved back by hand.
     */
    public function trash(string $id): string
    {
        $row = $this->existing($id);
        $target = Paths::data('trash/'.CarbonImmutable::now()->format('Y-m-d-His').'-'.str_replace('/', '--', $id));

        Files::lock('entry-'.md5($id), function () use ($row, $target) {
            @mkdir($target, 0775, true);

            if (! rename(Paths::content($row['id']), $target.'/'.basename($row['id']))) {
                throw new RuntimeException("Cannot move {$row['id']} to the trash.");
            }
        });

        $this->index->refresh();
        $this->history->record('trash', $id);

        return $target;
    }

    /**
     * Store an uploaded or imported file in the entry's own folder in the
     * media library (content/media/<id>/). Returns how content refers to it,
     * e.g. "media/posts/lantern-walk/procession.jpg".
     */
    public function addMedia(string $id, string $name, string $source): string
    {
        return $this->storeMedia($this->existing($id)['id'], $name, $source);
    }

    /**
     * Saves the design of one entry (style.css in its folder), cleaned by
     * PageStyle. Empty CSS removes the file. The previous design is kept as
     * a version.
     */
    public function saveStyle(string $id, string $css): void
    {
        $row = $this->existing($id);
        $file = $row['id'].'/style.css';
        $path = Paths::content($file);
        $css = PageStyle::clean($css);

        Files::lock('entry-'.md5($row['id']), function () use ($file, $path, $css) {
            if (is_file($path)) {
                $this->revisions->keep($file, (string) file_get_contents($path));
            }

            $css === '' ? @unlink($path) : Files::write($path, $css."\n");
        });

        $this->index->refresh();
        $this->history->record('style', $row['id']);
    }

    /**
     * Store a file in a folder of the media library ("documents", "posts/x").
     * Names become lowercase with dashes; a taken name gets "-2".
     */
    public function storeMedia(string $folder, string $name, string $source): string
    {
        $folder = trim(preg_replace('#[^a-z0-9/_-]+#', '-', strtolower($folder)), '/-');

        if ($folder === '' || str_contains($folder, '..')) {
            throw new InvalidArgumentException('Choose a folder in the media library.');
        }

        $info = pathinfo($name);
        $stem = Str::slug($info['filename'] ?? 'file') ?: 'file';
        $extension = strtolower($info['extension'] ?? 'bin');
        $name = $stem.'.'.$extension;

        for ($i = 2; is_file(Paths::content("media/{$folder}/{$name}")); $i++) {
            $name = $stem.'-'.$i.'.'.$extension;
        }

        Files::copy($source, Paths::content("media/{$folder}/{$name}"));
        $this->index->refresh();

        return "media/{$folder}/{$name}";
    }

    /**
     * Move a file of the media library (and its details) to the trash.
     */
    public function deleteMedia(string $reference): void
    {
        $path = $this->mediaPath($reference);

        if ($path === null) {
            return;
        }

        $target = Paths::data('trash/'.CarbonImmutable::now()->format('Y-m-d-His').'-media--'.str_replace('/', '--', $path));
        @mkdir(dirname($target), 0775, true);
        rename(Paths::content('media/'.$path), $target);

        if (is_file($sidecar = Paths::content('media/'.$path.'.yml'))) {
            rename($sidecar, $target.'.yml');
        }

        $this->index->refresh();
    }

    /**
     * Saves alt text, caption, credit and focus point of a file in its sidecar
     * file (photo.jpg.yml next to photo.jpg). Empty values are left out;
     * without any, the sidecar file is removed.
     *
     * @param  array<string, mixed>  $meta
     */
    public function saveMediaMeta(string $reference, array $meta): void
    {
        $path = $this->mediaPath($reference) ?? throw new InvalidArgumentException("There is no file {$reference} in the media library.");

        $clean = function ($value) use (&$clean) {
            return is_array($value) ? array_filter(array_map($clean, $value), fn ($v) => $v !== '' && $v !== []) : trim((string) $value);
        };
        $meta = array_filter(array_map($clean, array_intersect_key($meta, array_flip(['alt', 'caption', 'credit', 'focus']))), fn ($v) => $v !== '' && $v !== []);
        $sidecar = Paths::content('media/'.$path.'.yml');

        if ($meta === []) {
            @unlink($sidecar);
        } else {
            Files::write($sidecar, Yaml::dump($meta, 3, 2));
        }

        $this->index->refresh();
    }

    /**
     * Moves a page in the page tree: under another parent ($parent, null for
     * the top level) and to $position among its siblings (counted from 0;
     * null puts it at the end).
     *
     * The order is written as "order: N" into the pages' files. The folder
     * only moves when the parent changes; then versions, redirects and menus
     * follow the new id, and published pages whose address changes get a
     * redirect from the old one.
     *
     * @return string the id of the page afterwards
     */
    public function movePage(string $id, ?string $parent, ?int $position = null): string
    {
        return Files::lock('page-tree', function () use ($id, $parent, $position) {
            $row = $this->existing($id);
            $type = $this->types->get($row['type']);

            if ($type->layout !== ContentType::TREE) {
                throw new InvalidArgumentException('Only pages can be moved in the page tree.');
            }

            if ($row['parent'] === null && $row['name'] === 'home' && $parent !== null) {
                throw new InvalidArgumentException('The home page stays where it is.');
            }

            if ($parent !== null && ($parent === $id || str_starts_with($parent.'/', $id.'/') || $this->existing($parent)['type'] !== $row['type'])) {
                throw new InvalidArgumentException('A page cannot be moved into itself.');
            }

            $newId = $row['parent'] === $parent ? $id : $this->movePageFolder($row, $parent ?? $type->folder);

            // Number the siblings 1, 2, 3 … with the moved page in its new place.
            $siblings = collect($this->index->entries())
                ->filter(fn (array $r) => $r['type'] === $row['type'] && $r['parent'] === $parent && $r['id'] !== $newId)
                ->sortBy(fn (array $r) => [$r['order'] ?? PHP_INT_MAX, $r['name']])
                ->keys()->values()->all();
            array_splice($siblings, $position === null ? count($siblings) : max(0, min($position, count($siblings))), 0, [$newId]);

            foreach ($siblings as $n => $sibling) {
                if (($this->index->find($sibling)['order'] ?? null) !== $n + 1) {
                    $this->setFields($sibling, ['order' => $n + 1]);
                }
            }

            $this->index->refresh();
            $this->history->record('move', $newId);

            return $newId;
        });
    }

    /**
     * Writes fields into the default language's file without keeping a
     * version, for bookkeeping such as the order in the page tree.
     *
     * @param  array<string, mixed>  $fields  null removes a field
     */
    public function setFields(string $id, array $fields): void
    {
        $row = $this->existing($id);
        $file = $this->fileFor($row, $this->languages->default());

        Files::lock('entry-'.md5($id), function () use ($file, $fields) {
            $path = Paths::content($file);
            [$current, $body] = is_file($path) ? FrontMatter::parse((string) file_get_contents($path), $file) : [[], ''];
            Files::write($path, FrontMatter::dump($this->clean(array_merge($current, $fields)), $body));
            $this->index->invalidate([$file]);
        });
    }

    /**
     * Moves a page's folder below another parent and points everything that
     * refers to it (and to its sub-pages) at the new ids.
     *
     * @param  array<string, mixed>  $row
     */
    protected function movePageFolder(array $row, string $base): string
    {
        $id = $row['id'];
        $target = $this->uniqueId($base.'/'.$row['name']);
        $rename = fn (string $old) => $old === $id || str_starts_with($old, $id.'/') ? $target.substr($old, strlen($id)) : $old;

        // Addresses and files of the page and its sub-pages before the move.
        $before = [];
        foreach ($this->index->entries() as $entryId => $entryRow) {
            if ($entryId === $id || str_starts_with($entryId, $id.'/')) {
                foreach (array_keys($entryRow['languages']) as $language) {
                    $entry = Entry::make($entryRow, $language);
                    $before[$entryId][$language] = ['path' => $entry->isPublished() ? $entry->path() : null, 'file' => $entryRow['languages'][$language]['file']];
                }
            }
        }

        @mkdir(dirname(Paths::content($target)), 0775, true);
        rename(Paths::content($id), Paths::content($target));

        foreach ($before as $oldId => $translations) {
            foreach ($translations as $translation) {
                $this->revisions->move($translation['file'], $rename($oldId).substr($translation['file'], strlen($oldId)));
            }
        }

        $this->redirects->renameTargets($rename);
        app(Menus::class)->renameTargets($rename);
        $this->index->refresh();

        foreach ($before as $oldId => $translations) {
            $moved = $this->index->find($rename($oldId));

            foreach ($translations as $language => $translation) {
                if ($moved && $translation['path'] !== null && ($path = Entry::make($moved, $language)->path()) !== $translation['path']) {
                    $this->redirects->add($language, $translation['path'], $moved['id']);
                }
            }
        }

        return $target;
    }

    /**
     * The file a translation lives in (it may not exist yet).
     *
     * @param  array<string, mixed>  $row
     */
    protected function fileFor(array $row, string $language): string
    {
        return $row['languages'][$language]['file']
            ?? $row['id'].'/content'.($language === $this->languages->default() ? '' : '.'.$language).'.md';
    }

    /**
     * @return array<string, mixed>
     */
    protected function existing(string $id): array
    {
        $this->index->refresh();

        return $this->index->find($id) ?? throw new InvalidArgumentException("There is no entry {$id}.");
    }

    protected function fresh(string $id, string $language): Entry
    {
        $this->index->refresh();
        $row = $this->index->find($id) ?? throw new RuntimeException("The entry {$id} could not be read back.");

        return Entry::make($row, $language);
    }

    protected function uniqueId(string $id): string
    {
        $unique = $id;

        for ($i = 2; is_dir(Paths::content($unique)); $i++) {
            $unique = $id.'-'.$i;
        }

        return $unique;
    }

    /**
     * The path below media/ of an existing file in the library.
     */
    protected function mediaPath(string $reference): ?string
    {
        $path = ltrim(preg_replace('#^media/#', '', $reference), '/');

        return ! str_contains($path, '..') && $this->index->media($path) !== null ? $path : null;
    }

    protected function guardLanguage(string $language): void
    {
        if (! $this->languages->exists($language)) {
            throw new InvalidArgumentException("The site has no language {$language}.");
        }
    }

    /**
     * Drop empty values and normalise lists, so files stay tidy.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    protected function clean(array $fields): array
    {
        $order = ['title', 'slug', 'status', 'published', 'author', 'tags', 'cover', 'excerpt'];

        $fields = array_filter($fields, fn ($value) => $value !== null && $value !== '' && $value !== []);

        if (isset($fields['tags']) && is_string($fields['tags'])) {
            $fields['tags'] = array_values(array_filter(array_map('trim', explode(',', $fields['tags']))));
        }

        uksort($fields, function ($a, $b) use ($order) {
            $ia = array_search($a, $order, true);
            $ib = array_search($b, $order, true);

            return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib) ?: strcmp($a, $b);
        });

        return $fields;
    }
}

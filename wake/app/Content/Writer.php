<?php

namespace Wake\Content;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
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
 * - moves deleted entries to storage/trash instead of deleting them,
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

        $folder = match ($contentType->layout) {
            ContentType::FOLDERS => (! empty($fields['published']) ? CarbonImmutable::parse((string) $fields['published']) : CarbonImmutable::now())->format('Y-m-d').'-'.$slug,
            default => $slug,
        };

        $base = $contentType->layout === ContentType::TREE && $parent !== null
            ? $this->existing($parent)['id']
            : $contentType->folder;

        $id = $this->uniqueId($base.'/'.$folder, $contentType);
        $file = $contentType->layout === ContentType::FILES
            ? $id.($language === $this->languages->default() ? '' : '.'.$language).'.md'
            : $id.'/index.'.$language.'.md';

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
     * Move the whole entry (all languages and media) to storage/trash.
     * Nothing is deleted for good; the folder can be moved back by hand.
     */
    public function trash(string $id): string
    {
        $row = $this->existing($id);
        $type = $this->types->get($row['type']);
        $target = storage_path('trash/'.CarbonImmutable::now()->format('Y-m-d-His').'-'.str_replace('/', '--', $id));

        Files::lock('entry-'.md5($id), function () use ($row, $type, $target) {
            @mkdir($target, 0775, true);

            if ($type->layout === ContentType::FILES) {
                foreach ($row['languages'] as $translation) {
                    rename(Paths::content($translation['file']), $target.'/'.basename($translation['file']));
                }
            } elseif (! rename(Paths::content($row['id']), $target.'/'.basename($row['id']))) {
                throw new RuntimeException("Cannot move {$row['id']} to the trash.");
            }
        });

        $this->index->refresh();
        $this->history->record('trash', $id);

        return $target;
    }

    /**
     * Store an uploaded or imported file next to an entry's text files.
     */
    public function addMedia(string $id, string $name, string $source): string
    {
        $row = $this->existing($id);
        $type = $this->types->get($row['type']);

        if ($type->layout === ContentType::FILES) {
            throw new InvalidArgumentException('Tags cannot have files.');
        }

        $info = pathinfo($name);
        $name = Str::slug($info['filename'] ?? 'file').'.'.strtolower($info['extension'] ?? 'bin');

        for ($i = 2; is_file(Paths::content($id.'/'.$name)); $i++) {
            $name = Str::slug($info['filename']).'-'.$i.'.'.strtolower($info['extension'] ?? 'bin');
        }

        Files::copy($source, Paths::content($id.'/'.$name));
        $this->index->refresh();

        return $name;
    }

    public function deleteMedia(string $id, string $name): void
    {
        $row = $this->existing($id);

        if (! isset($row['media'][$name])) {
            return;
        }

        $target = storage_path('trash/'.CarbonImmutable::now()->format('Y-m-d-His').'-'.str_replace('/', '--', $id).'--'.$name);
        @mkdir(dirname($target), 0775, true);
        rename(Paths::content($id.'/'.$name), $target);
        $this->index->refresh();
    }

    /**
     * The file a translation lives in (it may not exist yet).
     *
     * @param  array<string, mixed>  $row
     */
    protected function fileFor(array $row, string $language): string
    {
        if (isset($row['languages'][$language])) {
            return $row['languages'][$language]['file'];
        }

        return $this->types->get($row['type'])->layout === ContentType::FILES
            ? $row['id'].'.'.$language.'.md'
            : $row['id'].'/index.'.$language.'.md';
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

    protected function uniqueId(string $id, ContentType $type): string
    {
        $exists = fn (string $candidate) => $type->layout === ContentType::FILES
            ? glob(Paths::content($candidate).'*.md') !== []
            : is_dir(Paths::content($candidate));

        $unique = $id;

        for ($i = 2; $exists($unique); $i++) {
            $unique = $id.'-'.$i;
        }

        return $unique;
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

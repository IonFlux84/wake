<?php

namespace Wake\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Wake\Accounts\Account;
use Wake\Accounts\Permissions;
use Wake\Content\ContentTypes;
use Wake\Content\Entry;
use Wake\Content\History;
use Wake\Content\Index;
use Wake\Content\Query;
use Wake\Content\Revisions;
use Wake\Content\Writer;
use Wake\Localization\Languages;
use Wake\Media\Media;
use Wake\Stats\Stats;
use Wake\Support\Files;

/**
 * The editor: title, Markdown body, fields from the blueprint, publishing,
 * translations, files and the statistics of the entry.
 */
class EntryEditor extends Component
{
    use WithFileUploads;

    /** Fields every entry has; anything else comes from the blueprint. */
    public const BASE_FIELDS = ['title', 'slug', 'status', 'published', 'author', 'tags', 'excerpt', 'cover', 'template', 'seo', 'noindex', 'via', 'review'];

    public string $type = 'post';

    #[Url]
    public ?string $id = null;

    #[Url]
    public string $language = '';

    public ?string $parent = null;

    public string $title = '';

    public string $body = '';

    public string $slug = '';

    public string $tags = '';

    public string $excerpt = '';

    public string $cover = '';

    public string $template = '';

    public string $seoTitle = '';

    public string $seoDescription = '';

    public string $scheduleAt = '';

    /** @var array<string, mixed> values of blueprint fields */
    public array $custom = [];

    public string $tab = 'entry';

    /** @var mixed an uploaded file waiting to be stored */
    public $upload = null;

    public function mount(string $type = 'post'): void
    {
        abort_unless(app(ContentTypes::class)->get($type), 404);
        $this->type = $type;
        $this->language = app(Languages::class)->exists($this->language) ? $this->language : app(Languages::class)->default();

        if ($this->id !== null) {
            $entry = $this->entry() ?? abort(404);
            abort_unless($this->mayEdit($entry), 403);
            $this->loadEntry($entry);
        }
    }

    // Loading ---------------------------------------------------------------

    protected function entry(): ?Entry
    {
        if ($this->id === null) {
            return null;
        }

        $row = app(Index::class)->find($this->id);

        return $row && $row['type'] === $this->type ? Entry::make($row, $this->language) : null;
    }

    protected function loadEntry(Entry $entry): void
    {
        $translated = $entry->hasTranslation($this->language);
        $fields = $translated ? $entry->fields() : [];

        $this->title = (string) ($fields['title'] ?? '');
        $this->body = $translated ? $entry->body() : '';
        $this->slug = (string) ($fields['slug'] ?? '');
        $this->tags = implode(', ', (array) ($fields['tags'] ?? ($translated ? [] : $entry->get('tags', []))));
        $this->excerpt = (string) ($fields['excerpt'] ?? '');
        $this->cover = (string) ($fields['cover'] ?? $entry->get('cover', ''));
        $this->template = (string) ($fields['template'] ?? '');
        $this->seoTitle = (string) data_get($fields, 'seo.title', '');
        $this->seoDescription = (string) data_get($fields, 'seo.description', '');
        $this->custom = Arr::except($translated ? $fields : $entry->fields(), self::BASE_FIELDS);
    }

    public function switchLanguage(string $language): void
    {
        if (! app(Languages::class)->exists($language)) {
            return;
        }

        $this->language = $language;
        $entry = $this->entry();

        if ($entry !== null) {
            $this->loadEntry($entry);
            $this->dispatch('wake-editor-load', markdown: $this->body, media: $this->mediaMap($entry));
        }
    }

    // Saving ----------------------------------------------------------------

    /**
     * Save the current translation. Creates the entry on the first save.
     */
    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:300',
            'slug' => ['nullable', 'string', 'max:200', 'regex:/^[\pL\pN-]*$/u'],
            'excerpt' => 'nullable|string|max:1000',
            'seoTitle' => 'nullable|string|max:200',
            'seoDescription' => 'nullable|string|max:400',
        ]);

        $writer = app(Writer::class);
        $fields = $this->fields();

        try {
            if ($this->id === null) {
                $entry = $writer->create($this->type, [...$fields, 'author' => $this->account()->id()], $this->body, $this->language, $this->parent);
                $this->id = $entry->id();
            } else {
                abort_unless($this->mayEdit($this->entry()), 403);
                $writer->update($this->id, $this->language, $fields, $this->body);
            }
        } catch (InvalidArgumentException $e) {
            $this->addError('title', $e->getMessage());

            return;
        }

        $this->dispatch('wake-saved', at: now()->format('H:i'));
    }

    public function publish(): void
    {
        $this->authorizePublishing();
        $this->save();

        if ($this->getErrorBag()->isEmpty()) {
            $at = $this->scheduleAt !== '' ? CarbonImmutable::parse($this->scheduleAt, config('app.timezone')) : null;
            app(Writer::class)->publish($this->id, $this->language, $at);
            $this->scheduleAt = '';
            session()->flash('status', $at?->isFuture() ? __('Scheduled.') : __('Published.'));
        }
    }

    public function unpublish(): void
    {
        $this->authorizePublishing();
        app(Writer::class)->unpublish($this->id, $this->language);
        session()->flash('status', __('Unpublished. The entry is a draft again.'));
    }

    public function trash(): void
    {
        abort_unless(Permissions::mayDelete($this->account(), $this->entry()), 403);

        app(Writer::class)->trash($this->id);
        session()->flash('status', __('Moved to the trash.'));
        $this->redirectRoute('wake.entries', ['type' => $this->type]);
    }

    /**
     * Store an uploaded image and hand its URL back to the editor.
     *
     * @return array{name: string, url: string}|null
     */
    public function storeUpload(): ?array
    {
        $this->validate(['upload' => 'required|file|max:20480|mimes:jpg,jpeg,png,gif,webp,avif,svg,pdf,zip,mp3,mp4']);

        if ($this->id === null) {
            $this->save();

            if ($this->id === null) {
                return null;
            }
        }

        $name = app(Writer::class)->addMedia($this->id, $this->upload->getClientOriginalName(), $this->upload->getRealPath());
        $this->upload = null;
        $file = $this->entry()?->file($name);

        return $file ? ['name' => $name, 'url' => $file->url()] : null;
    }

    public function restore(string $revision): void
    {
        abort_unless($this->id && $this->mayEdit($this->entry()), 403);

        $entry = app(Writer::class)->restore($this->id, $this->language, $revision);
        $this->loadEntry($entry);
        $this->dispatch('wake-editor-load', markdown: $this->body);
        session()->flash('status', __('The earlier version is back. The version before is kept as a revision.'));
    }

    public function setCover(string $name): void
    {
        $this->cover = $name;
    }

    /**
     * Called every 30 seconds by the editor to show who else is editing.
     */
    public function keepalive(): void
    {
        if ($this->id === null) {
            return;
        }

        Files::write($this->lockFile(), json_encode(['account' => $this->account()->id(), 'name' => $this->account()->name(), 'until' => time() + 75]));
    }

    // Rendering -------------------------------------------------------------

    public function render(): View
    {
        $entry = $this->entry();

        return view('wake::livewire.entry-editor', [
            'entry' => $entry,
            'contentType' => app(ContentTypes::class)->get($this->type),
            'languages' => app(Languages::class),
            'media' => $entry ? $entry->media()->values() : collect(),
            'mediaMap' => $this->mediaMap($entry),
            'blueprint' => app(ContentTypes::class)->get($this->type)->blueprint['fields'] ?? [],
            'mayPublish' => $this->mayPublish(),
            'otherEditor' => $this->otherEditor(),
            'stats' => $this->tab === 'stats' && $entry ? $this->stats($entry) : null,
            'revisions' => $this->tab === 'history' && $entry?->hasTranslation($this->language)
                ? app(Revisions::class)->of($entry->row()['languages'][$this->language]['file']) : [],
            'history' => $this->tab === 'history' && $entry ? app(History::class)->latest(20, $entry->id()) : [],
            'pages' => $this->type === 'page' ? (new Query('page'))->withFallback(true)->orderBy('title')->get() : collect(),
        ]);
    }

    /**
     * @return array<string, string> file name => media URL, for the editor
     */
    protected function mediaMap(?Entry $entry): array
    {
        return $entry ? $entry->media()->map(fn (Media $m) => $m->isImage() ? $m->url(1280) : $m->url())->all() : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function stats(Entry $entry): ?array
    {
        if (! $entry->hasTranslation($this->language)) {
            return null;
        }

        $path = parse_url($entry->translation($this->language)->url(), PHP_URL_PATH) ?: '/';
        $stats = app(Stats::class);
        $report = $stats->period('30d');

        return [
            'page' => $report->page($path),
            'series' => $stats->pageSeries($path, $report->from, $report->to),
            'path' => $path,
        ];
    }

    // Rules -----------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    protected function fields(): array
    {
        $fields = [
            'title' => trim($this->title),
            'slug' => $this->slug !== '' ? $this->slug : null,
            'tags' => $this->tags !== '' ? array_values(array_filter(array_map('trim', explode(',', $this->tags)))) : null,
            'excerpt' => $this->excerpt !== '' ? $this->excerpt : null,
            'cover' => $this->cover !== '' ? $this->cover : null,
            'template' => $this->template !== '' ? $this->template : null,
            'seo' => array_filter(['title' => $this->seoTitle, 'description' => $this->seoDescription]) ?: null,
        ];

        foreach ($this->custom as $key => $value) {
            if (! in_array($key, self::BASE_FIELDS, true)) {
                $fields[$key] = $value === '' ? null : $value;
            }
        }

        return $fields;
    }

    protected function account(): Account
    {
        return auth()->user();
    }

    protected function mayEdit(?Entry $entry): bool
    {
        return Permissions::mayEdit($this->account(), $entry);
    }

    protected function mayPublish(): bool
    {
        return Permissions::mayPublish($this->account(), $this->entry());
    }

    protected function authorizePublishing(): void
    {
        abort_unless($this->mayPublish(), 403);
    }

    /**
     * @return string|null name of someone else who has this entry open
     */
    protected function otherEditor(): ?string
    {
        $lock = $this->id ? json_decode((string) @file_get_contents($this->lockFile()), true) : null;

        return $lock && $lock['until'] > time() && $lock['account'] !== $this->account()->id() ? $lock['name'] : null;
    }

    protected function lockFile(): string
    {
        return storage_path('locks/edit-'.md5((string) $this->id).'.json');
    }
}

<?php

namespace Wake\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Wake\Accounts\Account;
use Wake\Accounts\Permissions;
use Wake\Content\Categories;
use Wake\Content\ContentTypes;
use Wake\Content\Entry;
use Wake\Content\History;
use Wake\Content\Index;
use Wake\Content\PageStyle;
use Wake\Content\Query;
use Wake\Content\Recurrence;
use Wake\Content\Revisions;
use Wake\Content\Writer;
use Wake\Http\Controllers\Panel\PreviewController;
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
    public const BASE_FIELDS = ['title', 'slug', 'status', 'published', 'author', 'tags', 'excerpt', 'cover', 'thumbnail', 'category', 'template', 'seo', 'noindex', 'via', 'review'];

    public const IMAGE_RULE = 'required|file|max:20480|mimes:jpg,jpeg,png,gif,webp,avif';

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

    /** Header image: shown large at the top of the post. */
    public string $cover = '';

    /** Preview image: used in lists, cards and when shared; falls back to the header image. */
    public string $thumbnail = '';

    public string $template = '';

    /** Key of the post category, e.g. "termine". */
    public string $category = '';

    public string $seoTitle = '';

    public string $seoDescription = '';

    public string $scheduleAt = '';

    /** @var array<string, mixed> values of blueprint fields */
    public array $custom = [];

    public string $tab = 'entry';

    /** The design of this entry only (style.css in its folder). */
    public string $style = '';

    /** @var mixed an image dropped into the text, stored by storeUpload() */
    public $upload = null;

    /** @var mixed uploads for header image, preview image and other files */
    public $coverUpload = null;

    public $thumbnailUpload = null;

    public $fileUpload = null;

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
        $this->style = $entry->style();
        $this->slug = (string) ($fields['slug'] ?? '');
        $this->tags = implode(', ', (array) ($fields['tags'] ?? ($translated ? [] : $entry->get('tags', []))));
        $this->excerpt = (string) ($fields['excerpt'] ?? '');
        $this->cover = (string) ($fields['cover'] ?? $entry->get('cover', ''));
        $this->thumbnail = (string) ($fields['thumbnail'] ?? $entry->get('thumbnail', ''));
        $this->template = (string) ($fields['template'] ?? '');
        $this->category = (string) ($fields['category'] ?? $entry->get('category', ''));
        $this->seoTitle = (string) data_get($fields, 'seo.title', '');
        $this->seoDescription = (string) data_get($fields, 'seo.description', '');
        // Custom fields: the translation's own values over the default
        // language's (dates and places are often only written once).
        $this->custom = $this->toInputs(Arr::except(
            array_replace($entry->row()['languages'][app(Languages::class)->default()]['fields'] ?? [], $translated ? $fields : []),
            self::BASE_FIELDS,
        ));
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

        foreach ($this->fieldDefinitions() as $key => $definition) {
            if (! empty($definition['required']) && ($this->custom[$key] ?? '') === '' && ($this->language === app(Languages::class)->default() || ($definition['translate'] ?? true) !== false)) {
                $this->addError('custom.'.$key, __('Please fill in “:field” before publishing.', ['field' => $this->fieldLabel($key, $definition)]));
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->save();

        if ($this->getErrorBag()->isEmpty()) {
            $at = $this->scheduleAt !== '' ? CarbonImmutable::parse($this->scheduleAt, config('app.timezone')) : null;
            app(Writer::class)->publish($this->id, $this->language, $at);
            $this->scheduleAt = '';
            session()->now('status', $at?->isFuture() ? __('Scheduled.') : __('Published.'));
        }
    }

    public function unpublish(): void
    {
        $this->authorizePublishing();
        app(Writer::class)->unpublish($this->id, $this->language);
        session()->now('status', __('Unpublished. The entry is a draft again.'));
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
        $this->validate(['upload' => self::IMAGE_RULE]);

        $name = $this->storeFile($this->upload);
        $this->upload = null;
        $file = $name ? $this->entry()?->file($name) : null;

        return $file ? ['name' => $name, 'url' => $file->url(1280)] : null;
    }

    public function updatedCoverUpload(): void
    {
        $this->validate(['coverUpload' => self::IMAGE_RULE]);

        if ($name = $this->storeFile($this->coverUpload)) {
            $this->cover = $name;
            $this->save();
        }

        $this->coverUpload = null;
    }

    public function updatedThumbnailUpload(): void
    {
        $this->validate(['thumbnailUpload' => self::IMAGE_RULE]);

        if ($name = $this->storeFile($this->thumbnailUpload)) {
            $this->thumbnail = $name;
            $this->save();
        }

        $this->thumbnailUpload = null;
    }

    public function updatedFileUpload(): void
    {
        $this->validate(['fileUpload' => MediaLibrary::FILE_RULE]);
        $this->storeFile($this->fileUpload);
        $this->fileUpload = null;
    }

    /**
     * Use an image of the entry as header image or preview image, or remove it
     * with an empty name.
     */
    public function setImage(string $slot, string $name): void
    {
        abort_unless(in_array($slot, ['cover', 'thumbnail'], true), 400);

        if ($name !== '' && ! $this->entry()?->file($name)?->isImage()) {
            return;
        }

        $this->{$slot} = $name;

        if ($this->id !== null) {
            $this->save();
        }
    }

    /**
     * Store an uploaded file in the entry's folder of the media library
     * (content/media/<id>/) and return its reference. A new entry is saved as a
     * draft first, with a placeholder title if it has none yet, so nothing
     * uploaded gets lost.
     */
    protected function storeFile(mixed $upload): ?string
    {
        if ($this->id === null) {
            if (trim($this->title) === '') {
                $this->title = __('Untitled');
            }

            $this->save();

            if ($this->id === null) {
                return null;
            }
        }

        abort_unless($this->mayEdit($this->entry()), 403);

        return app(Writer::class)->addMedia($this->id, $upload->getClientOriginalName(), $upload->getRealPath());
    }

    /**
     * Saves style.css of the entry. Same rights as the text; Wake cleans it
     * and limits it to the content of this page.
     */
    public function saveStyle(): void
    {
        $entry = $this->entry();
        abort_unless($entry && $this->mayEdit($entry), 403);

        $this->validate(['style' => 'nullable|string|max:'.PageStyle::MAX_BYTES]);

        app(Writer::class)->saveStyle($entry->id(), $this->style);
        $this->style = (string) $this->entry()?->style();
        session()->flash('status', __('Design saved.'));
    }

    /**
     * Save and open the preview in a new tab.
     */
    public function preview(): void
    {
        $this->save();

        if ($this->id !== null && $this->getErrorBag()->isEmpty()) {
            $this->dispatch('wake-open', url: route('wake.preview', ['id' => $this->id, 'language' => $this->language]));
        }
    }

    /** A preview link others can open without an account, valid for a week. */
    public ?string $sharedLink = null;

    public function shareLink(): void
    {
        $this->save();

        if ($this->id !== null && $this->getErrorBag()->isEmpty()) {
            $this->sharedLink = PreviewController::sharedUrl($this->entry());
        }
    }

    public function restore(string $revision): void
    {
        abort_unless($this->id && $this->mayEdit($this->entry()), 403);

        $entry = app(Writer::class)->restore($this->id, $this->language, $revision);
        $this->loadEntry($entry);
        $this->dispatch('wake-editor-load', markdown: $this->body);
        session()->now('status', __('The earlier version is back. The version before is kept as a revision.'));
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
            'categories' => $this->type === 'post' ? app(Categories::class)->all() : [],
            'occurrences' => $this->previewOccurrences(),
            'categoryFields' => $this->type === 'post' ? (app(Categories::class)->get($this->category)?->fields() ?? []) : [],
            'mayPublish' => $this->mayPublish(),
            'mayEdit' => $entry !== null && $this->mayEdit($entry),
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
            'thumbnail' => $this->thumbnail !== '' ? $this->thumbnail : null,
            'template' => $this->template !== '' ? $this->template : null,
            'category' => $this->category !== '' && $this->type === 'post' ? $this->category : null,
            'seo' => array_filter(['title' => $this->seoTitle, 'description' => $this->seoDescription]) ?: null,
        ];

        $definitions = $this->fieldDefinitions();
        $isTranslation = $this->id !== null && $this->language !== app(Languages::class)->default();

        foreach ($this->custom as $key => $value) {
            if (in_array($key, self::BASE_FIELDS, true)) {
                continue;
            }

            $definition = $definitions[$key] ?? [];

            // Fields marked "translate: false" live in the default language only.
            if ($isTranslation && ($definition['translate'] ?? true) === false) {
                continue;
            }

            $fields[$key] = ! empty($definition['list'])
                ? (array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $value)))) ?: null)
                : $this->fromInput($value, $definition['type'] ?? 'text');
        }

        return $fields;
    }

    /**
     * The next dates of an event as they are in the form right now, so a
     * repetition can be checked before saving.
     *
     * @return array{rule: string, dates: list<CarbonImmutable>}|null
     */
    protected function previewOccurrences(): ?array
    {
        $fields = [];

        foreach ($this->fieldDefinitions() as $key => $definition) {
            $fields[$key] = ! empty($definition['list'])
                ? preg_split('/\R/', (string) ($this->custom[$key] ?? ''))
                : $this->fromInput($this->custom[$key] ?? null, $definition['type'] ?? 'text');
        }

        $recurrence = Recurrence::fromFields($fields);

        if (! $recurrence?->isRecurring()) {
            return null;
        }

        return [
            'rule' => $recurrence->describe(),
            'dates' => array_column($recurrence->between(now()->startOfDay(), now()->addYears(2), 8), 'start'),
        ];
    }

    public function updatedCategory(): void
    {
        foreach (array_keys($this->fieldDefinitions()) as $key) {
            $this->custom[$key] ??= '';
        }
    }

    /**
     * Field definitions of the content type's blueprint and of the chosen
     * category.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function fieldDefinitions(): array
    {
        $blueprint = app(ContentTypes::class)->get($this->type)->blueprint['fields'] ?? [];
        $category = $this->type === 'post' ? app(Categories::class)->get($this->category) : null;

        return array_replace($blueprint, $category?->fields() ?? []);
    }

    /**
     * Stored values as form values: datetime fields need "Y-m-d\TH:i".
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function toInputs(array $values): array
    {
        foreach ($this->fieldDefinitions() as $key => $definition) {
            $value = $values[$key] ?? '';

            if (($definition['type'] ?? '') === 'datetime' && is_string($value) && $value !== '') {
                $value = str_replace(' ', 'T', substr($value, 0, 16));
            }

            if (($definition['type'] ?? '') === 'toggle') {
                $value = (bool) $value;
            }

            // List fields ("list: true") are edited one item per line.
            if (! empty($definition['list']) && is_array($value)) {
                $value = implode("\n", $value);
            }

            $values[$key] = $value;
        }

        return $values;
    }

    protected function fromInput(mixed $value, string $type): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return match ($type) {
            'datetime' => str_replace('T', ' ', substr((string) $value, 0, 16)),
            'toggle' => (bool) $value ?: null,
            'number' => is_numeric($value) ? $value + 0 : null,
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public function fieldLabel(string $key, array $definition): string
    {
        $label = $definition['label'] ?? null;

        if (is_array($label)) {
            $label = $label[app()->getLocale()] ?? $label['en'] ?? reset($label);
        }

        return (string) ($label ?: Str::headline($key));
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

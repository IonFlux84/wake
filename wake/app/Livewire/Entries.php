<?php

namespace Wake\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Wake\Content\Categories;
use Wake\Content\ContentTypes;
use Wake\Content\Entry;
use Wake\Content\Query;
use Wake\Localization\Languages;

/**
 * The list of posts, pages or entries of a custom type, with status filters,
 * search and the state of every translation.
 */
class Entries extends Component
{
    use WithPagination;

    public string $type = 'post';

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = '';

    public function mount(string $type = 'post'): void
    {
        abort_unless(app(ContentTypes::class)->get($type), 404);
        $this->type = $type;
    }

    public function setStatus(string $status): void
    {
        $this->status = in_array($status, ['all', 'published', 'draft', 'scheduled'], true) ? $status : 'all';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * current, outdated (the default language changed after the translation)
     * or missing, for every language of the site.
     *
     * @return array<string, string>
     */
    public static function translationStates(Entry $entry): array
    {
        $languages = app(Languages::class);
        $row = $entry->row();
        $original = $row['languages'][$languages->default()]['mtime'] ?? 0;
        $states = [];

        foreach ($languages->codes() as $code) {
            $states[$code] = match (true) {
                ! isset($row['languages'][$code]) => 'missing',
                $code !== $languages->default() && $row['languages'][$code]['mtime'] < $original => 'outdated',
                default => 'current',
            };
        }

        return $states;
    }

    public function render(): View
    {
        $base = fn () => (new Query($this->type))->withFallback(true)->in(app(Languages::class)->default());
        $query = $base();

        match ($this->status) {
            'published' => $query->published(),
            'draft' => $query->drafts(),
            'scheduled' => $query->scheduled(),
            default => null,
        };

        if ($this->category !== '') {
            $query->category($this->category);
        }

        if (trim($this->search) !== '') {
            $needle = mb_strtolower(trim($this->search));
            $query->where(fn (array $fields, array $row) => str_contains(mb_strtolower((string) ($fields['title'] ?? $row['name'])), $needle));
        }

        if ($this->type === 'page') {
            $query->orderBy('title');
        }

        // Contributors only see their own entries.
        if (! auth()->user()->can('content.edit_all') && ! auth()->user()->can('content.publish')) {
            $query->author(auth()->user()->id());
        }

        return view('wake::livewire.entries', [
            'entries' => $query->paginate(20),
            'counts' => [
                'all' => $base()->count(),
                'published' => $base()->published()->count(),
                'draft' => $base()->drafts()->count(),
                'scheduled' => $base()->scheduled()->count(),
            ],
            'contentType' => app(ContentTypes::class)->get($this->type),
            'languages' => app(Languages::class),
            'categories' => $this->type === 'post' ? app(Categories::class)->all() : [],
        ]);
    }
}

<?php

namespace Wake\Livewire;

use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Wake\Content\Entry;
use Wake\Content\Index;
use Wake\Content\Menus;
use Wake\Content\Query;
use Wake\Localization\Languages;

/**
 * The main and footer menu (settings/menus.yml): pages and links, one level of
 * children, arranged by drag and drop or with the arrow buttons.
 */
class MenuEditor extends Component
{
    #[Url]
    public string $menu = 'main';

    /** @var list<array{key: string, page: ?string, url: string, label: array<string, string>, children: list<array<string, mixed>>}> */
    public array $items = [];

    /** Whether the menu is stored in settings/menus.yml (the main menu follows the page tree otherwise). */
    public bool $custom = false;

    public string $addPage = '';

    public string $linkLabel = '';

    public string $linkUrl = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('structure.manage'), 403);
        $this->load();
    }

    public function updatedMenu(): void
    {
        $this->menu = in_array($this->menu, Menus::NAMES, true) ? $this->menu : 'main';
        $this->load();
    }

    /**
     * Start editing the main menu with what the page tree shows now.
     */
    public function customize(): void
    {
        $this->custom = true;
        $this->items = app(Menus::class)->items('main', app(Languages::class)->default())
            ->map(fn ($item) => $this->item($item->page()?->id(), '', [], $item->children()->map(fn ($child) => $this->item($child->page()?->id()))->all()))
            ->all();
    }

    public function addPageItem(): void
    {
        if ($this->addPage !== '' && app(Index::class)->find($this->addPage)) {
            $this->startCustom();
            $this->items[] = $this->item($this->addPage);
        }

        $this->addPage = '';
    }

    public function addLink(): void
    {
        $this->validate([
            'linkLabel' => 'required|string|max:80',
            'linkUrl' => ['required', 'string', 'max:500', 'regex:#^(https?://|/|mailto:|tel:)#'],
        ], ['linkUrl.regex' => __('Enter a full address starting with https://, or a path starting with /.')]);

        $this->startCustom();
        $this->items[] = $this->item(null, $this->linkUrl, [app(Languages::class)->default() => $this->linkLabel]);
        $this->reset('linkLabel', 'linkUrl');
    }

    /**
     * Adding to the automatic main menu starts from what it shows now.
     */
    protected function startCustom(): void
    {
        if (! $this->custom && $this->menu === 'main') {
            $this->customize();
        }

        $this->custom = true;
    }

    public function remove(string $key): void
    {
        $this->take($key);
    }

    /**
     * Drop $key before, after or inside $target.
     */
    public function move(string $key, string $target, string $where): void
    {
        if ($key === $target || ! ($item = $this->find($key)) || ! $this->find($target)) {
            return;
        }

        // Only one level of children.
        $targetIsChild = $this->parentOf($target) !== null;
        if (($where === 'inside' || $targetIsChild) && $item['children'] !== []) {
            return;
        }
        if ($where === 'inside' && $targetIsChild) {
            $where = 'after';
        }

        $item = $this->take($key);
        $this->insert($item, $target, $where);
    }

    public function step(string $key, string $direction): void
    {
        $parent = $this->parentOf($key);
        $list = $parent === null ? $this->items : $this->find($parent)['children'];
        $keys = array_column($list, 'key');
        $index = array_search($key, $keys, true);

        match ($direction) {
            'up' => $index > 0 ? $this->move($key, $keys[$index - 1], 'before') : null,
            'down' => $index < count($keys) - 1 ? $this->move($key, $keys[$index + 1], 'after') : null,
            'in' => $index > 0 && $parent === null ? $this->move($key, $keys[$index - 1], 'inside') : null,
            'out' => $parent !== null ? $this->move($key, $parent, 'after') : null,
            default => null,
        };
    }

    public function save(Menus $menus): void
    {
        $strip = fn (array $item) => [
            'page' => $item['page'],
            'url' => $item['url'],
            'label' => $item['label'],
            'children' => array_map(fn ($child) => ['page' => $child['page'], 'url' => $child['url'], 'label' => $child['label']], $item['children']),
        ];

        $menus->save($this->menu, array_map($strip, $this->items));
        $this->custom = true;
        session()->flash('status', __('Menu saved.'));
    }

    /**
     * The main menu follows the page tree again.
     */
    public function automatic(Menus $menus): void
    {
        $menus->save($this->menu, null);
        $this->load();
        session()->flash('status', __('The main menu follows the page tree again.'));
    }

    public function render(): View
    {
        $pages = (new Query('page'))->withFallback(true)->in(app(Languages::class)->default())->get()
            ->sortBy(fn (Entry $page) => $page->id())
            ->keyBy(fn (Entry $page) => $page->id());

        return view('wake::livewire.menu-editor', [
            'pages' => $pages,
            'languages' => app(Languages::class),
            'preview' => $this->custom ? null : app(Menus::class)->items($this->menu, app(Languages::class)->default()),
        ]);
    }

    protected function load(): void
    {
        $menus = app(Menus::class);
        $this->custom = $menus->isCustom($this->menu);
        $this->items = array_map(fn (array $item) => $this->fromStored($item), $menus->all()[$this->menu] ?? []);
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    protected function fromStored(array $stored, bool $withChildren = true): array
    {
        $label = $stored['label'] ?? [];
        $label = is_array($label) ? $label : [app(Languages::class)->default() => (string) $label];

        return $this->item(
            isset($stored['page']) ? (string) $stored['page'] : null,
            (string) ($stored['url'] ?? ''),
            $label,
            $withChildren ? array_map(fn ($child) => $this->fromStored($child, false), array_filter((array) ($stored['children'] ?? []), 'is_array')) : [],
        );
    }

    /**
     * @param  array<string, string>  $label
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    protected function item(?string $page, string $url = '', array $label = [], array $children = []): array
    {
        $labels = [];
        foreach (app(Languages::class)->codes() as $code) {
            $labels[$code] = (string) ($label[$code] ?? '');
        }

        return ['key' => Str::random(8), 'page' => $page, 'url' => $url, 'label' => $labels, 'children' => array_values($children)];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function find(string $key): ?array
    {
        foreach ($this->items as $item) {
            if ($item['key'] === $key) {
                return $item;
            }
            foreach ($item['children'] as $child) {
                if ($child['key'] === $key) {
                    return $child;
                }
            }
        }

        return null;
    }

    protected function parentOf(string $key): ?string
    {
        foreach ($this->items as $item) {
            if (in_array($key, array_column($item['children'], 'key'), true)) {
                return $item['key'];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function take(string $key): array
    {
        $found = [];

        foreach ($this->items as $i => $item) {
            if ($item['key'] === $key) {
                $found = $item;
                unset($this->items[$i]);
                break;
            }
            foreach ($item['children'] as $j => $child) {
                if ($child['key'] === $key) {
                    $found = $child;
                    unset($this->items[$i]['children'][$j]);
                    $this->items[$i]['children'] = array_values($this->items[$i]['children']);
                    break 2;
                }
            }
        }

        $this->items = array_values($this->items);

        return $found;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function insert(array $item, string $target, string $where): void
    {
        foreach ($this->items as $i => $top) {
            if ($top['key'] === $target) {
                if ($where === 'inside') {
                    $this->items[$i]['children'][] = $item;
                } else {
                    array_splice($this->items, $i + ($where === 'after' ? 1 : 0), 0, [$item]);
                }

                return;
            }
            foreach ($top['children'] as $j => $child) {
                if ($child['key'] === $target) {
                    array_splice($this->items[$i]['children'], $j + ($where === 'after' ? 1 : 0), 0, [$item]);

                    return;
                }
            }
        }
    }
}

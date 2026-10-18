<?php

namespace Wake\Livewire;

use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Component;
use Wake\Content\Entry;
use Wake\Content\Index;
use Wake\Content\Query;
use Wake\Content\Writer;
use Wake\Localization\Languages;

/**
 * The pages as a tree. People with "structure.manage" arrange it by drag
 * and drop or with the arrow buttons; the folders on disk follow.
 */
class PageTree extends Component
{
    /**
     * Drop $id before, after or inside $target.
     */
    public function move(string $id, string $target, string $where): void
    {
        $this->authorizeStructure();
        $rows = app(Index::class)->ofType('page');

        if (! isset($rows[$id], $rows[$target]) || $id === $target) {
            return;
        }

        if ($where === 'inside') {
            $this->apply($id, $target, null);

            return;
        }

        $parent = $rows[$target]['parent'];
        $index = array_search($target, $this->children($parent, $id), true);
        $this->apply($id, $parent, $index + ($where === 'after' ? 1 : 0));
    }

    /**
     * The keyboard way: up, down, in (into the page above) and out.
     */
    public function step(string $id, string $direction): void
    {
        $this->authorizeStructure();
        $row = app(Index::class)->find($id) ?? abort(404);
        $siblings = $this->children($row['parent']);
        $index = array_search($id, $siblings, true);

        match ($direction) {
            'up' => $index > 0 ? $this->apply($id, $row['parent'], $index - 1) : null,
            'down' => $index !== false && $index < count($siblings) - 1 ? $this->apply($id, $row['parent'], $index + 1) : null,
            'in' => $index > 0 ? $this->apply($id, $siblings[$index - 1], null) : null,
            'out' => $row['parent'] !== null
                ? $this->apply($id, app(Index::class)->find($row['parent'])['parent'], array_search($row['parent'], $this->children(app(Index::class)->find($row['parent'])['parent']), true) + 1)
                : null,
            default => null,
        };
    }

    /**
     * In or out of the automatic main menu ("menu: true").
     */
    public function toggleListed(string $id): void
    {
        $this->authorizeStructure();
        $row = app(Index::class)->find($id) ?? abort(404);

        app(Writer::class)->setFields($id, ['menu' => ($row['menu'] ?? false) ? null : true]);
    }

    public function render(): View
    {
        $pages = (new Query('page'))->withFallback(true)->in(app(Languages::class)->default())->get();

        return view('wake::livewire.page-tree', [
            'tree' => $this->branch($pages->keyBy(fn (Entry $page) => $page->id())->all(), null, 0),
            'canArrange' => auth()->user()->can('structure.manage'),
        ]);
    }

    protected function apply(string $id, ?string $parent, ?int $position): void
    {
        try {
            app(Writer::class)->movePage($id, $parent, $position);
        } catch (InvalidArgumentException $e) {
            session()->flash('status', match ($e->getMessage()) {
                'The home page stays where it is.' => __('The home page stays where it is.'),
                'A page cannot be moved into itself.' => __('A page cannot be moved into itself.'),
                default => $e->getMessage(),
            });
        }
    }

    /**
     * Ids of the pages under $parent, in the order of the page tree.
     *
     * @return list<string>
     */
    protected function children(?string $parent, ?string $without = null): array
    {
        return collect(app(Index::class)->ofType('page'))
            ->filter(fn (array $row) => $row['parent'] === $parent && $row['id'] !== $without)
            ->sortBy(fn (array $row) => [$row['order'] ?? PHP_INT_MAX, $row['name']])
            ->keys()->values()->all();
    }

    /**
     * @param  array<string, Entry>  $pages
     * @return list<array{page: Entry, depth: int, listed: bool, home: bool, first: bool, last: bool}>
     */
    protected function branch(array $pages, ?string $parent, int $depth): array
    {
        $children = collect($pages)
            ->filter(fn (Entry $page) => $page->row()['parent'] === $parent)
            ->sortBy(fn (Entry $page) => [$page->row()['order'] ?? PHP_INT_MAX, $page->row()['name']])
            ->values();
        $rows = [];

        foreach ($children as $i => $page) {
            $rows[] = [
                'page' => $page,
                'depth' => $depth,
                'listed' => (bool) ($page->row()['menu'] ?? false),
                'home' => $parent === null && $page->row()['name'] === 'home',
                'first' => $i === 0,
                'last' => $i === $children->count() - 1,
            ];
            array_push($rows, ...$this->branch($pages, $page->id(), $depth + 1));
        }

        return $rows;
    }

    protected function authorizeStructure(): void
    {
        abort_unless(auth()->user()->can('structure.manage'), 403);
    }
}

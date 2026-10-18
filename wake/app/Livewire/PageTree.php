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
            $this->apply($id, $target, count($this->listedChildren($target, $id)));

            return;
        }

        $parent = $rows[$target]['parent'];

        if ($rows[$target]['num'] === null) {
            $this->apply($id, $parent, null);

            return;
        }

        $index = array_search($target, $this->listedChildren($parent, $id), true);
        $this->apply($id, $parent, $index + ($where === 'after' ? 1 : 0));
    }

    /**
     * The keyboard way: up, down, in (into the page above) and out.
     */
    public function step(string $id, string $direction): void
    {
        $this->authorizeStructure();
        $row = app(Index::class)->find($id) ?? abort(404);
        $siblings = $this->listedChildren($row['parent']);
        $index = array_search($id, $siblings, true);

        match ($direction) {
            'up' => $index > 0 ? $this->apply($id, $row['parent'], $index - 1) : null,
            'down' => $index !== false && $index < count($siblings) - 1 ? $this->apply($id, $row['parent'], $index + 1) : null,
            'in' => $index > 0 ? $this->apply($id, $siblings[$index - 1], count($this->listedChildren($siblings[$index - 1]))) : null,
            'out' => $row['parent'] !== null
                ? $this->apply($id, app(Index::class)->find($row['parent'])['parent'], array_search($row['parent'], $this->listedChildren(app(Index::class)->find($row['parent'])['parent']), true) + 1)
                : null,
            default => null,
        };
    }

    /**
     * In or out of the menu order.
     */
    public function toggleListed(string $id): void
    {
        $this->authorizeStructure();
        $row = app(Index::class)->find($id) ?? abort(404);

        $this->apply($id, $row['parent'], $row['num'] === null ? count($this->listedChildren($row['parent'], $id)) : null);
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
     * Ids of the listed pages under $parent, in order.
     *
     * @return list<string>
     */
    protected function listedChildren(?string $parent, ?string $without = null): array
    {
        return collect(app(Index::class)->ofType('page'))
            ->filter(fn (array $row) => $row['parent'] === $parent && $row['num'] !== null && $row['id'] !== $without)
            ->sortBy([['num', 'asc'], ['name', 'asc']])
            ->keys()->values()->all();
    }

    /**
     * @param  array<string, Entry>  $pages
     * @return list<array{page: Entry, depth: int, listed: bool, first: bool, last: bool}>
     */
    protected function branch(array $pages, ?string $parent, int $depth): array
    {
        $children = collect($pages)->filter(fn (Entry $page) => $page->row()['parent'] === $parent);
        $listed = $children->filter(fn (Entry $page) => $page->row()['num'] !== null)->sortBy(fn (Entry $page) => $page->row()['num'])->values();
        $unlisted = $children->filter(fn (Entry $page) => $page->row()['num'] === null)->sortBy(fn (Entry $page) => mb_strtolower($page->title()))->values();
        $rows = [];

        foreach ($listed->concat($unlisted) as $page) {
            $isListed = $page->row()['num'] !== null;
            $rows[] = [
                'page' => $page,
                'depth' => $depth,
                'listed' => $isListed,
                'home' => $parent === null && $page->row()['name'] === 'home',
                'first' => $isListed && $listed->first() === $page,
                'last' => $isListed && $listed->last() === $page,
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

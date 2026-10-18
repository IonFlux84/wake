<?php

use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Content\Index;
use Wake\Content\Menus;
use Wake\Content\Redirects;
use Wake\Content\Revisions;
use Wake\Content\Site;
use Wake\Content\Writer;
use Wake\Livewire\MenuEditor;
use Wake\Livewire\PageTree;
use Wake\Support\Files;
use Wake\Support\Paths;

function pageIds(): array
{
    return collect(app(Index::class)->entries())->where('type', 'page')->keys()->sort()->values()->all();
}

function pageOrder(?string $parent = null): array
{
    return collect(app(Index::class)->entries())->where('type', 'page')->where('parent', $parent)
        ->sortBy(fn (array $row) => [$row['order'] ?? PHP_INT_MAX, $row['name']])->keys()->values()->all();
}

it('reorders pages by writing "order" into their files', function () {
    $id = app(Writer::class)->movePage('pages/blog', null, 0);

    expect($id)->toBe('pages/blog')
        ->and(pageOrder())->toBe(['pages/blog', 'pages/about', 'pages/contact', 'pages/home'])
        ->and(app(Index::class)->find('pages/home')['order'])->toBe(4)
        ->and(pageIds())->toBe(['pages/about', 'pages/about/team', 'pages/blog', 'pages/contact', 'pages/home'])
        ->and(app(Site::class)->menu()->map->title()->all())->toBe(['Blog', 'About']);
});

it('moves a page under another parent and redirects its old address', function () {
    $id = app(Writer::class)->movePage('pages/about/team', null, 2);

    expect($id)->toBe('pages/team')
        ->and(app(Index::class)->find('pages/team')['parent'])->toBeNull()
        ->and(pageOrder())->toBe(['pages/about', 'pages/blog', 'pages/team', 'pages/contact', 'pages/home'])
        ->and(app(Redirects::class)->target('en', 'about/team'))->toBe('pages/team');

    $this->get('/about/team')->assertRedirect('/team');
});

it('moves a page into another page without changing the menu', function () {
    $writer = app(Writer::class);

    expect($writer->movePage('pages/contact', 'pages/about', 0))->toBe('pages/about/contact')
        ->and(pageOrder('pages/about'))->toBe(['pages/about/contact', 'pages/about/team'])
        ->and(app(Site::class)->menu()->first()->children()->map->title()->all())->toBe(['Team']);
});

it('never renames a folder when only the order changes', function () {
    expect(app(Writer::class)->movePage('pages/contact', null, 0))->toBe('pages/contact')
        ->and(is_file(Paths::content('pages/contact/content.md')))->toBeTrue()
        ->and(app(Index::class)->find('pages/about')['order'])->toBe(2);
});

it('keeps versions, redirects and menus pointing at moved pages', function () {
    $writer = app(Writer::class);
    $writer->update('pages/about/team', 'en', ['title' => 'Our team']);
    app(Redirects::class)->add('en', 'crew', 'pages/about/team');
    app(Menus::class)->save('footer', [['page' => 'pages/about/team']]);

    $writer->movePage('pages/about/team', 'pages/blog', 0);

    expect(app(Revisions::class)->of('pages/blog/team/content.md'))->toHaveCount(1)
        ->and(app(Redirects::class)->target('en', 'crew'))->toBe('pages/blog/team')
        ->and(app(Menus::class)->all()['footer'][0]['page'])->toBe('pages/blog/team')
        ->and(app(Site::class)->menu('footer')->map->title()->all())->toBe(['Our team']);
});

it('gives a moved page a free name', function () {
    app(Writer::class)->create('page', ['title' => 'Team', 'status' => 'published']);

    expect(app(Writer::class)->movePage('pages/about/team', null, null))->toBe('pages/team-2');
});

it('refuses impossible moves', function (string $id, ?string $parent) {
    app(Writer::class)->movePage($id, $parent, 0);
})->with([
    'into itself' => ['pages/about', 'pages/about'],
    'into its child' => ['pages/about', 'pages/about/team'],
    'the home page' => ['pages/home', 'pages/about'],
    'a post' => ['posts/first', null],
])->throws(InvalidArgumentException::class);

it('stores menus with pages and links', function () {
    $menus = app(Menus::class);
    $menus->save('main', [
        ['page' => 'pages/contact', 'label' => ['en' => 'Write to us', 'de' => '']],
        ['label' => 'Shop', 'url' => 'https://shop.example.org', 'children' => [['page' => 'pages/about']]],
        ['page' => 'pages/missing'],
    ]);

    $menu = app(Site::class)->menu();

    expect($menu->map->title()->all())->toBe(['Write to us', 'Shop'])
        ->and($menu[1]->isExternal())->toBeTrue()
        ->and($menu[1]->children()->first()->title())->toBe('About')
        ->and(file_get_contents($menus->file()))->not->toContain("de: ''");

    $menus->save('main', null);

    expect(app(Site::class)->menu()->map->title()->all())->toBe(['About', 'Blog']);
});

it('arranges pages in the panel tree', function () {
    $owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);

    $this->actingAs($owner)->get('/wake/pages')->assertOk()->assertSee('Page tree')->assertSee('not in the menu');

    Livewire::actingAs($owner)->test(PageTree::class)
        ->call('move', 'pages/contact', 'pages/about', 'before')
        ->call('step', 'pages/blog', 'in')
        ->call('toggleListed', 'pages/contact');

    expect(pageIds())->toBe(['pages/about', 'pages/about/blog', 'pages/about/team', 'pages/contact', 'pages/home'])
        ->and(pageOrder())->toBe(['pages/contact', 'pages/about', 'pages/home'])
        ->and(app(Index::class)->find('pages/contact')['menu'])->toBeTrue();

    Livewire::actingAs($owner)->test(PageTree::class)
        ->call('step', 'pages/about/blog', 'out')
        ->call('move', 'pages/about/team', 'pages/blog', 'inside');

    expect(pageIds())->toBe(['pages/about', 'pages/blog', 'pages/blog/team', 'pages/contact', 'pages/home'])
        ->and(pageOrder())->toBe(['pages/contact', 'pages/about', 'pages/blog', 'pages/home']);
});

it('lets only people with the right arrange pages and menus', function () {
    $author = app(Accounts::class)->create(['name' => 'Ali', 'email' => 'ali@example.com', 'password' => 'secret secret', 'role' => Role::Author]);

    Livewire::actingAs($author)->test(PageTree::class)->call('step', 'pages/blog', 'up')->assertForbidden();
    $this->actingAs($author)->get('/wake/menus')->assertForbidden();
});

it('edits menus in the panel', function () {
    $owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);

    $this->actingAs($owner)->get('/wake/menus')->assertOk()->assertSee('Follows the page tree');

    $editor = Livewire::actingAs($owner)->test(MenuEditor::class)
        ->call('customize')
        ->set('addPage', 'pages/contact')
        ->call('addPageItem')
        ->set('linkLabel', 'Shop')
        ->set('linkUrl', 'https://shop.example.org')
        ->call('addLink');

    $keys = collect($editor->get('items'))->pluck('key')->all(); // About (with Team), Blog, Contact, Shop
    $editor->call('move', $keys[3], $keys[0], 'before')
        ->call('step', $keys[2], 'in')
        ->set('items.0.label.de', 'Laden')
        ->call('save');

    expect(app(Site::class)->menu()->map->title()->all())->toBe(['Shop', 'About', 'Blog'])
        ->and(app(Site::class)->menu()[1]->children()->map->title()->all())->toBe(['Team'])
        ->and(app(Site::class)->menu()[2]->children()->map->title()->all())->toBe(['Contact']);

    Livewire::actingAs($owner)->withQueryParams(['menu' => 'footer'])->test(MenuEditor::class)
        ->set('linkLabel', 'Imprint')->set('linkUrl', 'not a url')->call('addLink')->assertHasErrors('linkUrl');

    Livewire::actingAs($owner)->withQueryParams(['menu' => 'main'])->test(MenuEditor::class)->call('automatic');

    expect(app(Menus::class)->isCustom('main'))->toBeFalse();
});

it('notices a menu edited by hand', function () {
    $index = app(Index::class);
    $index->refresh();

    Files::write(app(Menus::class)->file(), "main:\n  - page: pages/contact\n");
    touch(app(Menus::class)->file(), time() + 5);

    expect($index->refresh())->toBeTrue();
});

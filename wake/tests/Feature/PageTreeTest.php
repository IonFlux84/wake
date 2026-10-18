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
use Wake\Support\Paths;

function pageIds(): array
{
    return collect(app(Index::class)->entries())->where('type', 'page')->keys()->sort()->values()->all();
}

it('reorders pages by renaming their folders', function () {
    $id = app(Writer::class)->movePage('pages/2_blog', null, 0);

    expect($id)->toBe('pages/1_blog')
        ->and(pageIds())->toBe(['pages/1_blog', 'pages/2_about', 'pages/2_about/1_team', 'pages/contact', 'pages/home'])
        ->and(app(Site::class)->menu()->map->title()->all())->toBe(['Blog', 'About']);
});

it('moves a page under another parent and redirects its old address', function () {
    $id = app(Writer::class)->movePage('pages/1_about/1_team', null, 2);

    expect($id)->toBe('pages/3_team')
        ->and(app(Index::class)->find('pages/3_team')['parent'])->toBeNull()
        ->and(app(Redirects::class)->target('en', 'about/team'))->toBe('pages/3_team');

    $this->get('/about/team')->assertRedirect('/team');
});

it('moves a page out of the menu and into another page', function () {
    $writer = app(Writer::class);

    expect($writer->movePage('pages/2_blog', null, null))->toBe('pages/blog')
        ->and($writer->movePage('pages/contact', 'pages/1_about', 0))->toBe('pages/1_about/1_contact')
        ->and(pageIds())->toContain('pages/1_about/2_team')
        ->and(app(Site::class)->menu()->first()->children()->map->title()->all())->toBe(['Contact', 'Team']);
});

it('moves a page out of a sibling that is renamed at the same time', function () {
    $id = app(Writer::class)->movePage('pages/1_about/1_team', null, 0);

    expect($id)->toBe('pages/1_team')
        ->and(pageIds())->toBe(['pages/1_team', 'pages/2_about', 'pages/3_blog', 'pages/contact', 'pages/home'])
        ->and(is_file(Paths::content('pages/1_team/index.en.md')))->toBeTrue();
});

it('keeps versions, redirects and menus pointing at moved pages', function () {
    $writer = app(Writer::class);
    $writer->update('pages/1_about/1_team', 'en', ['title' => 'Our team']);
    app(Redirects::class)->add('en', 'crew', 'pages/1_about/1_team');
    app(Menus::class)->save('footer', [['page' => 'pages/1_about/1_team']]);

    $writer->movePage('pages/1_about', null, 1);

    expect(app(Revisions::class)->of('pages/2_about/1_team/index.en.md'))->toHaveCount(1)
        ->and(app(Redirects::class)->target('en', 'crew'))->toBe('pages/2_about/1_team')
        ->and(app(Menus::class)->all()['footer'][0]['page'])->toBe('pages/2_about/1_team')
        ->and(app(Site::class)->menu('footer')->map->title()->all())->toBe(['Our team']);
});

it('gives a moved page a free name', function () {
    app(Writer::class)->create('page', ['title' => 'Team', 'status' => 'published']);

    expect(app(Writer::class)->movePage('pages/1_about/1_team', null, null))->toBe('pages/team-2');
});

it('refuses impossible moves', function (string $id, ?string $parent) {
    app(Writer::class)->movePage($id, $parent, 0);
})->with([
    'into itself' => ['pages/1_about', 'pages/1_about'],
    'into its child' => ['pages/1_about', 'pages/1_about/1_team'],
    'the home page' => ['pages/home', 'pages/1_about'],
    'a post' => ['posts/2026-01-10-first', null],
])->throws(InvalidArgumentException::class);

it('stores menus with pages and links', function () {
    $menus = app(Menus::class);
    $menus->save('main', [
        ['page' => 'pages/contact', 'label' => ['en' => 'Write to us', 'de' => '']],
        ['label' => 'Shop', 'url' => 'https://shop.example.org', 'children' => [['page' => 'pages/1_about']]],
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
        ->call('move', 'pages/contact', 'pages/1_about', 'before')
        ->call('step', 'pages/3_blog', 'in')
        ->call('toggleListed', 'pages/1_contact');

    expect(pageIds())->toBe(['pages/1_about', 'pages/1_about/1_team', 'pages/1_about/2_blog', 'pages/contact', 'pages/home']);

    Livewire::actingAs($owner)->test(PageTree::class)
        ->call('step', 'pages/1_about/2_blog', 'out')
        ->call('move', 'pages/1_about/1_team', 'pages/2_blog', 'inside');

    expect(pageIds())->toBe(['pages/1_about', 'pages/2_blog', 'pages/2_blog/1_team', 'pages/contact', 'pages/home']);
});

it('lets only people with the right arrange pages and menus', function () {
    $author = app(Accounts::class)->create(['name' => 'Ali', 'email' => 'ali@example.com', 'password' => 'secret secret', 'role' => Role::Author]);

    Livewire::actingAs($author)->test(PageTree::class)->call('step', 'pages/2_blog', 'up')->assertForbidden();
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

<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Content\Categories;
use Wake\Content\Models\Post;
use Wake\Livewire\CategoryManager;
use Wake\Livewire\EntryEditor;
use Wake\Mcp\Tools\ContentListTool;
use Wake\Mcp\Tools\SiteInfoTool;
use Wake\Mcp\WakeServer;

beforeEach(function () {
    foreach ([
        'posts/2026-05-20-summer-fest/index.en.md' => <<<'MD'
            ---
            title: Summer fest
            slug: summer-fest
            published: 2026-05-20 10:00
            category: termine
            start: 2026-07-04 15:00
            end: 2026-07-04 22:00
            location: Park
            address: Main Street 1, Bochum
            price: free
            event_status: scheduled
            ---

            Music and food.
            MD,
        'posts/2026-05-20-summer-fest/index.de.md' => <<<'MD'
            ---
            title: Sommerfest
            slug: sommerfest
            published: 2026-05-20 10:00
            category: termine
            location: Park
            price: frei
            ---

            Musik und Essen.
            MD,
        'posts/2026-05-02-old-fair/index.en.md' => <<<'MD'
            ---
            title: Old fair
            published: 2026-05-02 10:00
            category: termine
            start: 2026-05-10
            all_day: true
            ---

            Over.
            MD,
    ] as $path => $contents) {
        $this->writeContent($path, $contents);
    }

    $this->owner = app(Accounts::class)->create(['name' => 'Dana', 'email' => 'dana@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);
});

it('reads categories with titles, addresses and fields per language', function () {
    $termine = app(Categories::class)->get('termine');

    expect($termine->title('de'))->toBe('Termine')
        ->and($termine->title('en'))->toBe('Events')
        ->and($termine->slug('de'))->toBe('termine')
        ->and($termine->label('start', 'de'))->toBe('Beginn')
        ->and($termine->dateField())->toBe('start')
        ->and(array_keys($termine->fields()))->toContain('start', 'end', 'location', 'event_status');
});

it('gives posts of a category their own addresses and template', function () {
    $post = Post::find('posts/2026-05-20-summer-fest');

    expect($post->url())->toBe(url('/events/summer-fest'))
        ->and($post->translation('de')->url())->toBe(url('/de/termine/sommerfest'))
        ->and($post->category()->key)->toBe('termine');

    $this->get('/events/summer-fest')->assertOk()->assertSee('"@type":"Event"', false);
});

it('inherits shared fields in translations', function () {
    $german = Post::find('posts/2026-05-20-summer-fest')->translation('de');

    expect($german->get('start'))->toBe('2026-07-04 15:00')
        ->and($german->get('price'))->toBe('frei');
});

it('lists upcoming and past events', function () {
    $termine = app(Categories::class)->get('termine');

    expect($termine->posts()->get()->map->title()->all())->toBe(['Summer fest'])
        ->and($termine->past()->get()->map->title()->all())->toBe(['Old fair'])
        ->and($termine->posts('de')->first()->title())->toBe('Sommerfest');
});

it('shows the category page in every language', function () {
    $this->get('/events')->assertOk()->assertSee('Summer fest');
    $this->get('/de/termine')->assertOk()->assertSee('Sommerfest');
});

it('offers a calendar file for events', function () {
    $this->get('/events/summer-fest.ics')->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=UTF-8')
        ->assertSee('SUMMARY:Summer fest')
        ->assertSee('DTSTART:20260704T130000Z')
        ->assertSee('LOCATION:Park\, Main Street 1\, Bochum', false);

    $this->get('/events/old-fair.ics')->assertOk()->assertSee('DTSTART;VALUE=DATE:20260510');
    $this->get('/first.ics')->assertNotFound();
});

it('shows category fields in the editor and stores them typed', function () {
    $component = Livewire::actingAs($this->owner)->test(EntryEditor::class, ['type' => 'post'])
        ->set('title', 'Choir concert')
        ->set('category', 'termine')
        ->assertSee('Venue')
        ->set('custom.start', '2026-12-20T18:00')
        ->set('custom.all_day', false)
        ->set('custom.location', 'Church')
        ->call('save');

    $post = Post::find($component->get('id'));

    expect($post->get('category'))->toBe('termine')
        ->and($post->get('start'))->toBe('2026-12-20 18:00')
        ->and($post->get('all_day'))->toBeNull()
        ->and($post->url())->toBe(url('/events/choir-concert'));
});

it('requires required category fields before publishing', function () {
    Livewire::actingAs($this->owner)->test(EntryEditor::class, ['type' => 'post'])
        ->set('title', 'No date yet')->set('category', 'termine')
        ->call('publish')
        ->assertHasErrors('custom.start');
});

it('does not overwrite shared fields from a translation', function () {
    Livewire::withQueryParams(['id' => 'posts/2026-05-20-summer-fest', 'language' => 'de'])->actingAs($this->owner)
        ->test(EntryEditor::class, ['type' => 'post'])
        ->assertSet('custom.start', '2026-07-04T15:00')
        ->set('custom.start', '2030-01-01T00:00')
        ->set('custom.location', 'Stadtpark')
        ->call('save');

    $post = Post::find('posts/2026-05-20-summer-fest');

    expect($post->get('start'))->toBe('2026-07-04 15:00')
        ->and($post->translation('de')->get('location'))->toBe('Stadtpark');
});

it('creates and edits categories in the panel', function () {
    Livewire::actingAs($this->owner)->test(CategoryManager::class)
        ->call('create')
        ->set('titles.en', 'Reports')->set('titles.de', 'Berichte')
        ->set('slugs.de', 'berichte')
        ->set('fields.0.key', 'photographer')->set('fields.0.label_de', 'Fotograf')->set('fields.0.type', 'text')
        ->call('save')
        ->assertHasNoErrors();

    $reports = app(Categories::class)->get('reports');

    expect($reports->title('de'))->toBe('Berichte')
        ->and($reports->label('photographer', 'de'))->toBe('Fotograf')
        ->and($reports->listMode())->toBe('newest');

    Livewire::actingAs($this->owner)->test(CategoryManager::class)
        ->call('edit', 'termine')->assertSet('fields.0.key', 'start')->assertSet('fields.0.label_de', 'Beginn')
        ->call('save')->assertHasNoErrors();

    expect(app(Categories::class)->get('termine')->fields()['event_status']['options']['cancelled']['de'])->toBe('Abgesagt');
});

it('rejects field names Wake uses itself', function () {
    Livewire::actingAs($this->owner)->test(CategoryManager::class)
        ->call('create')->set('titles.en', 'X')->set('fields.0.key', 'title')
        ->call('save')->assertHasErrors('fields.0.key');
});

it('lets only administrators manage categories', function () {
    $editor = app(Accounts::class)->create(['name' => 'Ed', 'email' => 'ed@example.com', 'password' => 'secret secret', 'role' => Role::Editor]);

    $this->actingAs($editor)->get('/wake/categories')->assertForbidden();
});

it('tells assistants about categories and filters by them', function () {
    WakeServer::tool(SiteInfoTool::class)->assertOk()->assertSee('termine')->assertSee('start');
    WakeServer::tool(ContentListTool::class, ['category' => 'termine'])->assertOk()->assertSee('Summer fest')->assertDontSee('First post');
    $this->getJson('/wake/api/v1/entries?category=termine')->assertOk()->assertJsonPath('data.0.title', 'Summer fest')->assertJsonCount(1, 'data');
});

describe('recurring events', function () {
    beforeEach(function () {
        $this->writeContent('posts/2026-05-21-choir/index.en.md', "---\ntitle: Choir\nslug: choir\npublished: 2026-05-21 10:00\ncategory: termine\nstart: 2026-05-26 19:00\nend: 2026-05-26 21:00\nrepeat: weekly\nrepeat_until: 2026-07-28\nrepeat_except: [2026-06-09]\nlocation: Hall\n---\n\nWe sing.\n");
    });

    it('lists each date in the category and counts as upcoming', function () {
        $termine = app(Categories::class)->get('termine');
        $june = collect($termine->occurrences(CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30 23:59')))
            ->filter(fn ($o) => $o['entry']->slug() === 'choir')
            ->map(fn ($o) => $o['start']->format('d.m.'))->values()->all();

        expect($june)->toBe(['02.06.', '16.06.', '23.06.', '30.06.'])
            ->and($termine->posts()->get()->map->title()->all())->toBe(['Choir', 'Summer fest']);
    });

    it('shows the rule and the next date on the event page', function () {
        $this->get('/events/choir')->assertOk()->assertSee('"startDate":"2026-06-02T19:00:00+02:00"', false);
    });

    it('puts the rule and exceptions into the calendar file', function () {
        $this->get('/events/choir.ics')->assertOk()
            ->assertSee('RRULE:FREQ=WEEKLY;UNTIL=20260728T215959Z')
            ->assertSee('EXDATE:20260609T170000Z');
    });

    it('becomes past after its last date', function () {
        Carbon::setTestNow('2026-08-15 12:00:00');

        expect(app(Categories::class)->get('termine')->past()->get()->map->title()->all())->toContain('Choir');
    });

    it('edits exceptions one per line and previews the dates', function () {
        Livewire::withQueryParams(['id' => 'posts/2026-05-21-choir'])->actingAs($this->owner)
            ->test(EntryEditor::class, ['type' => 'post'])
            ->assertSet('custom.repeat_except', '2026-06-09')
            ->assertSee('Every Tuesday')
            ->set('custom.repeat_except', "2026-06-09\n2026-06-16")
            ->call('save');

        expect(Post::find('posts/2026-05-21-choir')->get('repeat_except'))->toBe(['2026-06-09', '2026-06-16']);
    });
});

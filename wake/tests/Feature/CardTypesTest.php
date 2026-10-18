<?php

use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Content\Models\Post;
use Wake\Content\Writer;
use Wake\Livewire\EntryEditor;
use Wake\Markdown\Cards\Card;
use Wake\Markdown\Cards\CardTypes;
use Wake\Mcp\Tools\ContentCardsTool;
use Wake\Mcp\Tools\ContentPublishTool;
use Wake\Mcp\WakeServer;
use Wake\Support\Files;
use Wake\Support\Paths;

beforeEach(function () {
    Files::write(Paths::theme('templates/cards/member.yml'), <<<'YAML'
        title: { en: Board member, de: Vorstandsmitglied }
        description: { en: A person with photo and role. }
        hint: 👤
        value: { label: { en: Name, de: Name }, required: true }
        content: false
        fields:
          role: { type: text, label: { en: Role, de: Amt }, required: true }
          photo: { type: image, label: { en: Photo } }
          tone: { type: select, options: { light: { en: Light }, dark: { en: Dark } }, default: light }
          weird key: { type: text }
        YAML);
    Files::write(Paths::theme('templates/cards/member.blade.php'),
        '<div class="member member-{{ $attributes[\'tone\'] ?? \'\' }}">{{ $value }} · {{ $attributes[\'role\'] ?? \'\' }} · {{ $images[\'photo\']?->url() ?? \'no photo\' }} · {{ $card?->title() }}</div>');
    Files::write(Paths::theme('templates/cards/callout.yml'), "title: { en: Theme note }\nhint: ✳\n");
});

it('reads card descriptions from Wake, plugins and the theme, the theme winning', function () {
    $cards = app(CardTypes::class);

    expect(array_keys($cards->all()))->toContain('button', 'embed', 'divider', 'member')
        ->and($cards->get('callout')->title())->toBe('Theme note')
        ->and($cards->get('callout')->source)->toBe('theme')
        ->and($cards->get('button')->source)->toBe('wake')
        ->and($cards->get('member')->title('de'))->toBe('Vorstandsmitglied')
        ->and(array_keys($cards->get('member')->fields()))->toBe(['role', 'photo', 'tone'])
        ->and($cards->get('member')->fields()['tone']['options'])->toBe(['light' => 'Light', 'dark' => 'Dark'])
        ->and($cards->get('member')->hasContent())->toBeFalse()
        ->and($cards->get('member')->example())->toStartWith('::: member … role="…" photo="photo.jpg" tone="light"')
        ->and($cards->get('divider')->inMenu())->toBeFalse();
});

it('names the required settings a card leaves empty', function () {
    $member = app(CardTypes::class)->get('member');

    expect($member->missing(new Card('member', ['role' => ''], '')))->toBe(['Name', 'Role'])
        ->and($member->missing(new Card('member', ['role' => 'Chair'], 'Jane')))->toBe([]);
});

it('hands settings and image files to the card view', function () {
    app(Writer::class)->update('posts/first', 'en', body: "::: member Jane Doe role=\"Chair\" photo=\"photo.png\" tone=\"dark\"\n:::");

    $this->get('/first')->assertOk()
        ->assertSee('member-dark', false)
        ->assertSee('Jane Doe · Chair · ', false)
        ->assertSee('/photo.png', false)
        ->assertSee('Board member');
});

it('gives the editor the cards for its forms and / menu', function () {
    $owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);

    Livewire::withQueryParams(['id' => 'posts/first'])->actingAs($owner)->test(EntryEditor::class, ['type' => 'post'])
        ->assertViewHas('cards', fn (array $cards) => collect($cards)->firstWhere('name', 'member')['fields'][0]['key'] === 'role');
});

it('describes the cards to assistants', function () {
    $owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);
    $this->actingAs($owner);

    WakeServer::tool(ContentCardsTool::class, ['language' => 'de'])->assertOk()
        ->assertSee('Vorstandsmitglied')->assertSee('"required":true')->assertSee('::: member');
});

it('shows card descriptions that cannot be read on the themes page', function () {
    Files::write(Paths::theme('templates/cards/broken.yml'), "title: { en: A, b, c }\nfields: [\n");
    $owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);

    expect(app(CardTypes::class)->get('broken'))->toBeNull();

    $this->actingAs($owner)->get('/wake/themes')->assertOk()->assertSee('broken.yml');
});

it('asks before publishing an entry with incomplete cards', function () {
    $owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);
    app(Writer::class)->update('posts/draft', 'en', body: "Text\n\n::: member Jane Doe\n:::\n\n```\n::: member\n```");

    expect(app(CardTypes::class)->missingIn(Post::find('posts/draft')->body()))->toBe([['card' => 'Board member', 'missing' => ['Role']]]);

    $editor = Livewire::withQueryParams(['id' => 'posts/draft'])->actingAs($owner)->test(EntryEditor::class, ['type' => 'post'])
        ->call('publish')
        ->assertSee('Some cards are missing information')->assertSee('Role');

    expect(Post::find('posts/draft')->isDraft())->toBeTrue();

    $editor->call('publish', true)->assertSet('cardWarnings', []);

    expect(Post::find('posts/draft')->isPublished())->toBeTrue();
});

it('tells assistants about incomplete cards when they publish', function () {
    $owner = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);
    $this->actingAs($owner);
    app(Writer::class)->update('posts/draft', 'en', body: "::: member Jane Doe\n:::");

    WakeServer::tool(ContentPublishTool::class, ['id' => 'posts/draft'])->assertOk()->assertSee('card_warnings')->assertSee('Role');
});

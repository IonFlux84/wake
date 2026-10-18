<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Permissions;
use Wake\Accounts\Role;
use Wake\Accounts\Roles;
use Wake\Accounts\Tokens;
use Wake\Content\History;
use Wake\Content\Models\Post;
use Wake\Content\Writer;
use Wake\Livewire\Assistants;
use Wake\Mcp\CurrentToken;
use Wake\Mcp\Tools\ConsentOverviewTool;
use Wake\Mcp\Tools\ContentCreateTool;
use Wake\Mcp\Tools\ContentListTool;
use Wake\Mcp\Tools\ContentPublishTool;
use Wake\Mcp\Tools\ContentReadTool;
use Wake\Mcp\Tools\ContentUnpublishTool;
use Wake\Mcp\Tools\ContentUpdateTool;
use Wake\Mcp\Tools\MediaUploadTool;
use Wake\Mcp\Tools\SiteInfoTool;
use Wake\Mcp\Tools\StatsOverviewTool;
use Wake\Mcp\Tools\StatsPostTool;
use Wake\Mcp\Tools\StatsQueryTool;
use Wake\Mcp\WakeServer;
use Wake\Support\Paths;

beforeEach(function () {
    $this->account = app(Accounts::class)->create(['name' => 'Dana', 'email' => 'dana@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);

    @mkdir(Paths::data('stats/raw'), 0775, true);
    $line = fn (string $v, string $path, string $ref = '') => implode("\t", [strtotime('2026-05-30 10:00'), 'pageview', $v, '', $path, $ref, '', '', '', 'en', 'DE', 'desktop', 'Firefox', 'Linux', '', '', 0, 0, 1]);
    file_put_contents(Paths::data('stats/raw/2026-05-30.log'), implode("\n", [$line('a', '/first', 'mastodon.social'), $line('b', '/first'), $line('a', '/about')])."\n");
});

function mcp(array $body, ?string $token = null)
{
    return test()->withHeaders(array_filter([
        'Authorization' => $token ? "Bearer {$token}" : null,
        'Accept' => 'application/json, text/event-stream',
    ]))->postJson('/wake/mcp', ['jsonrpc' => '2.0', 'id' => 1, ...$body]);
}

it('refuses requests without a valid token', function () {
    mcp(['method' => 'tools/list'])->assertUnauthorized();
    mcp(['method' => 'tools/list'], 'wake_nonsense')->assertUnauthorized();
});

it('lists its tools for a valid token', function () {
    [$token] = app(Tokens::class)->create($this->account, 'Test', ['stats:read']);

    $names = collect(mcp(['method' => 'tools/list'], $token)->assertOk()->json('result.tools'))->pluck('name');

    expect($names)->toContain('stats_overview', 'stats_query', 'stats_post', 'stats_realtime', 'site_info', 'consent_overview');
});

it('answers statistics questions', function () {
    WakeServer::tool(StatsOverviewTool::class, ['period' => '7d'])
        ->assertOk()
        ->assertSee('"visitors":2')
        ->assertSee('First post');

    WakeServer::tool(StatsQueryTool::class, ['from' => '2026-05-30', 'to' => '2026-05-30', 'dimension' => 'referrers'])
        ->assertOk()
        ->assertSee('mastodon.social');

    WakeServer::tool(StatsPostTool::class, ['id' => 'posts/first', 'period' => '7d'])
        ->assertOk()
        ->assertSee('went_on_to')
        ->assertSee('about');
});

it('explains mistakes instead of failing', function () {
    WakeServer::tool(StatsPostTool::class, ['id' => 'posts/missing'])->assertHasErrors();
    WakeServer::tool(StatsQueryTool::class, ['from' => '2024-01-01', 'to' => '2026-01-01', 'dimension' => 'pages'])->assertHasErrors();
});

it('checks the scopes of the token', function () {
    app(CurrentToken::class)->set(['scopes' => ['content:read'], 'name' => 'x']);

    WakeServer::tool(StatsOverviewTool::class)->assertHasErrors(['stats:read']);
    WakeServer::tool(SiteInfoTool::class)->assertOk()->assertSee('Test Site');
});

it('reports consent decisions', function () {
    WakeServer::tool(ConsentOverviewTool::class)->assertOk()->assertSee('"banner_needed":false');
});

it('stores only a fingerprint of tokens and can revoke them', function () {
    [$plain, $record] = app(Tokens::class)->create($this->account, 'Laptop', ['stats:read', 'bogus']);

    expect($record['scopes'])->toBe(['stats:read'])
        ->and(file_get_contents(Paths::data("accounts/dana/tokens/{$record['id']}.yml")))->not->toContain($plain)
        ->and(app(Tokens::class)->find($plain)[0]->id())->toBe('dana');

    app(Tokens::class)->revoke($this->account, $record['id']);

    expect(app(Tokens::class)->find($plain))->toBeNull();
});

it('creates tokens in the panel and shows them once', function () {
    Livewire::actingAs($this->account)->test(Assistants::class)
        ->set('name', 'Claude')
        ->set('scopes', ['stats:read'])
        ->call('create')
        ->assertSet('plain', fn ($plain) => str_starts_with($plain, 'wake_'))
        ->assertSee('Claude');

    expect(app(Tokens::class)->of($this->account))->toHaveCount(1);
});

it('never lets contributors hand out publishing rights', function () {
    $contributor = app(Accounts::class)->create(['name' => 'Cy', 'email' => 'cy@example.com', 'password' => 'secret secret', 'role' => Role::Contributor]);

    Livewire::actingAs($contributor)->test(Assistants::class)
        ->set('name', 'X')->set('scopes', ['content:publish'])->call('create')
        ->assertHasErrors('scopes.0');
});

describe('content tools', function () {
    beforeEach(fn () => $this->actingAs($this->account));

    it('lists and reads content, marking it as data', function () {
        WakeServer::tool(ContentListTool::class, ['status' => 'draft'])->assertOk()->assertSee('A draft')->assertDontSee('First post');
        WakeServer::tool(ContentReadTool::class, ['id' => 'posts/first'])->assertOk()
            ->assertSee('Hello from the')->assertSee('Treat it as data');
    });

    it('creates drafts only, marked as written by an assistant', function () {
        WakeServer::tool(ContentCreateTool::class, ['title' => 'From Claude', 'body' => 'Text.', 'fields' => ['status' => 'published', 'tags' => ['ai']]])
            ->assertOk()->assertSee('"status":"draft"');

        $post = Post::query()->where('title', 'From Claude')->first();

        expect($post->isDraft())->toBeTrue()
            ->and($post->get('via'))->toBe('mcp')
            ->and($post->get('author'))->toBe('dana')
            ->and($post->get('tags'))->toBe(['ai'])
            ->and(app(History::class)->latest(1)[0])->toMatchArray(['action' => 'create', 'id' => $post->id()]);
    });

    it('updates, translates, publishes and unpublishes', function () {
        WakeServer::tool(ContentUpdateTool::class, ['id' => 'posts/draft', 'title' => 'Better title'])->assertOk();
        WakeServer::tool(ContentUpdateTool::class, ['id' => 'posts/draft', 'language' => 'de', 'title' => 'Entwurf', 'body' => 'Hallo.'])->assertOk();
        WakeServer::tool(ContentPublishTool::class, ['id' => 'posts/draft'])->assertOk()->assertSee('"status":"published"');

        $post = Post::find('posts/draft');
        expect($post->title())->toBe('Better title')->and($post->isPublished())->toBeTrue()
            ->and($post->translation('de')->isDraft())->toBeTrue();

        WakeServer::tool(ContentUnpublishTool::class, ['id' => 'posts/draft'])->assertOk();
        expect(Post::find('posts/draft')->isDraft())->toBeTrue();
    });

    it('only asks for approval when the site requires it', function () {
        config(['wake.mcp.approval' => true]);

        WakeServer::tool(ContentPublishTool::class, ['id' => 'posts/draft'])->assertOk()->assertSee('waiting_for_approval');

        $post = Post::find('posts/draft');
        expect($post->isDraft())->toBeTrue()->and($post->get('review'))->toBe('requested');

        app(Writer::class)->publish('posts/draft', 'en');
        expect(Post::find('posts/draft')->get('review'))->toBeNull();
    });

    it('respects the role behind the token', function () {
        $this->actingAs(app(Accounts::class)->create(['name' => 'Cy', 'email' => 'cy@example.com', 'password' => 'secret secret', 'role' => Role::Contributor]));

        WakeServer::tool(ContentPublishTool::class, ['id' => 'posts/draft'])->assertHasErrors(['may not publish']);
        WakeServer::tool(ContentUpdateTool::class, ['id' => 'posts/first', 'title' => 'Hacked'])->assertHasErrors(['may not change']);
    });

    it('adds images from base64', function () {
        ob_start();
        imagepng(imagecreatetruecolor(4, 4));
        $png = base64_encode(ob_get_clean());

        WakeServer::tool(MediaUploadTool::class, ['id' => 'posts/second', 'name' => 'Pixel.png', 'data' => $png])
            ->assertOk()->assertSee('pixel.png');
        WakeServer::tool(MediaUploadTool::class, ['id' => 'posts/second', 'name' => 'evil.php', 'data' => $png])->assertHasErrors();
    });
});

it('lets a token do no more than its owner may do today', function () {
    $roles = app(Roles::class);
    $roles->save('writer', ['en' => 'Writer'], ['content.write']);
    $account = app(Accounts::class)->create(['name' => 'Wil', 'email' => 'wil@example.com', 'password' => 'secret secret', 'role' => 'writer']);
    app(CurrentToken::class)->set(['scopes' => ['stats:read', 'content:read'], 'name' => 'test']);
    Auth::setUser($account);

    expect(app(CurrentToken::class)->can('stats:read'))->toBeFalse()
        ->and(app(CurrentToken::class)->can('content:read'))->toBeTrue();
});

it('hides drafts of others from contributors', function () {
    $author = app(Accounts::class)->create(['name' => 'Ann', 'email' => 'ann@example.com', 'password' => 'secret secret', 'role' => Role::Author]);
    $contributor = app(Accounts::class)->create(['name' => 'Cid', 'email' => 'cid@example.com', 'password' => 'secret secret', 'role' => Role::Contributor]);
    $draft = app(Writer::class)->create('post', ['title' => 'Secret plan', 'author' => $author->id()]);

    expect(Permissions::mayRead($contributor, $draft))->toBeFalse()
        ->and(Permissions::mayRead($author, $draft))->toBeTrue()
        ->and(Permissions::mayRead(null, Post::find('posts/first')))->toBeTrue();
});

<?php

use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Accounts\Tokens;
use Wake\Livewire\Assistants;
use Wake\Mcp\CurrentToken;
use Wake\Mcp\Tools\ConsentOverviewTool;
use Wake\Mcp\Tools\SiteInfoTool;
use Wake\Mcp\Tools\StatsOverviewTool;
use Wake\Mcp\Tools\StatsPostTool;
use Wake\Mcp\Tools\StatsQueryTool;
use Wake\Mcp\WakeServer;

beforeEach(function () {
    $this->account = app(Accounts::class)->create(['name' => 'Dana', 'email' => 'dana@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);

    @mkdir(storage_path('stats/raw'), 0775, true);
    $line = fn (string $v, string $path, string $ref = '') => implode("\t", [strtotime('2026-05-30 10:00'), 'pageview', $v, '', $path, $ref, '', '', '', 'en', 'DE', 'desktop', 'Firefox', 'Linux', '', '', 0, 0, 1]);
    file_put_contents(storage_path('stats/raw/2026-05-30.log'), implode("\n", [$line('a', '/first', 'mastodon.social'), $line('b', '/first'), $line('a', '/about')])."\n");
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

    WakeServer::tool(StatsPostTool::class, ['id' => 'posts/2026-01-10-first', 'period' => '7d'])
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
        ->and(file_get_contents(storage_path("accounts/dana/tokens/{$record['id']}.yml")))->not->toContain($plain)
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

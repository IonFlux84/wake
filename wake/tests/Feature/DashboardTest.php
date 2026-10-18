<?php

use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Livewire\Dashboard;
use Wake\Stats\Insights;
use Wake\Stats\Stats;

beforeEach(function () {
    $this->account = app(Accounts::class)->create(['name' => 'Dana Owner', 'email' => 'dana@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);

    @mkdir(storage_path('stats/raw'), 0775, true);
    $line = fn (string $visitor, string $path, string $referrer = '') => implode("\t", [strtotime('2026-05-30 10:00'), 'pageview', $visitor, '', $path, $referrer, '', '', '', 'en', 'DE', 'desktop', 'Firefox', 'Linux', '', '', 0, 0, 1]);
    file_put_contents(storage_path('stats/raw/2026-05-30.log'), implode("\n", [$line('a', '/first', 'mastodon.social'), $line('b', '/first'), $line('c', '/about')])."\n");
});

it('requires signing in', function () {
    $this->get('/wake')->assertRedirect('/wake/login');
});

it('shows key figures, most read posts and sources', function () {
    $this->actingAs($this->account)->get('/wake')
        ->assertOk()
        ->assertSee('Visitors')
        ->assertSee('First post')
        ->assertSee('mastodon.social')
        ->assertSee('A draft');
});

it('switches the period without reloading', function () {
    Livewire::actingAs($this->account)->test(Dashboard::class)
        ->assertSet('period', '7d')
        ->call('setPeriod', '30d')
        ->assertSet('period', '30d')
        ->call('setPeriod', 'nonsense')
        ->assertSet('period', '7d');
});

it('calculates changes in percent', function () {
    expect(Insights::delta(100, 124))->toBe(24)
        ->and(Insights::delta(0, 5))->toBeNull()
        ->and(Insights::delta(10, 9))->toBe(-10);
});

it('shows the statistics page', function () {
    $this->actingAs($this->account)->get('/wake/stats?period=12m')
        ->assertOk()
        ->assertSee('First post')
        ->assertSee('mastodon.social')
        ->assertSee('stats/journey?path=%2Ffirst', false);
});

it('shows where readers of a page came from and went next', function () {
    file_put_contents(storage_path('stats/raw/2026-05-30.log'), implode("\t", [strtotime('2026-05-30 10:05'), 'pageview', 'a', '', '/about', '', '', '', '', 'en', 'DE', 'desktop', 'Firefox', 'Linux', '', '', 0, 0, 1])."\n", FILE_APPEND);

    $this->actingAs($this->account)->get('/wake/stats/journey?path=/first&period=12m')
        ->assertOk()
        ->assertSee('First post')
        ->assertSee('mastodon.social')
        ->assertSee('About');
});

it('counts AI crawlers', function () {
    @mkdir(storage_path('stats/ai'), 0775, true);
    file_put_contents(storage_path('stats/ai/2026-05-30.log'), "1\tGPTBot\t/first\n1\tClaudeBot\t/first\n1\tGPTBot\t/\n");

    $ai = app(Stats::class)->aiCrawlers('2026-05-01', '2026-06-01');

    expect($ai)->toBe(['total' => 3, 'bots' => ['GPTBot' => 2, 'ClaudeBot' => 1], 'pages' => ['/first' => 2, '/' => 1]]);
});

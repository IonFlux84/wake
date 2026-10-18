<?php

use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Livewire\Dashboard;
use Wake\Stats\Insights;

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

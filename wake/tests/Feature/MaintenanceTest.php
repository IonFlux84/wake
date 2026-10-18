<?php

use Wake\Content\Index;
use Wake\Maintenance\Maintenance;

it('writes "published" into due scheduled posts', function () {
    app(Maintenance::class)->run();

    expect(file_get_contents($this->content('posts/due/content.md')))->toContain('status: published')
        ->and(file_get_contents($this->content('posts/future/content.md')))->toContain('status: scheduled')
        ->and(app(Index::class)->find('posts/due')['languages']['en']['fields']['status'])->toBe('published');
});

it('runs at most once per interval', function () {
    $maintenance = app(Maintenance::class);

    expect($maintenance->runIfDue())->toBeTrue()
        ->and($maintenance->runIfDue())->toBeFalse();
});

it('runs after website requests', function () {
    config(['wake.maintenance.mode' => 'requests']);
    $this->get('/first');
    app()->terminate();

    expect(app(Maintenance::class)->lastRun())->toBeGreaterThan(0);
});

it('leaves maintenance to cron when configured', function () {
    config(['wake.maintenance.mode' => 'cron']);

    $this->get('/first');
    app()->terminate();

    expect(app(Maintenance::class)->lastRun())->toBe(0);
});

it('has Artisan commands', function () {
    $this->artisan('wake:index')->assertSuccessful();
    $this->artisan('wake:clear')->expectsOutputToContain('Page cache and compiled templates cleared.')->assertSuccessful();
    $this->artisan('wake:maintenance')->assertSuccessful();
});

<?php

use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Accounts\Tokens;

beforeEach(function () {
    $this->account = app(Accounts::class)->create(['name' => 'Dana', 'email' => 'dana@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);
});

it('describes the site', function () {
    $this->getJson('/wake/api/v1/site')->assertOk()->assertJsonPath('data.title', 'Test Site')->assertJsonPath('data.default_language', 'en');
});

it('lists published entries publicly, with CORS', function () {
    $response = $this->getJson('/wake/api/v1/entries?limit=2')->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('data.0.title', 'Second post');

    expect($response->json('data.0'))->not->toHaveKey('markdown');

    $this->getJson('/wake/api/v1/entries?language=de&tag=news&body=1')->assertOk()
        ->assertJsonPath('data.0.title', 'Erster Beitrag')
        ->assertJsonPath('data.0.markdown', "Hallo aus dem ersten Beitrag.\n");
});

it('reads one entry with body and translations', function () {
    $this->getJson('/wake/api/v1/entry?id=posts/first')->assertOk()
        ->assertJsonPath('data.translations.de', url('/de/erster-beitrag'))
        ->assertJsonPath('data.cover.width', 800);

    $this->getJson('/wake/api/v1/entry?id=posts/draft')->assertNotFound();
});

it('needs a token for drafts and statistics', function () {
    $this->getJson('/wake/api/v1/entries?status=draft')->assertForbidden();
    $this->getJson('/wake/api/v1/stats')->assertForbidden();
    $this->withToken('wake_wrong')->getJson('/wake/api/v1/site')->assertUnauthorized();

    [$token] = app(Tokens::class)->create($this->account, 'API', ['content:read', 'stats:read']);

    $this->withToken($token)->getJson('/wake/api/v1/entries?status=draft')->assertOk()->assertJsonPath('data.0.title', 'A draft');
    $this->withToken($token)->getJson('/wake/api/v1/stats?period=7d')->assertOk()->assertJsonStructure(['data' => ['visitors', 'pageviews', 'series']]);
});

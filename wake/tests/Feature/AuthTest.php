<?php

use Wake\Accounts\Account;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;

function owner(array $attributes = []): Account
{
    return app(Accounts::class)->create([
        'name' => 'Dana Owner', 'email' => 'dana@example.com', 'password' => 'correct horse', 'role' => Role::Owner, ...$attributes,
    ]);
}

it('sends the first visitor to the setup', function () {
    $this->get('/wake')->assertRedirect('/wake/login');
    $this->get('/wake/login')->assertRedirect('/wake/setup');
    $this->get('/wake/setup')->assertOk()->assertSee('Set up Wake');
});

it('creates the owner during setup and signs them in', function () {
    $this->post('/wake/setup', [
        'name' => 'Dana Owner', 'email' => 'Dana@Example.com', 'password' => 'a long password', 'language' => 'de',
    ])->assertRedirect('/wake');

    $account = app(Accounts::class)->findByEmail('dana@example.com');

    expect($account->role())->toBe(Role::Owner)
        ->and($account->id())->toBe('dana-owner')
        ->and($account->language())->toBe('de')
        ->and(file_get_contents(storage_path('accounts/dana-owner/account.yml')))->not->toContain('a long password');

    $this->assertAuthenticatedAs($account);
});

it('closes the setup once an account exists', function () {
    owner();

    $this->get('/wake/setup')->assertRedirect('/wake/login');
    $this->post('/wake/setup', ['name' => 'Eve', 'email' => 'eve@example.com', 'password' => 'another password', 'language' => 'en']);

    expect(app(Accounts::class)->all())->toHaveCount(1);
});

it('validates the setup form', function () {
    $this->post('/wake/setup', ['name' => '', 'email' => 'nope', 'password' => 'short', 'language' => 'fr'])
        ->assertSessionHasErrors(['name', 'email', 'password', 'language']);
});

it('signs in with email and password', function () {
    $account = owner();

    $this->post('/wake/login', ['email' => 'DANA@example.com', 'password' => 'correct horse'])->assertRedirect('/wake');
    $this->assertAuthenticatedAs($account);
});

it('rejects wrong passwords and slows down guessing', function () {
    owner();

    foreach (range(1, 5) as $i) {
        $this->post('/wake/login', ['email' => 'dana@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
    }

    $this->post('/wake/login', ['email' => 'dana@example.com', 'password' => 'correct horse'])
        ->assertSessionHasErrors(['email' => __('Too many attempts. Please try again in :seconds seconds.', ['seconds' => 60])]);
    $this->assertGuest();
});

it('remembers people who want to stay signed in', function () {
    owner();

    $this->post('/wake/login', ['email' => 'dana@example.com', 'password' => 'correct horse', 'remember' => '1']);

    expect(app(Accounts::class)->find('dana-owner')->getRememberToken())->not->toBeEmpty();
});

it('signs out', function () {
    $this->actingAs(owner())->post('/wake/logout')->assertRedirect('/wake/login');
    $this->assertGuest();
});

it('shows the panel in the language of the account', function () {
    $this->actingAs(owner(['language' => 'de']))->get('/wake')->assertOk()->assertSee('Guten Morgen, Dana')->assertSee('Eigentümer');
});

it('keeps the website free of sessions and cookies', function () {
    $this->get('/first')->assertCookieMissing('wake_session')->assertHeaderMissing('Set-Cookie');
});

it('ranks roles', function () {
    expect(Role::Editor->atLeast(Role::Author))->toBeTrue()
        ->and(Role::Contributor->atLeast(Role::Author))->toBeFalse();
});

it('gives accounts unique ids and refuses duplicate emails', function () {
    owner();
    $second = app(Accounts::class)->create(['name' => 'Dana Owner', 'email' => 'other@example.com', 'password' => 'whatever123']);

    expect($second->id())->toBe('dana-owner-2');

    owner();
})->throws(InvalidArgumentException::class);

<?php

use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Capabilities;
use Wake\Accounts\Role;
use Wake\Accounts\Roles;
use Wake\Livewire\RoleManager;
use Wake\Livewire\Team;

beforeEach(function () {
    $this->owner = app(Accounts::class)->create(['name' => 'Dana', 'email' => 'dana@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);
});

it('adjusts what a built-in role may do', function () {
    $author = app(Accounts::class)->create(['name' => 'Al', 'email' => 'al@example.com', 'password' => 'secret secret', 'role' => Role::Author]);

    $this->actingAs($author)->get('/wake/stats')->assertOk();

    Livewire::actingAs($this->owner)->test(RoleManager::class)
        ->set('matrix.author', ['content.write', 'content.publish'])
        ->call('save');

    expect(app(Roles::class)->get(Role::Author)->can('stats.view'))->toBeFalse();
    $this->actingAs(app(Accounts::class)->find('al'))->get('/wake/stats')->assertForbidden();
});

it('adds custom roles, assigns them and protects them while in use', function () {
    Livewire::actingAs($this->owner)->test(RoleManager::class)->set('newRole', 'Moderator')->call('addRole');

    expect(app(Roles::class)->get('moderator')->label('en'))->toBe('Moderator');

    Livewire::actingAs($this->owner)->test(RoleManager::class)
        ->set('matrix.moderator', ['content.write', 'content.edit_all'])->call('save');

    Mail::fake();
    Livewire::actingAs($this->owner)->test(Team::class)
        ->set('name', 'Mo')->set('email', 'mo@example.com')->set('role', 'moderator')->call('invite')->assertHasNoErrors();

    $mo = app(Accounts::class)->findByEmail('mo@example.com');
    expect($mo->can('content.edit_all'))->toBeTrue()->and($mo->can('content.publish'))->toBeFalse();

    Livewire::actingAs($this->owner)->test(RoleManager::class)->call('deleteRole', 'moderator')->assertHasErrors('matrix');
    expect(app(Roles::class)->get('moderator'))->not->toBeNull();
});

it('resets built-in roles and never changes the owner', function () {
    app(Roles::class)->save(Role::Editor, [], ['content.write']);
    Livewire::actingAs($this->owner)->test(RoleManager::class)->call('deleteRole', 'editor');

    expect(app(Roles::class)->get(Role::Editor)->can('content.edit_all'))->toBeTrue()
        ->and(fn () => app(Roles::class)->save(Role::Owner, [], []))->toThrow(InvalidArgumentException::class)
        ->and(app(Roles::class)->get(Role::Owner)->capabilities())->toBe(array_keys(Capabilities::all()));
});

it('keeps people from locking themselves out', function () {
    $admin = app(Accounts::class)->create(['name' => 'Ad', 'email' => 'ad@example.com', 'password' => 'secret secret', 'role' => Role::Admin]);

    Livewire::actingAs($admin)->test(RoleManager::class)->set('matrix.admin', ['content.write'])->call('save');

    expect(app(Roles::class)->get(Role::Admin)->can('team.manage'))->toBeTrue();
});

it('hides menu entries without the capability', function () {
    $contributor = app(Accounts::class)->create(['name' => 'Cy', 'email' => 'cy@example.com', 'password' => 'secret secret', 'role' => Role::Contributor]);

    $this->actingAs($contributor)->get('/wake')->assertOk()->assertDontSee('/wake/team')->assertDontSee('/wake/consent');
});

it('documents every capability', function () {
    $reference = file_get_contents(dirname(base_path()).'/docs/reference/roles.md');

    expect(array_values(array_filter(array_keys(Capabilities::all()), fn ($c) => ! str_contains($reference, "`{$c}`"))))->toBe([]);
});

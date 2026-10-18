<?php

use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Invitations;
use Wake\Accounts\Role;
use Wake\Livewire\Team;
use Wake\Mail\InvitationMail;
use Wake\Mail\PasswordResetMail;

beforeEach(function () {
    $this->owner = app(Accounts::class)->create(['name' => 'Dana', 'email' => 'dana@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);
});

it('invites someone, who chooses a password and joins', function () {
    Mail::fake();

    Livewire::actingAs($this->owner)->test(Team::class)
        ->set('name', 'Eve Editor')->set('email', 'eve@example.com')->set('role', 'editor')
        ->call('invite')->assertHasNoErrors();

    $eve = app(Accounts::class)->findByEmail('eve@example.com');
    $token = null;
    Mail::assertSent(InvitationMail::class, function ($mail) use (&$token) {
        $token = $mail->token;

        return $mail->hasTo('eve@example.com');
    });

    expect($eve->isPending())->toBeTrue()->and($eve->role())->toBe(Role::Editor);

    auth()->logout();
    $this->get("/wake/invitation/eve-editor/{$token}")->assertOk()->assertSee('Eve Editor');
    $this->post("/wake/invitation/eve-editor/{$token}", ['password' => 'a fine password', 'password_confirmation' => 'a fine password'])
        ->assertRedirect('/wake');

    expect(app(Accounts::class)->find('eve-editor')->isPending())->toBeFalse();
    $this->get("/wake/invitation/eve-editor/{$token}")->assertStatus(302); // signed in now, guests only
});

it('rejects wrong or expired invitation links', function () {
    Mail::fake();
    $eve = app(Invitations::class)->invite('Eve', 'eve@example.com', Role::Author, $this->owner);

    $this->get('/wake/invitation/eve/wrong-token')->assertStatus(410);

    app(Accounts::class)->save($eve->with(['invite_expires' => now()->subDay()->toIso8601String()]));
    expect(app(Invitations::class)->valid(app(Accounts::class)->find('eve'), 'x'))->toBeFalse();
});

it('does not let invited people sign in before they chose a password', function () {
    Mail::fake();
    app(Invitations::class)->invite('Eve', 'eve@example.com', Role::Author, $this->owner);

    $this->post('/wake/login', ['email' => 'eve@example.com', 'password' => ''])->assertSessionHasErrors();
    $this->assertGuest();
});

it('changes roles and removes members, but never the owner or yourself', function () {
    Mail::fake();
    app(Invitations::class)->invite('Al', 'al@example.com', Role::Author, $this->owner);

    Livewire::actingAs($this->owner)->test(Team::class)->call('changeRole', 'al', 'editor');
    expect(app(Accounts::class)->find('al')->role())->toBe(Role::Editor);

    Livewire::actingAs($this->owner)->test(Team::class)->call('changeRole', 'dana', 'author')->assertForbidden();
    Livewire::actingAs($this->owner)->test(Team::class)->call('changeRole', 'al', 'owner')->assertForbidden();

    Livewire::actingAs($this->owner)->test(Team::class)->call('remove', 'al');
    expect(app(Accounts::class)->find('al'))->toBeNull();
});

it('is only for administrators', function () {
    $editor = app(Accounts::class)->create(['name' => 'Ed', 'email' => 'ed@example.com', 'password' => 'secret secret', 'role' => Role::Editor]);

    $this->actingAs($editor)->get('/wake/team')->assertForbidden();
});

it('resets forgotten passwords by mail', function () {
    Mail::fake();

    $this->post('/wake/password', ['email' => 'dana@example.com'])->assertSessionHas('status');
    $this->post('/wake/password', ['email' => 'nobody@example.com'])->assertSessionHas('status');

    $token = null;
    Mail::assertSent(PasswordResetMail::class, function ($mail) use (&$token) {
        $token = $mail->token;

        return true;
    });
    Mail::assertSentCount(1);

    $this->post('/wake/password/reset', ['token' => $token, 'email' => 'dana@example.com', 'password' => 'brand new pass', 'password_confirmation' => 'brand new pass'])
        ->assertRedirect('/wake/login');

    $this->post('/wake/login', ['email' => 'dana@example.com', 'password' => 'brand new pass'])->assertRedirect('/wake');
});

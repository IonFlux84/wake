<?php

use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Accounts\TwoFactor;
use Wake\Livewire\Team;
use Wake\Livewire\TwoFactorSettings;

function withTwoFactor(string $role = Role::Owner, string $email = 'ola@example.com'): array
{
    $account = app(Accounts::class)->create(['name' => 'Ola', 'email' => $email, 'password' => 'secret secret', 'role' => $role]);
    $secret = app(TwoFactor::class)->newSecret();
    $codes = app(TwoFactor::class)->enable($account, $secret);

    return [app(Accounts::class)->find($account->id()), $secret, $codes];
}

it('computes the codes of RFC 6238', function () {
    $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // "12345678901234567890"

    expect(TwoFactor::code($secret, 59))->toBe('287082')
        ->and(TwoFactor::code($secret, 1111111109))->toBe('081804')
        ->and(TwoFactor::verifyCode($secret, '287 082', 59 + 30))->not->toBeNull()
        ->and(TwoFactor::verifyCode($secret, '287082', 59 + 90))->toBeNull();
});

it('asks for a code after the password', function () {
    [$account, $secret] = withTwoFactor();

    $this->post('/wake/login', ['email' => 'ola@example.com', 'password' => 'secret secret'])->assertRedirect('/wake/login/code');
    $this->assertGuest();

    $this->post('/wake/login/code', ['code' => '000000'])->assertSessionHasErrors('code');
    $this->assertGuest();

    $this->post('/wake/login/code', ['code' => TwoFactor::code($secret)])->assertRedirect('/wake');
    $this->assertAuthenticatedAs(app(Accounts::class)->find($account->id()));
});

it('accepts each code and each recovery code only once', function () {
    [$account, $secret, $codes] = withTwoFactor();
    $twoFactor = app(TwoFactor::class);
    $code = TwoFactor::code($secret);

    expect($twoFactor->verify($account, $code))->toBeTrue()
        ->and($twoFactor->verify(app(Accounts::class)->find($account->id()), $code))->toBeFalse()
        ->and($twoFactor->verify(app(Accounts::class)->find($account->id()), strtoupper($codes[0])))->toBeTrue()
        ->and($twoFactor->verify(app(Accounts::class)->find($account->id()), $codes[0]))->toBeFalse()
        ->and($twoFactor->remainingRecoveryCodes(app(Accounts::class)->find($account->id())))->toBe(7);
});

it('stores the secret encrypted and recovery codes hashed', function () {
    [$account, $secret, $codes] = withTwoFactor();
    $file = file_get_contents(app(Accounts::class)->folder($account->id()).'/account.yml');

    expect($file)->not->toContain($secret)->not->toContain($codes[0]);
});

it('does not let the code step be skipped', function () {
    withTwoFactor();

    $this->post('/wake/login', ['email' => 'ola@example.com', 'password' => 'secret secret']);
    $this->get('/wake')->assertRedirect('/wake/login');
    $this->get('/wake/login/code')->assertOk();

    $this->travel(6)->minutes();
    $this->get('/wake/login/code')->assertRedirect('/wake/login');
});

it('limits attempts at the code', function () {
    withTwoFactor();
    $this->post('/wake/login', ['email' => 'ola@example.com', 'password' => 'secret secret']);

    foreach (range(1, 5) as $i) {
        $this->post('/wake/login/code', ['code' => '000000']);
    }

    $this->post('/wake/login/code', ['code' => '000000'])->assertSessionHasErrors(['code' => __('Too many attempts. Please try again in :seconds seconds.', ['seconds' => 60])]);
});

it('sets up two-factor sign-in in the settings', function () {
    $account = app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);

    $component = Livewire::actingAs($account)->test(TwoFactorSettings::class)->call('start')->assertSee('svg');
    $secret = $component->get('pendingSecret');

    $component->set('code', '123456')->call('confirm')->assertHasErrors('code')
        ->set('code', TwoFactor::code($secret))->call('confirm')->assertHasNoErrors();

    expect($component->get('recoveryCodes'))->toHaveCount(8)
        ->and(TwoFactor::isEnabled(app(Accounts::class)->find($account->id())))->toBeTrue();

    Livewire::actingAs(app(Accounts::class)->find($account->id()))->test(TwoFactorSettings::class)
        ->set('password', 'wrong')->call('disable')->assertHasErrors('password')
        ->set('password', 'secret secret')->call('disable');

    expect(TwoFactor::isEnabled(app(Accounts::class)->find($account->id())))->toBeFalse();
});

it('lets team managers turn it off for someone who lost their phone', function () {
    [$member] = withTwoFactor(Role::Editor, 'eda@example.com');
    $owner = app(Accounts::class)->create(['name' => 'Owen', 'email' => 'owen@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);

    Livewire::actingAs($owner)->test(Team::class)->call('resetTwoFactor', $member->id());

    expect(TwoFactor::isEnabled(app(Accounts::class)->find($member->id())))->toBeFalse();
});

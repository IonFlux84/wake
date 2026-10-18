<?php

use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Livewire\MailSettingsForm;
use Wake\Livewire\Team;
use Wake\Mail\InvitationMail;
use Wake\Mail\MailSettings;

function mailOwner()
{
    return app(Accounts::class)->create(['name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'secret secret', 'role' => Role::Owner]);
}

it('saves mail settings with an encrypted password and applies them', function () {
    $owner = mailOwner();
    Livewire::actingAs($owner)->test(MailSettingsForm::class)
        ->set('mailer', 'smtp')->set('host', 'smtp.example.org')->set('port', 465)->set('encryption', 'ssl')
        ->set('username', 'wake')->set('password', 'very secret')->set('fromAddress', 'hallo@example.org')->set('fromName', 'Verein')
        ->call('save')->assertHasNoErrors();

    expect(file_get_contents(app(MailSettings::class)->file()))->not->toContain('very secret')
        ->and(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.scheme'))->toBe('smtps')
        ->and(config('mail.mailers.smtp.password'))->toBe('very secret')
        ->and(config('mail.from.address'))->toBe('hallo@example.org');

    // An empty password keeps the stored one.
    Livewire::actingAs($owner)->test(MailSettingsForm::class)->set('host', 'mail.example.org')->call('save');
    expect(config('mail.mailers.smtp.password'))->toBe('very secret')->and(config('mail.mailers.smtp.host'))->toBe('mail.example.org');
});

it('sends a test mail', function () {
    Mail::fake();
    app(MailSettings::class)->save(['mailer' => 'smtp', 'host' => 'smtp.example.org', 'port' => 587, 'from_address' => 'hallo@example.org']);

    Livewire::actingAs(mailOwner())->test(MailSettingsForm::class)->call('sendTest')->assertSee('Test mail sent to ola@example.com');
});

it('says honestly when invitations are not mailed', function () {
    config(['mail.default' => 'log']);

    Livewire::actingAs(mailOwner())->test(Team::class)
        ->set('name', 'Bo')->set('email', 'bo@example.com')->set('role', Role::Editor)->call('invite')
        ->assertSee('Wake does not send mails yet')->assertSee('/wake/invitation/');
});

it('keeps the invitation when the mail server fails', function () {
    app(MailSettings::class)->save(['mailer' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'encryption' => 'none', 'from_address' => 'hallo@example.org']);

    Livewire::actingAs(mailOwner())->test(Team::class)
        ->set('name', 'Bo')->set('email', 'bo@example.com')->set('role', Role::Editor)->call('invite')
        ->assertSee('the mail could not be sent');

    expect(app(Accounts::class)->findByEmail('bo@example.com')?->isPending())->toBeTrue();
});

it('mails invitations when mail is set up', function () {
    Mail::fake();
    config(['mail.default' => 'smtp']);

    Livewire::actingAs(mailOwner())->test(Team::class)
        ->set('name', 'Bo')->set('email', 'bo@example.com')->set('role', Role::Editor)->call('invite')
        ->assertSee('Invitation sent to bo@example.com');

    Mail::assertSent(InvitationMail::class);
});

it('keeps mail settings away from editors', function () {
    $editor = app(Accounts::class)->create(['name' => 'Eda', 'email' => 'eda@example.com', 'password' => 'secret secret', 'role' => Role::Editor]);

    Livewire::actingAs($editor)->test(MailSettingsForm::class)->assertForbidden();
});

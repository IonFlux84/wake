<?php

namespace Wake\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Wake\Accounts\Account;
use Wake\Content\Site;

class PasswordResetMail extends Mailable
{
    public function __construct(public Account $account, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Choose a new password for :site', ['site' => app(Site::class)->title()]));
    }

    public function content(): Content
    {
        return new Content(text: 'wake::mail.password-reset', with: [
            'url' => route('wake.password.reset', ['token' => $this->token, 'email' => $this->account->email()]),
        ]);
    }
}

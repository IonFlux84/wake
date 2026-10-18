<?php

namespace Wake\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Wake\Accounts\Account;
use Wake\Content\Site;

class InvitationMail extends Mailable
{
    public function __construct(public Account $account, public Account $inviter, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __(':name invites you to :site', ['name' => $this->inviter->name(), 'site' => app(Site::class)->title()]));
    }

    public function content(): Content
    {
        return new Content(text: 'wake::mail.invitation', with: [
            'url' => route('wake.invitation', ['id' => $this->account->id(), 'token' => $this->token]),
            'role' => $this->account->role()->label(),
        ]);
    }
}

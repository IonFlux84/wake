<?php

namespace Wake\Accounts;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Wake\Mail\InvitationMail;

/**
 * Invitations to the team: an account without password plus a token that
 * is valid for seven days. Only a hash of the token is stored.
 */
class Invitations
{
    public const DAYS = 7;

    /** The link of the last invitation sent, to show it in the panel as well. */
    public ?string $lastLink = null;

    public function __construct(protected Accounts $accounts) {}

    public function invite(string $name, string $email, Role $role, Account $inviter): Account
    {
        $account = $this->accounts->create(['name' => $name, 'email' => $email, 'role' => $role, 'language' => $inviter->language()]);

        return $this->send($account, $inviter);
    }

    /**
     * Send a new invitation link, which replaces older ones.
     */
    public function send(Account $account, Account $inviter): Account
    {
        $token = Str::random(48);
        $account = $account->with([
            'invite_hash' => hash('sha256', $token),
            'invite_expires' => now()->addDays(self::DAYS)->toIso8601String(),
            'invited_by' => $inviter->id(),
        ]);

        $this->accounts->save($account);
        $this->lastLink = route('wake.invitation', ['id' => $account->id(), 'token' => $token]);
        Mail::to($account->email())->locale($account->language())->send(new InvitationMail($account, $inviter, $token));

        return $account;
    }

    public function valid(?Account $account, string $token): bool
    {
        return $account !== null
            && $account->isPending()
            && hash_equals((string) $account->get('invite_hash'), hash('sha256', $token))
            && now()->lt((string) $account->get('invite_expires'));
    }

    public function accept(Account $account, string $password): Account
    {
        $attributes = array_diff_key($account->toArray(), array_flip(['invite_hash', 'invite_expires']));
        $account = new Account([...$attributes, 'password' => Hash::make($password), 'joined_at' => now()->toIso8601String()]);
        $this->accounts->save($account);

        return $account;
    }
}

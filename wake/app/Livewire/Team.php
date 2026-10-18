<?php

namespace Wake\Livewire;

use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Wake\Accounts\Account;
use Wake\Accounts\Accounts;
use Wake\Accounts\Invitations;
use Wake\Accounts\Role;
use Wake\Accounts\Roles;
use Wake\Accounts\TwoFactor;
use Wake\Mail\MailSettings;

/**
 * The team: who has access, with which role, and invitations.
 */
class Team extends Component
{
    public string $name = '';

    public string $email = '';

    public string $role = 'author';

    public function mount(): void
    {
        abort_unless($this->me()->can('team.manage'), 403);
    }

    public function invite(Invitations $invitations, Accounts $accounts): void
    {
        $this->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:190', function ($attribute, $value, $fail) use ($accounts) {
                if ($accounts->findByEmail($value)) {
                    $fail(__('There is already an account with this address.'));
                }
            }],
            'role' => ['required', Rule::in($this->assignableRoles())],
        ]);

        $invitations->invite($this->name, $this->email, $this->role, $this->me());
        $this->report($invitations, $this->email);
        $this->reset('name', 'email');
    }

    /**
     * Says honestly whether the mail went out, and always offers the link.
     */
    protected function report(Invitations $invitations, string $email): void
    {
        session()->flash('status', match (true) {
            $invitations->lastError !== null => __('The invitation for :email is ready, but the mail could not be sent: :error', ['email' => $email, 'error' => $invitations->lastError]),
            ! MailSettings::sends() => __('The invitation for :email is ready. Wake does not send mails yet, so send the link below yourself, or set up mail under Settings.', ['email' => $email]),
            default => __('Invitation sent to :email.', ['email' => $email]),
        });
        session()->flash('invite_link', $invitations->lastLink);
    }

    public function resend(Invitations $invitations, Accounts $accounts, string $id): void
    {
        $account = $accounts->find($id);

        if ($account?->isPending()) {
            $invitations->send($account, $this->me());
            $this->report($invitations, $account->email());
        }
    }

    public function changeRole(Accounts $accounts, string $id, string $role): void
    {
        $account = $accounts->find($id);
        abort_unless($account && $this->mayManage($account) && in_array($role, $this->assignableRoles(), true), 403);

        $accounts->save($account->with(['role' => $role]));
    }

    /**
     * For someone who lost their phone and their recovery codes.
     */
    public function resetTwoFactor(Accounts $accounts, TwoFactor $twoFactor, string $id): void
    {
        $account = $accounts->find($id);
        abort_unless($account && $this->mayManage($account), 403);

        $twoFactor->disable($account);
        session()->flash('status', __('Two-factor sign-in is off for :name. They can sign in with their password and set it up again.', ['name' => $account->name()]));
    }

    public function remove(Accounts $accounts, string $id): void
    {
        $account = $accounts->find($id);
        abort_unless($account && $this->mayManage($account), 403);

        $accounts->trash($account);

        session()->flash('status', __(':name no longer has access.', ['name' => $account->name()]));
    }

    /**
     * @return list<string>
     */
    public function assignableRoles(): array
    {
        return array_values(array_filter(array_keys(app(Roles::class)->all()), fn (string $key) => $key !== Role::Owner));
    }

    protected function mayManage(Account $account): bool
    {
        return $account->id() !== $this->me()->id() && ! $account->role()->isOwner();
    }

    protected function me(): Account
    {
        return auth()->user();
    }

    public function render(Accounts $accounts): View
    {
        return view('wake::livewire.team', ['accounts' => $accounts->all(), 'roles' => $this->assignableRoles()]);
    }
}

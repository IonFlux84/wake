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
        session()->flash('status', __('Invitation sent to :email.', ['email' => $this->email]));
        session()->flash('invite_link', $invitations->lastLink);
        $this->reset('name', 'email');
    }

    public function resend(Invitations $invitations, Accounts $accounts, string $id): void
    {
        $account = $accounts->find($id);

        if ($account?->isPending()) {
            $invitations->send($account, $this->me());
            session()->flash('status', __('Invitation sent to :email.', ['email' => $account->email()]));
            session()->flash('invite_link', $invitations->lastLink);
        }
    }

    public function changeRole(Accounts $accounts, string $id, string $role): void
    {
        $account = $accounts->find($id);
        abort_unless($account && $this->mayManage($account) && in_array($role, $this->assignableRoles(), true), 403);

        $accounts->save($account->with(['role' => $role]));
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

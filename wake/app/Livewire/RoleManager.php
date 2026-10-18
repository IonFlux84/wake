<?php

namespace Wake\Livewire;

use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;
use Wake\Accounts\Accounts;
use Wake\Accounts\Capabilities;
use Wake\Accounts\Role;
use Wake\Accounts\Roles;

/**
 * Roles and what they may do, as a matrix. The owner always may do
 * everything; built-in roles can be adjusted and reset; custom roles can be
 * added and removed while nobody has them.
 */
class RoleManager extends Component
{
    /** @var array<string, list<string>> role => capabilities */
    public array $matrix = [];

    /** @var array<string, array{de: string, en: string}> */
    public array $labels = [];

    public string $newRole = '';

    public function mount(Roles $roles): void
    {
        abort_unless(auth()->user()->can('team.manage'), 403);
        $this->load($roles);
    }

    public function save(Roles $roles): void
    {
        abort_unless(auth()->user()->can('team.manage'), 403);

        foreach ($this->matrix as $key => $capabilities) {
            if ($key === Role::Owner) {
                continue;
            }

            $role = $roles->get($key);
            $label = $role->builtIn && $this->labels[$key] === ['de' => __('wake::roles.'.$key, locale: 'de'), 'en' => __('wake::roles.'.$key, locale: 'en')]
                ? []
                : $this->labels[$key];

            $roles->save($key, $label, $capabilities);
        }

        // Nobody may lock themselves out of the team page.
        $me = app(Accounts::class)->find(auth()->id());

        if (! $me->role()->isOwner() && ! $me->role()->can('team.manage')) {
            $key = $me->role()->key;
            $roles->save($key, $this->labels[$key], [...$this->matrix[$key], 'team.manage']);
            session()->flash('status', __('Saved. Your own role keeps “Manage the team and roles”, so you do not lock yourself out.'));
        } else {
            session()->flash('status', __('Roles saved.'));
        }

        $this->load($roles);
    }

    public function addRole(Roles $roles): void
    {
        abort_unless(auth()->user()->can('team.manage'), 403);
        $this->validate(['newRole' => 'required|string|max:40']);

        $key = Str::slug($this->newRole, '_');

        if ($key === '' || $roles->get($key)) {
            $this->addError('newRole', __('There is already a role with this name.'));

            return;
        }

        $roles->save($key, ['de' => $this->newRole, 'en' => $this->newRole], ['content.write']);
        $this->newRole = '';
        $this->load($roles);
    }

    public function deleteRole(Roles $roles, Accounts $accounts, string $key): void
    {
        abort_unless(auth()->user()->can('team.manage'), 403);
        $role = $roles->get($key);

        if ($role === null || $role->isOwner()) {
            return;
        }

        if (! $role->builtIn && $accounts->all()->contains(fn ($a) => $a->role()->key === $key)) {
            $this->addError('matrix', __('Somebody still has this role. Give them another role first.'));

            return;
        }

        $roles->delete($key);
        session()->flash('status', $role->builtIn ? __('The role is back to its defaults.') : __('Role removed.'));
        $this->load($roles);
    }

    protected function load(Roles $roles): void
    {
        $this->matrix = [];
        $this->labels = [];

        foreach ($roles->all() as $key => $role) {
            $this->matrix[$key] = $role->capabilities();
            $raw = $role->rawLabel();
            $this->labels[$key] = [
                'de' => is_array($raw) && isset($raw['de']) ? $raw['de'] : $role->label('de'),
                'en' => is_array($raw) && isset($raw['en']) ? $raw['en'] : $role->label('en'),
            ];
        }
    }

    public function render(Roles $roles, Accounts $accounts): View
    {
        return view('wake::livewire.role-manager', [
            'roles' => $roles->all(),
            'groups' => Capabilities::groups(),
            'capabilities' => Capabilities::all(),
            'members' => $accounts->all()->groupBy(fn ($a) => $a->role()->key)->map->count(),
        ]);
    }
}

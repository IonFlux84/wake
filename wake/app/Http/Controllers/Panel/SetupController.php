<?php

namespace Wake\Http\Controllers\Panel;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Wake\Accounts\Accounts;
use Wake\Accounts\Role;
use Wake\Support\Files;
use Wake\Support\Health;

/**
 * The first visit to /wake: create the owner account.
 */
class SetupController
{
    public function __construct(protected Accounts $accounts) {}

    public function show(Health $health): View|RedirectResponse
    {
        if ($this->accounts->exists()) {
            return redirect()->route('wake.login');
        }

        return view('wake::panel.auth.setup', ['warnings' => $health->problems()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', Password::min(10)],
            'language' => ['required', 'in:en,de'],
        ]);

        // Two people opening /wake at the same moment must not both become owner.
        $account = Files::lock('setup', function () use ($data) {
            if ($this->accounts->exists()) {
                return null;
            }

            return $this->accounts->create([...$data, 'role' => Role::Owner]);
        });

        if ($account === null) {
            return redirect()->route('wake.login');
        }

        Auth::login($account, remember: true);
        $request->session()->regenerate();

        return redirect()->route('wake.dashboard')->with('status', __('Welcome aboard, :name.', ['name' => $account->name()]));
    }
}

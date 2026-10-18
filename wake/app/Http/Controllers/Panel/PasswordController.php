<?php

namespace Wake\Http\Controllers\Panel;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Wake\Accounts\Accounts;
use Wake\Accounts\Invitations;

/**
 * Forgotten passwords and accepting invitations.
 */
class PasswordController
{
    public function request(): View
    {
        return view('wake::panel.auth.forgot');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        Password::broker('accounts')->sendResetLink($request->only('email'));

        // The same answer whether or not the address exists.
        return back()->with('status', __('If an account with this address exists, a mail with a link is on its way.'));
    }

    public function reset(Request $request, string $token): View
    {
        return view('wake::panel.auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request, Accounts $accounts): RedirectResponse
    {
        $request->validate(['token' => 'required', 'email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::min(10)]]);

        $status = Password::broker('accounts')->reset($request->only('email', 'password', 'password_confirmation', 'token'), function ($account, $password) use ($accounts) {
            $accounts->save($account->with(['password' => Hash::make($password), 'remember_token' => null]));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('wake.login')->with('status', __('Your password has been changed. You can sign in now.'))
            : back()->withErrors(['email' => __('This link is invalid or has expired. Please ask for a new one.')]);
    }

    public function invitation(Accounts $accounts, Invitations $invitations, string $id, string $token): View
    {
        $account = $accounts->find($id);
        abort_unless($invitations->valid($account, $token), 410, __('This invitation is invalid or has expired.'));

        return view('wake::panel.auth.invitation', ['account' => $account, 'token' => $token]);
    }

    public function accept(Request $request, Accounts $accounts, Invitations $invitations, string $id, string $token): RedirectResponse
    {
        $account = $accounts->find($id);
        abort_unless($invitations->valid($account, $token), 410);

        $request->validate(['password' => ['required', 'confirmed', PasswordRule::min(10)]]);

        Auth::login($invitations->accept($account, $request->input('password')), remember: true);
        $request->session()->regenerate();

        return redirect()->route('wake.dashboard')->with('status', __('Welcome aboard, :name.', ['name' => $account->name()]));
    }
}

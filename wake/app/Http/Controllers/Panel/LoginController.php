<?php

namespace Wake\Http\Controllers\Panel;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Wake\Accounts\Accounts;
use Wake\Accounts\TwoFactor;

class LoginController
{
    public function show(Accounts $accounts): View|RedirectResponse
    {
        if (! $accounts->exists()) {
            return redirect()->route('wake.setup');
        }

        return view('wake::panel.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('Too many attempts. Please try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        if (! Auth::validate($credentials)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['email' => __('These details do not match an account.')]);
        }

        RateLimiter::clear($key);
        $account = Auth::getProvider()->retrieveByCredentials($credentials);

        // With two-factor sign-in, the password only opens the second step.
        if (TwoFactor::isEnabled($account)) {
            $request->session()->put('wake.two_factor', ['id' => $account->id(), 'remember' => $request->boolean('remember'), 'until' => now()->getTimestamp() + 300]);

            return redirect()->route('wake.login.code');
        }

        Auth::login($account, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('wake.dashboard'));
    }

    public function code(Request $request): View|RedirectResponse
    {
        if (! $this->pending($request)) {
            return redirect()->route('wake.login');
        }

        return view('wake::panel.auth.code');
    }

    public function verify(Request $request, Accounts $accounts, TwoFactor $twoFactor): RedirectResponse
    {
        $pending = $this->pending($request) ?? throw ValidationException::withMessages(['code' => __('Please sign in again.')]);
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $key = 'two-factor:'.$pending['id'];

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'code' => __('Too many attempts. Please try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $account = $accounts->find($pending['id']);

        if (! $account || ! $twoFactor->verify($account, (string) $request->input('code'))) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['code' => __('This code is not valid. Check the time on your phone, or use a recovery code.')]);
        }

        RateLimiter::clear($key);
        $request->session()->forget('wake.two_factor');
        Auth::login($account, $pending['remember']);
        $request->session()->regenerate();

        if ($twoFactor->remainingRecoveryCodes($account) <= 2) {
            $request->session()->flash('status', __('Only :count recovery codes left. Make new ones under Settings.', ['count' => $twoFactor->remainingRecoveryCodes($accounts->find($account->id()))]));
        }

        return redirect()->intended(route('wake.dashboard'));
    }

    /**
     * @return array{id: string, remember: bool, until: int}|null
     */
    protected function pending(Request $request): ?array
    {
        $pending = $request->session()->get('wake.two_factor');

        return is_array($pending) && ($pending['until'] ?? 0) > now()->getTimestamp() ? $pending : null;
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('wake.login')->with('status', __('You are signed out.'));
    }
}

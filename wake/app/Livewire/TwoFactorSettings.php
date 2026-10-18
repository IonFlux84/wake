<?php

namespace Wake\Livewire;

use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Livewire\Component;
use Wake\Accounts\Accounts;
use Wake\Accounts\TwoFactor;

/**
 * Turning two-factor sign-in on and off for one's own account.
 */
class TwoFactorSettings extends Component
{
    /** The secret while setting up, before the first code confirms it. */
    public string $pendingSecret = '';

    public string $code = '';

    public string $password = '';

    /** @var list<string> shown once after turning it on */
    public array $recoveryCodes = [];

    public function start(TwoFactor $twoFactor): void
    {
        $this->pendingSecret = $twoFactor->newSecret();
        $this->reset('code', 'recoveryCodes');
    }

    public function confirm(TwoFactor $twoFactor, Accounts $accounts): void
    {
        $this->validate(['code' => 'required|string']);

        if ($this->pendingSecret === '' || TwoFactor::verifyCode($this->pendingSecret, $this->code) === null) {
            $this->addError('code', __('This code is not valid. Check the time on your phone, or use a recovery code.'));

            return;
        }

        $this->recoveryCodes = $twoFactor->enable($accounts->find(auth()->id()), $this->pendingSecret);
        $this->reset('pendingSecret', 'code');
    }

    public function cancel(): void
    {
        $this->reset('pendingSecret', 'code');
    }

    /**
     * New recovery codes, or switching it off, both need the password.
     */
    public function newRecoveryCodes(TwoFactor $twoFactor, Accounts $accounts): void
    {
        if ($this->checkPassword()) {
            $this->recoveryCodes = $twoFactor->regenerateRecoveryCodes($accounts->find(auth()->id()));
        }
    }

    public function disable(TwoFactor $twoFactor, Accounts $accounts): void
    {
        if ($this->checkPassword()) {
            $twoFactor->disable($accounts->find(auth()->id()));
            $this->reset('recoveryCodes');
            session()->flash('two_factor_status', __('Two-factor sign-in is off.'));
        }
    }

    public function render(TwoFactor $twoFactor, Accounts $accounts): View
    {
        $account = $accounts->find(auth()->id());

        return view('wake::livewire.two-factor-settings', [
            'enabled' => TwoFactor::isEnabled($account),
            'remaining' => $twoFactor->remainingRecoveryCodes($account),
            'qr' => $this->pendingSecret !== '' ? $twoFactor->qrCode($twoFactor->uri($account, $this->pendingSecret)) : null,
        ]);
    }

    protected function checkPassword(): bool
    {
        if (! Hash::check($this->password, auth()->user()->getAuthPassword())) {
            $this->addError('password', __('The password is not correct.'));

            return false;
        }

        $this->reset('password');

        return true;
    }
}

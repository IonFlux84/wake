<div class="form">
    <div class="card-head" style="margin:0">
        <h2 class="h2">{{ __('Two-factor sign-in') }}</h2>
        <span @class(['badge', 'on' => $enabled])>{{ $enabled ? __('On') : __('Off') }}</span>
    </div>
    @if (session('two_factor_status'))<div class="flash" role="status">{{ session('two_factor_status') }}</div>@endif

    @if ($recoveryCodes)
        <div class="flash" role="status">{{ __('Keep these recovery codes somewhere safe, for example in your password manager. Each one works once, if you lose your phone. They are shown only now.') }}</div>
        <ul class="mono" style="columns:2;margin:0;padding-left:18px;font-size:15px;line-height:1.8" aria-label="{{ __('Recovery codes') }}">
            @foreach ($recoveryCodes as $recoveryCode)<li>{{ $recoveryCode }}</li>@endforeach
        </ul>
        <div class="actions">
            <button type="button" class="btn btn-small" x-data="{ copied: false }" @click="navigator.clipboard.writeText(@js(implode("\n", $recoveryCodes))); copied = true; setTimeout(() => copied = false, 2000)" x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</button>
            @if ($required)<a class="btn btn-primary" href="{{ route('wake.dashboard') }}">{{ __('I have saved them, continue') }} →</a>@endif
        </div>
    @endif

    @if ($qr)
        <p class="muted" style="margin:0;font-size:14px">{{ __('Scan the code with an authenticator app (for example the one built into your password manager), then enter the six digits it shows.') }}</p>
        <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap">
            <div style="background:#fff;padding:8px;border-radius:4px;border:1px solid var(--line);line-height:0" aria-hidden="true">{!! $qr !!}</div>
            <div style="min-width:0">
                <div class="label">{{ __('Or enter this key') }}</div>
                <code class="codebox">{{ trim(chunk_split($pendingSecret, 4, ' ')) }}</code>
            </div>
        </div>
        <form class="form" wire:submit="confirm">
            <div class="field"><label for="tf-code">{{ __('Code from the app') }}</label><input id="tf-code" class="input mono" wire:model="code" inputmode="numeric" autocomplete="one-time-code" style="max-width:200px" @error('code') aria-invalid="true" aria-describedby="tf-code-error" @enderror>@error('code')<span class="error" id="tf-code-error">{{ $message }}</span>@enderror</div>
            <div class="actions"><button type="submit" class="btn btn-primary">{{ __('Turn on') }}</button><button type="button" class="btn" wire:click="cancel">{{ __('Cancel') }}</button></div>
        </form>
    @elseif ($enabled)
        <p class="muted" style="margin:0;font-size:14px">{{ trans_choice('After your password, Wake asks for a code from your app. :count recovery code left.|After your password, Wake asks for a code from your app. :count recovery codes left.', $remaining) }}</p>
        <div class="field"><label for="tf-pw">{{ __('Password, to make changes') }}</label><input id="tf-pw" type="password" class="input" wire:model="password" autocomplete="current-password" @error('password') aria-invalid="true" aria-describedby="tf-pw-error" @enderror>@error('password')<span class="error" id="tf-pw-error">{{ $message }}</span>@enderror</div>
        <div class="actions">
            <button type="button" class="btn" wire:click="newRecoveryCodes">{{ __('New recovery codes') }}</button>
            @unless ($siteRequires)
                <button type="button" class="btn btn-danger" wire:click="disable" wire:confirm="{{ __('Turn off two-factor sign-in?') }}">{{ __('Turn off') }}</button>
            @endunless
        </div>
    @else
        <p class="muted" style="margin:0;font-size:14px">{{ $siteRequires ? __('This site requires two-factor sign-in for everyone. It takes two minutes: you need an authenticator app on your phone.') : __('With a second step, a stolen password alone is not enough to sign in.') }}</p>
        <div class="actions"><button type="button" class="btn" wire:click="start"><x-wake::icon name="key" />{{ __('Set up') }}</button></div>
    @endif
</div>

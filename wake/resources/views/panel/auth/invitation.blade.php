<x-wake::layout.auth :title="__('Join the team')">
    <div class="auth-head">
        <p class="eyebrow">{{ app(\Wake\Content\Site::class)->title() }}</p>
        <h1 class="auth-title">{{ __('Welcome, :name', ['name' => $account->name()]) }}</h1>
        <p class="auth-lead">{{ __('Choose a password for :email to join as :role.', ['email' => $account->email(), 'role' => $account->role()->label()]) }}</p>
    </div>
    <form class="auth-form" method="post" action="{{ route('wake.invitation', ['id' => $account->id(), 'token' => $token]) }}" x-data="{ busy: false }" @submit="busy = true" @pageshow.window="busy = false">
        @csrf
        <div class="field"><label for="password">{{ __('Password') }}</label>
            <x-wake::password autocomplete="new-password" minlength="10" :autofocus="true" :hint="__('At least 10 characters.')" /></div>
        <div class="field"><label for="password_confirmation">{{ __('Repeat the password') }}</label>
            <x-wake::password id="password_confirmation" name="password_confirmation" autocomplete="new-password" /></div>
        <button class="btn btn-primary auth-submit" type="submit" :disabled="busy">{{ __('Join') }}</button>
    </form>
</x-wake::layout.auth>

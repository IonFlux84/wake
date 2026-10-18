<x-wake::layout.auth :title="__('Join the team')">
    <div>
        <div class="eyebrow">{{ app(\Wake\Content\Site::class)->title() }}</div>
        <h1 class="title" style="font-size:32px">{{ __('Welcome, :name', ['name' => $account->name()]) }}</h1>
        <p class="lead" style="font-size:15px">{{ __('Choose a password for :email to join as :role.', ['email' => $account->email(), 'role' => $account->role()->label()]) }}</p>
    </div>
    <form class="form" method="post" action="{{ route('wake.invitation', ['id' => $account->id(), 'token' => $token]) }}">
        @csrf
        <div class="field"><label for="password">{{ __('Password') }}</label><input class="input" id="password" type="password" name="password" required minlength="10" autocomplete="new-password" autofocus>
            <span class="hint">{{ __('At least 10 characters.') }}</span>@error('password')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="field"><label for="password_confirmation">{{ __('Repeat the password') }}</label><input class="input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div>
        <button class="btn btn-primary" type="submit">{{ __('Join') }}</button>
    </form>
</x-wake::layout.auth>

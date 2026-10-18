<x-wake::layout.auth :title="__('Choose a new password')">
    <h1 class="title" style="font-size:32px">{{ __('Choose a new password') }}</h1>
    <form class="form" method="post" action="{{ route('wake.password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="field"><label for="email">{{ __('Email address') }}</label><input class="input" id="email" type="email" name="email" value="{{ old('email', $email) }}" required>
            @error('email')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="field"><label for="password">{{ __('New password') }}</label><input class="input" id="password" type="password" name="password" required minlength="10" autocomplete="new-password">
            <span class="hint">{{ __('At least 10 characters.') }}</span>@error('password')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="field"><label for="password_confirmation">{{ __('Repeat the password') }}</label><input class="input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div>
        <button class="btn btn-primary" type="submit">{{ __('Save password') }}</button>
    </form>
</x-wake::layout.auth>

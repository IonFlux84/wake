<x-wake::layout.auth :title="__('Choose a new password')">
    <div class="auth-head"><h1 class="auth-title">{{ __('Choose a new password') }}</h1></div>
    <form class="auth-form" method="post" action="{{ route('wake.password.update') }}" x-data="{ busy: false }" @submit="busy = true" @pageshow.window="busy = false">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="field">
            <label for="email">{{ __('Email address') }}</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username" autocapitalize="none" spellcheck="false"
                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')<span class="error" id="email-error">{{ $message }}</span>@enderror
        </div>
        <div class="field"><label for="password">{{ __('New password') }}</label>
            <x-wake::password autocomplete="new-password" minlength="10" :hint="__('At least 10 characters.')" /></div>
        <div class="field"><label for="password_confirmation">{{ __('Repeat the password') }}</label>
            <x-wake::password id="password_confirmation" name="password_confirmation" autocomplete="new-password" /></div>
        <button class="btn btn-primary auth-submit" type="submit" :disabled="busy">{{ __('Save password') }}</button>
    </form>
</x-wake::layout.auth>

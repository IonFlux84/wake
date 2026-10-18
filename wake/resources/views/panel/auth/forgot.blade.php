<x-wake::layout.auth :title="__('Forgot your password?')">
    <div class="auth-head">
        <h1 class="auth-title">{{ __('Forgot your password?') }}</h1>
        <p class="auth-lead">{{ __('Enter your email address. We send you a link to choose a new password.') }}</p>
    </div>
    @if (session('status'))<div class="auth-note" role="status"><span class="auth-note-k">{{ __('Status') }}</span><span class="auth-note-v">{{ session('status') }}</span></div>@endif
    <form class="auth-form" method="post" action="{{ route('wake.password.email') }}" x-data="{ busy: false }" @submit="busy = true" @pageshow.window="busy = false">
        @csrf
        <div class="field">
            <label for="email">{{ __('Email address') }}</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false"
                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')<span class="error" id="email-error">{{ $message }}</span>@enderror
        </div>
        <button class="btn btn-primary auth-submit" type="submit" :disabled="busy">{{ __('Send link') }}</button>
        <a class="auth-link" href="{{ route('wake.login') }}">{{ __('Back to sign in') }}</a>
    </form>
</x-wake::layout.auth>

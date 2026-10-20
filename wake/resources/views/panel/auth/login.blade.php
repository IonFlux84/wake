<x-wake::layout.auth :title="__('Sign in')">
    <div class="auth-head">
        <p class="eyebrow">{{ app(\Wake\Content\Site::class)->title() }}</p>
        <h1 class="auth-title">{{ __('Sign in') }}</h1>
        @if (config('wake.closed'))<p class="auth-lead">{{ __('This website is only visible to the team at the moment.') }}</p>@endif
    </div>

    @if (session('status'))
        <div class="auth-note" role="status"><span class="auth-note-k">{{ __('Status') }}</span><span class="auth-note-v">{{ session('status') }}</span></div>
    @endif

    <form class="auth-form" method="post" action="{{ route('wake.login') }}" x-data="{ busy: false }" @submit="busy = true" @pageshow.window="busy = false">
        @csrf
        <div class="field">
            <label for="email">{{ __('Email address') }}</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false"
                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')<span class="error" id="email-error">{{ $message }}</span>@enderror
        </div>

        <div class="field">
            <div class="field-row">
                <label for="password">{{ __('Password') }}</label>
                @if (Route::has('wake.password.request'))<a class="auth-link" href="{{ route('wake.password.request') }}">{{ __('Forgot your password?') }}</a>@endif
            </div>
            <x-wake::password />
        </div>

        <label class="check"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> {{ __('Stay signed in') }}</label>

        <button class="btn btn-primary auth-submit" type="submit" :disabled="busy" :aria-busy="busy.toString()">
            <span x-show="!busy">{{ __('Sign in') }} <span class="mono" aria-hidden="true">→</span></span>
            <span x-show="busy" x-cloak>{{ __('One moment …') }}</span>
        </button>
    </form>
</x-wake::layout.auth>

<x-wake::layout.auth :title="__('Sign in')">
    <div>
        <div class="eyebrow">{{ app(\Wake\Content\Site::class)->title() }}</div>
        <h1 class="title" style="font-size:32px">{{ __('Sign in') }}</h1>
        @if (config('wake.closed'))<p class="lead" style="font-size:15px">{{ __('This website is only visible to its team at the moment.') }}</p>@endif
    </div>

    @if (session('status'))
        <div class="flash" role="status">{{ session('status') }}</div>
    @endif

    <form class="form" method="post" action="{{ route('wake.login') }}">
        @csrf
        <div class="field">
            <label for="email">{{ __('Email address') }}</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus>
            @error('email')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="password">{{ __('Password') }}</label>
            <input class="input" id="password" type="password" name="password" required autocomplete="current-password">
        </div>
        <label class="check"><input type="checkbox" name="remember" value="1"> {{ __('Stay signed in') }}</label>
        <div class="actions">
            <button class="btn btn-primary" type="submit">{{ __('Sign in') }}</button>
            @if (Route::has('wake.password.request'))
                <a href="{{ route('wake.password.request') }}" class="muted">{{ __('Forgot your password?') }}</a>
            @endif
        </div>
    </form>
</x-wake::layout.auth>

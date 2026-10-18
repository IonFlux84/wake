<x-wake::layout.auth :title="__('Sign in')">
    <div>
        <div class="eyebrow">{{ app(\Wake\Content\Site::class)->title() }}</div>
        <h1 class="title" style="font-size:32px">{{ __('One more step') }}</h1>
        <p class="lead" style="font-size:15px">{{ __('Enter the code from your authenticator app. If you do not have your phone, use one of your recovery codes.') }}</p>
    </div>

    <form class="form" method="post" action="{{ route('wake.login.code') }}">
        @csrf
        <div class="field">
            <label for="code">{{ __('Code') }}</label>
            <input class="input mono" id="code" name="code" required autofocus autocomplete="one-time-code" inputmode="numeric" style="font-size:22px;letter-spacing:.2em;max-width:220px">
            @error('code')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div class="actions">
            <button class="btn btn-primary" type="submit">{{ __('Sign in') }}</button>
            <a href="{{ route('wake.login') }}" class="muted">{{ __('Back') }}</a>
        </div>
    </form>
</x-wake::layout.auth>

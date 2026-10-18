<x-wake::layout.auth :title="__('Sign in')">
    <div class="auth-head">
        <p class="eyebrow">{{ __('Step :current of :total', ['current' => 2, 'total' => 2]) }} · {{ app(\Wake\Content\Site::class)->title() }}</p>
        <h1 class="auth-title">{{ __('One more step') }}</h1>
        <p class="auth-lead">{{ __('Enter the code from your authenticator app. If you do not have your phone, use one of your recovery codes.') }}</p>
    </div>
    <form class="auth-form" method="post" action="{{ route('wake.login.code') }}" x-data="{ busy: false }" @submit="busy = true" @pageshow.window="busy = false">
        @csrf
        <div class="field">
            <label for="code">{{ __('Code') }}</label>
            <input class="input auth-code" id="code" name="code" required autofocus autocomplete="one-time-code" inputmode="numeric" maxlength="20" autocapitalize="none" spellcheck="false"
                   @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
            @error('code')<span class="error" id="code-error">{{ $message }}</span>@enderror
        </div>
        <button class="btn btn-primary auth-submit" type="submit" :disabled="busy">{{ __('Sign in') }} <span class="mono" aria-hidden="true">→</span></button>
        <a class="auth-link" href="{{ route('wake.login') }}">{{ __('Back') }}</a>
    </form>
</x-wake::layout.auth>

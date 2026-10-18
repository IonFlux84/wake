<x-wake::layout.auth :title="__('Set up Wake')">
    <div class="auth-head">
        <p class="eyebrow">{{ __('Welcome aboard') }}</p>
        <h1 class="auth-title">{{ __('Set up Wake') }}</h1>
        <p class="auth-lead">{{ __('Create your account. You will be the owner of this site and can invite your team later.') }}</p>
    </div>

    @if ($warnings)
        <div class="auth-note is-warn" role="alert"><span class="auth-note-k">{{ __('Check this') }}</span><span class="auth-note-v">@foreach ($warnings as $warning)<span>{{ $warning }}</span>@endforeach</span></div>
    @endif

    <form class="auth-form" method="post" action="{{ route('wake.setup') }}" x-data="{ busy: false }" @submit="busy = true" @pageshow.window="busy = false">
        @csrf
        <div class="field">
            <label for="name">{{ __('Your name') }}</label>
            <input class="input" id="name" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus
                   @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
            @error('name')<span class="error" id="name-error">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="email">{{ __('Email address') }}</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" autocapitalize="none" spellcheck="false"
                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')<span class="error" id="email-error">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="password">{{ __('Password') }}</label>
            <x-wake::password autocomplete="new-password" minlength="10" :hint="__('At least 10 characters.')" />
        </div>
        <div class="field">
            <label for="language">{{ __('Language of the panel') }}</label>
            <select class="select" id="language" name="language">
                <option value="de" @selected(old('language', app()->getLocale()) === 'de')>Deutsch</option>
                <option value="en" @selected(old('language', app()->getLocale()) === 'en')>English</option>
            </select>
        </div>
        <button class="btn btn-primary auth-submit" type="submit" :disabled="busy">{{ __('Create account') }} <span class="mono" aria-hidden="true">→</span></button>
    </form>
</x-wake::layout.auth>

<x-wake::layout.auth :title="__('Set up Wake')">
    <div>
        <div class="eyebrow">{{ __('Welcome aboard') }}</div>
        <h1 class="title" style="font-size:32px">{{ __('Set up Wake') }}</h1>
        <p class="lead" style="font-size:15px">{{ __('Create your account. You will be the owner of this site and can invite your team later.') }}</p>
    </div>

    @if ($warnings)
        <div class="card" style="border-color:var(--danger)">
            @foreach ($warnings as $warning)<p style="margin:0">{{ $warning }}</p>@endforeach
        </div>
    @endif

    <form class="form" method="post" action="{{ route('wake.setup') }}">
        @csrf
        <div class="field">
            <label for="name">{{ __('Your name') }}</label>
            <input class="input" id="name" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>
            @error('name')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="email">{{ __('Email address') }}</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
            @error('email')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="password">{{ __('Password') }}</label>
            <input class="input" id="password" type="password" name="password" required autocomplete="new-password" minlength="10">
            <span class="hint">{{ __('At least 10 characters.') }}</span>
            @error('password')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="language">{{ __('Language of the panel') }}</label>
            <select class="select" id="language" name="language">
                <option value="de" @selected(old('language', app()->getLocale()) === 'de')>Deutsch</option>
                <option value="en" @selected(old('language', app()->getLocale()) === 'en')>English</option>
            </select>
        </div>
        <button class="btn btn-primary" type="submit">{{ __('Create account') }}</button>
    </form>
</x-wake::layout.auth>

<x-wake::layout.auth :title="__('Forgot your password?')">
    <div>
        <h1 class="title" style="font-size:32px">{{ __('Forgot your password?') }}</h1>
        <p class="lead" style="font-size:15px">{{ __('Enter your email address. We send you a link to choose a new password.') }}</p>
    </div>
    @if (session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif
    <form class="form" method="post" action="{{ route('wake.password.email') }}">
        @csrf
        <div class="field"><label for="email">{{ __('Email address') }}</label><input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="actions"><button class="btn btn-primary" type="submit">{{ __('Send link') }}</button><a class="muted" href="{{ route('wake.login') }}">{{ __('Back to sign in') }}</a></div>
    </form>
</x-wake::layout.auth>

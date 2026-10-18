<x-wake::layout.auth :title="__('Two-factor sign-in')" :wide="true">
    <div class="auth-head">
        <p class="eyebrow">{{ app(\Wake\Content\Site::class)->title() }}</p>
        <h1 class="auth-title">{{ __('Secure your account') }}</h1>
        <p class="auth-lead">{{ __('This site asks everyone on the team to sign in with a second step. Set it up once, then you can continue.') }}</p>
    </div>
    <livewire:wake.two-factor-settings :required="true" />
    <form method="post" action="{{ route('wake.logout') }}">
        @csrf
        <button type="submit" class="link-quiet">{{ __('Sign out') }}</button>
    </form>
</x-wake::layout.auth>

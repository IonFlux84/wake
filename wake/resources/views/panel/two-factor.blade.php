<x-wake::layout.auth :title="__('Two-factor sign-in')">
    <div>
        <div class="eyebrow">{{ app(\Wake\Content\Site::class)->title() }}</div>
        <h1 class="title" style="font-size:32px">{{ __('Secure your account') }}</h1>
        <p class="lead" style="font-size:15px">{{ __('This site asks everyone on the team to sign in with a second step. Set it up once, then you can continue.') }}</p>
    </div>
    <livewire:wake.two-factor-settings :required="true" />
    <form method="post" action="{{ route('wake.logout') }}">
        @csrf
        <button type="submit" class="btn btn-small" style="background:none;border:0;padding:0;color:var(--muted);text-decoration:underline">{{ __('Sign out') }}</button>
    </form>
</x-wake::layout.auth>

@props(['title' => null, 'wide' => false])
<x-wake::layout.base :title="$title">
<div class="auth">
    <header class="auth-ink">
        <div class="auth-brand"><x-wake::logo :size="28" id="auth" /><span>wake</span></div>

        <div class="auth-hero">
            <p class="auth-claim">{!! __('Every post leaves a <span class="mono">wake.</span>') !!}<span class="caret" aria-hidden="true"></span></p>
            <p class="auth-ink-lead">{{ __('Everything you write is a file on your own web space.') }}</p>

            <figure class="auth-window" aria-hidden="true">
                <div class="auth-window-bar mono"><span>content/posts/</span><span>content.md</span></div>
                <svg class="auth-wake" viewBox="0 0 400 236">
                    <path class="axis" d="M200 22V236"/>
                    <path class="arm" pathLength="1" d="M200 22 124.3 236"/>
                    <path class="arm" pathLength="1" d="M200 22 275.7 236"/>
                    <path class="wave w1" d="M180.2 78Q200 70 219.8 78"/>
                    <path class="wave w2" d="M159 138Q200 124 241 138"/>
                    <path class="wave w3" d="M137.8 198Q200 178 262.2 198"/>
                    <path class="tick w1" d="M226 78H292"/>
                    <path class="tick w2" d="M247 138H292"/>
                    <path class="tick w3" d="M268 198H292"/>
                    <text class="w1 now" x="298" y="82">{{ __('Published') }}</text>
                    <text class="w2" x="298" y="142">{{ __('Scheduled') }}</text>
                    <text class="w3" x="298" y="202">{{ __('Draft') }}</text>
                    <path class="hull" d="M200 10 204 20h-8z"/>
                </svg>
            </figure>
        </div>

        <p class="auth-ink-foot mono">{{ __('Open source · MIT · no database') }}</p>
    </header>

    <main class="auth-main">
        <div class="auth-top mono">
            <a href="{{ url('/') }}">← {{ __('Back to the website') }}</a>
            <span>{{ parse_url(url('/'), PHP_URL_HOST) }}</span>
        </div>
        <div @class(['auth-body', 'is-wide' => $wide])>{{ $slot }}</div>
    </main>
</div>
</x-wake::layout.base>

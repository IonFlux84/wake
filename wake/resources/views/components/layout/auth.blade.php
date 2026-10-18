@props(['title' => null])
<x-wake::layout.base :title="$title">
<div class="auth">
    <div class="auth-art">
        <div class="brand" style="padding:0">
            <span class="brand-mark" style="background:#1a1a1a"><x-wake::logo /></span>
            <span class="brand-name" style="color:#fff">Wake</span>
        </div>
        <h2>{!! __('Write it. <em>Watch it travel.</em>') !!}</h2>
        <div class="waves" aria-hidden="true">
            <svg viewBox="0 0 2000 90" preserveAspectRatio="none"><path d="M0 50 Q 125 20 250 50 T 500 50 T 750 50 T 1000 50 T 1250 50 T 1500 50 T 1750 50 T 2000 50" fill="none" stroke="var(--accent)" stroke-opacity=".3" stroke-width="1.4"/></svg>
            <svg viewBox="0 0 2000 90" preserveAspectRatio="none"><path d="M0 60 Q 100 35 200 60 T 400 60 T 600 60 T 800 60 T 1000 60 T 1200 60 T 1400 60 T 1600 60 T 1800 60 T 2000 60" fill="none" stroke="#fff" stroke-opacity=".1" stroke-width="1.2"/></svg>
        </div>
    </div>
    <div class="auth-form">
        <div>{{ $slot }}</div>
    </div>
</div>
</x-wake::layout.base>

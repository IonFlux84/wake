@props(['size' => 28, 'tile' => false, 'id' => null])
@php($gid = 'wk-'.($id ?? \Illuminate\Support\Str::random(6)))
{{-- The Wake mark: a hull and its wake at the Kelvin angle (19.47°). White strokes: pass :tile="true" on light backgrounds. --}}
<svg {{ $attributes->class('wake-mark') }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 64 64" fill="none" aria-hidden="true">
    <defs><linearGradient id="{{ $gid }}" x1="0" y1="15" x2="0" y2="52.5" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".25"/></linearGradient></defs>
    @if ($tile)<rect width="64" height="64" rx="14" fill="#0A0A0A"/>@endif
    <path class="wk-trail" pathLength="1" d="M32 15 18.74 52.5M32 15l13.26 37.5" stroke="url(#{{ $gid }})" stroke-width="4" stroke-linecap="round"/>
    <path d="M32 9.5 35.5 17h-7z" fill="#fff" stroke="#fff" stroke-width="2" stroke-linejoin="round"/>
</svg>

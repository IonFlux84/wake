@props(['name', 'size' => null])
@php
$paths = [
    'dashboard' => '<path d="M3 13h8V3H3zM13 21h8V11h-8zM13 3v5h8V3zM3 21h8v-5H3z" stroke-linejoin="round"/>',
    'posts' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" stroke-linejoin="round"/>',
    'pages' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z" stroke-linejoin="round"/><path d="M14 3v6h6"/>',
    'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
    'media' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>',
    'stats' => '<path d="M5 20V11M11 20V5M17 20v-6M3 20h18"/>',
    'wake' => '<path d="M12 4 4 20M12 4l8 16"/><path d="M12 4 9 20M12 4l3 16" stroke-opacity=".5"/>',
    'consent' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z" stroke-linejoin="round"/><path d="m9 12 2 2 4-4"/>',
    'assistant' => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3"/><circle cx="12" cy="12" r="5"/><path d="M10 12h4"/>',
    'team' => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M16 4a4 4 0 0 1 0 8M22 21a7 7 0 0 0-4-6.3"/>',
    'settings' => '<path d="M4 6h9M17 6h3M4 12h3M11 12h9M4 18h11M19 18h1"/><circle cx="15" cy="6" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="18" r="2"/>',
    'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
    'plus' => '<path d="M12 5v14M5 12h14"/>',
    'logout' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l-5-5 5-5M5 12h11"/>',
    'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
    'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
    'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
    'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    'check' => '<path d="m5 12 5 5L20 7"/>',
    'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
    'key' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 6l3 3M14 9l2 2"/>',
    'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l3 2"/>',
    'upload' => '<path d="M12 16V4M6 10l6-6 6 6M4 20h16"/>',
    'download' => '<path d="M12 4v12M6 10l6 6 6-6M4 20h16"/>',
    'tag' => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z" stroke-linejoin="round"/><circle cx="8" cy="8" r="1.5"/>',
];
@endphp
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true" @if ($size) width="{{ $size }}" height="{{ $size }}" @endif {{ $attributes }}>{!! $paths[$name] ?? '' !!}</svg>

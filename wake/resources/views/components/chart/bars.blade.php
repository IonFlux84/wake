@props(['series', 'key' => 'visitors', 'height' => 200, 'label' => ''])
@php
    $values = array_map(fn ($p) => $p[$key] ?? 0, array_values($series));
    $days = array_keys($series);
    $max = max(1, ...($values ?: [0]));
    $count = max(count($values), 1);
@endphp
<div role="img" aria-label="{{ $label }}" style="display:flex;align-items:flex-end;gap:{{ $count > 60 ? 1 : 4 }}px;height:{{ $height }}px" x-data="{ tip: null }">
    @foreach ($values as $i => $value)
        <div style="flex:1;position:relative;height:100%;display:flex;align-items:flex-end" @mouseenter="tip = {{ $i }}" @mouseleave="tip = null">
            <div style="width:100%;height:{{ max(2, round($value / $max * 100)) }}%;border-radius:4px 4px 0 0;background:{{ $loop->last ? 'var(--accent)' : '#2a2a2a' }};{{ $loop->last ? 'box-shadow:0 0 14px var(--glow);' : '' }}transform-origin:bottom;animation:rise .8s ease-out both;animation-delay:{{ min($i * 15, 600) }}ms"></div>
            <div x-cloak x-show="tip === {{ $i }}" style="position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);white-space:nowrap;font:500 12px var(--sans);background:#1f1f1f;color:#fff;padding:6px 9px;border-radius:8px;pointer-events:none;z-index:2">
                {{ \Carbon\CarbonImmutable::parse($days[$i])->locale(app()->getLocale())->isoFormat('dd D. MMM') }} · {{ $value }}
            </div>
        </div>
    @endforeach
</div>

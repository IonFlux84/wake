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
            <div style="width:100%;height:{{ max(2, round($value / $max * 100)) }}%;border-radius:1px 1px 0 0;background:{{ $loop->last ? 'var(--accent-ink)' : '#3a3a3a' }};transform-origin:bottom;animation:rise .8s ease-out both;animation-delay:{{ min($i * 15, 600) }}ms"></div>
            <div x-cloak x-show="tip === {{ $i }}" class="tip">
                {{ \Carbon\CarbonImmutable::parse($days[$i])->locale(app()->getLocale())->isoFormat('dd D. MMM') }} · {{ $value }}
            </div>
        </div>
    @endforeach
</div>

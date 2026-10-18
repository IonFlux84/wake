@props(['series', 'height' => 250, 'primary' => 'visitors', 'secondary' => 'pageviews', 'label' => null])
@php
    // Server-rendered SVG line chart. Values are scaled into a 1000 × height box.
    $points = array_values($series);
    $keys = array_keys($series);
    $count = max(count($points), 1);
    $max = max(1, ...array_map(fn ($p) => max($p[$primary] ?? 0, $p[$secondary] ?? 0), $points ?: [[]]));
    $x = fn (int $i) => $count === 1 ? 500 : round($i * 1000 / ($count - 1), 1);
    $y = fn ($v) => round($height - 12 - ($v / $max) * ($height - 36), 1);
    $line = fn (string $key) => collect($points)->map(fn ($p, $i) => ($i === 0 ? 'M' : 'L').$x($i).' '.$y($p[$key] ?? 0))->implode(' ');
    $area = $line($primary).' L'.$x($count - 1).' '.$height.' L0 '.$height.' Z';
    $last = $points ? end($points) : [$primary => 0];
@endphp
<div class="chart" style="position:relative;height:{{ $height }}px" x-data="{ tip: null }">
    <svg viewBox="0 0 1000 {{ $height }}" preserveAspectRatio="none" width="100%" height="{{ $height }}" role="img" aria-label="{{ $label ?? __('Visitors per day') }}" style="display:block;overflow:visible;animation:reveal 1.8s ease-out both">
        <path d="M0 {{ round($height * .25) }}H1000M0 {{ round($height * .5) }}H1000M0 {{ round($height * .75) }}H1000" style="stroke:var(--night-line)" stroke-dasharray="2 6" stroke-width="1" vector-effect="non-scaling-stroke"/>
        <path d="{{ $area }}" fill="#fff" fill-opacity=".04" style="animation:fade 1.6s ease-out both"/>
        <path d="{{ $line($secondary) }}" fill="none" style="stroke:var(--night-dim)" stroke-width="1.25" stroke-dasharray="4 4" vector-effect="non-scaling-stroke" stroke-linejoin="round"/>
        <path d="{{ $line($primary) }}" fill="none" stroke="var(--accent-ink)" stroke-width="1.75" vector-effect="non-scaling-stroke" stroke-linejoin="round"/>
    </svg>
    <span aria-hidden="true" style="position:absolute;left:calc({{ $x($count - 1) / 10 }}% - 4px);top:{{ $y($last[$primary] ?? 0) - 4 }}px;width:8px;height:8px;border-radius:50%;background:var(--accent-ink)"></span>
    <div style="position:absolute;inset:0;display:grid;grid-template-columns:repeat({{ $count }},1fr)">
        @foreach ($keys as $i => $day)
            <div @mouseenter="tip = {{ $i }}" @mouseleave="tip = null" style="position:relative">
                <div x-cloak x-show="tip === {{ $i }}" class="tip">
                    {{ \Carbon\CarbonImmutable::parse($day)->locale(app()->getLocale())->isoFormat('dd D. MMM') }} · {{ number_format($points[$i][$primary] ?? 0, 0, ',', '.') }} / {{ number_format($points[$i][$secondary] ?? 0, 0, ',', '.') }}
                </div>
            </div>
        @endforeach
    </div>
</div>

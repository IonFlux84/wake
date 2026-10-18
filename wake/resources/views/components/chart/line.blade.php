@props(['series', 'height' => 250, 'primary' => 'visitors', 'secondary' => 'pageviews', 'label' => ''])
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
    <svg viewBox="0 0 1000 {{ $height }}" preserveAspectRatio="none" width="100%" height="{{ $height }}" role="img" aria-label="{{ $label }}" style="display:block;overflow:visible;animation:reveal 1.8s ease-out both">
        <path d="M0 {{ round($height * .25) }}H1000M0 {{ round($height * .5) }}H1000M0 {{ round($height * .75) }}H1000" stroke="#1f1f1f" stroke-width="1" vector-effect="non-scaling-stroke"/>
        <path d="{{ $area }}" fill="var(--accent)" fill-opacity=".10" style="animation:fade 1.6s ease-out both"/>
        <path d="{{ $line($secondary) }}" fill="none" stroke="#8a8a8a" stroke-width="1.6" stroke-dasharray="5 5" vector-effect="non-scaling-stroke" stroke-linejoin="round" style="opacity:.8"/>
        <path d="{{ $line($primary) }}" fill="none" stroke="var(--accent)" stroke-width="2.6" vector-effect="non-scaling-stroke" stroke-linejoin="round" style="filter:drop-shadow(0 0 6px var(--glow))"/>
    </svg>
    <span aria-hidden="true" style="position:absolute;left:calc({{ $x($count - 1) / 10 }}% - 6px);top:{{ $y($last[$primary] ?? 0) - 6 }}px;width:12px;height:12px;border-radius:50%;background:var(--accent);box-shadow:0 0 12px var(--glow)">
        <span style="position:absolute;inset:0;border-radius:50%;border:2px solid var(--accent);animation:ring 2.4s ease-out infinite"></span>
    </span>
    <div style="position:absolute;inset:0;display:grid;grid-template-columns:repeat({{ $count }},1fr)">
        @foreach ($keys as $i => $day)
            <div @mouseenter="tip = {{ $i }}" @mouseleave="tip = null" style="position:relative">
                <div x-cloak x-show="tip === {{ $i }}" style="position:absolute;bottom:100%;left:50%;transform:translateX(-50%);white-space:nowrap;font:500 12px var(--mono);background:#1f1f1f;color:#fff;padding:6px 9px;border-radius:8px;pointer-events:none;z-index:2">
                    {{ \Carbon\CarbonImmutable::parse($day)->locale(app()->getLocale())->isoFormat('dd D. MMM') }} · {{ number_format($points[$i][$primary] ?? 0, 0, ',', '.') }} / {{ number_format($points[$i][$secondary] ?? 0, 0, ',', '.') }}
                </div>
            </div>
        @endforeach
    </div>
</div>

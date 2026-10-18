@props(['items', 'empty' => null, 'labels' => []])
@php($max = max([1, ...array_values($items)]))
<div style="display:grid;gap:12px">
    @forelse ($items as $name => $count)
        <div style="display:grid;gap:6px">
            <div style="display:flex;justify-content:space-between;gap:10px;font-size:14px"><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $labels[$name] ?? ($name === '' ? __('Direct') : $name) }}</span><span class="mono">{{ $count }}</span></div>
            <div class="bar"><i style="width:{{ round($count / $max * 100) }}%"></i></div>
        </div>
    @empty
        <p class="muted" style="margin:0">{{ $empty ?? __('No visits yet in this period.') }}</p>
    @endforelse
</div>

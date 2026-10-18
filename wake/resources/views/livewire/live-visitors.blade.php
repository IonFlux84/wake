<div class="card" wire:poll.15s>
    <div class="card-head">
        <h2 class="h2">{{ __('Right now') }}</h2>
        <span class="status live">{{ __('live') }}</span>
    </div>
    <div style="display:flex;align-items:baseline;gap:10px">
        <span class="kpi-value">{{ $live['visitors'] }}</span>
        <span class="muted">{{ trans_choice('visitor on the site|visitors on the site', $live['visitors']) }}</span>
    </div>
    @forelse (array_slice($live['pages'], 0, 5, true) as $path => $count)
        <div style="display:flex;justify-content:space-between;gap:12px;font-size:14px">
            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $path }}</span>
            <span class="mono">{{ $count }}</span>
        </div>
    @empty
        <p class="muted" style="margin:0">{{ __('Calm waters. Nobody is reading at the moment.') }}</p>
    @endforelse
</div>

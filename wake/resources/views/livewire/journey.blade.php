@php
    $de = app()->getLocale() === 'de';
    $number = fn ($n) => number_format($n, 0, $de ? ',' : '.', $de ? '.' : ',');
    $duration = fn (int $s) => $s >= 60 ? intdiv($s, 60).' min '.str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT).' s' : $s.' s';
    $sources = array_slice($page['sources'], 0, 6, true);
    $next = array_slice($page['next'], 0, 6, true);
    $title = fn (string $path) => $report->entryFor($path)?->title() ?? $path;
@endphp
<div style="display:flex;flex-direction:column;gap:20px">
    <header class="page-head">
        <div>
            <div class="eyebrow"><a href="{{ route('wake.stats') }}" style="color:inherit;text-decoration:none">← {{ __('Statistics') }}</a> · {{ __('Wake') }}</div>
            <h1 class="title">{{ $entry?->title() ?? $path }}</h1>
            <p class="lead mono" style="font-size:14px">{{ $path }}@if ($entry) · <a href="{{ $entry->url() }}" target="_blank" rel="noopener">{{ __('Open') }} ↗</a>@endif</p>
        </div>
        @include('wake::livewire.partials.period')
    </header>

    <section class="grid grid-4">
        <div class="card"><div class="kpi-label">{{ __('Visitors') }}</div><div class="kpi-value">{{ $number($page['visitors']) }}</div></div>
        <div class="card"><div class="kpi-label">{{ __('Page views') }}</div><div class="kpi-value">{{ $number($page['pageviews']) }}</div></div>
        <div class="card"><div class="kpi-label">{{ __('Reading time') }}</div><div class="kpi-value">{{ $duration($page['duration']) }}</div></div>
        <div class="card"><div class="kpi-label">{{ __('Read to the end') }}</div><div class="kpi-value">{{ $page['read_rate'] === null ? '–' : round($page['read_rate'] * 100).' %' }}</div><div class="kpi-note">{{ __('average scroll depth :n %', ['n' => $page['scroll']]) }}</div></div>
    </section>

    <section class="night" aria-label="{{ __('The wake of this page') }}">
        <div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:22px"><h2 class="h2">{{ __('The wake of this page') }}</h2><span class="muted" style="font-size:13px">{{ __('Where readers came from, and where they went next') }}</span></div>
        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;align-items:center">
            <div style="display:grid;gap:10px">
                <div class="mono" style="font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#8a8a8a">{{ __('Came from') }}</div>
                @forelse ($sources as $source => $count)
                    <div style="display:flex;justify-content:space-between;gap:8px;padding:10px 12px;border:1px solid #2a2a2a;border-radius:10px;animation:rise .6s ease-out both;animation-delay:{{ $loop->index * 80 }}ms"><span>{{ $source === '' ? __('Direct') : $source }}</span><span class="mono" style="color:var(--accent)">{{ $count }}</span></div>
                @empty
                    <p style="margin:0;color:#8a8a8a">{{ __('No visits yet in this period.') }}</p>
                @endforelse
            </div>
            <div style="text-align:center;padding:28px 16px;border-radius:16px;background:#141414;border:1px solid var(--accent);box-shadow:0 0 30px -8px var(--glow)">
                <x-wake::icon name="wake" size="28" style="margin:0 auto 10px;color:var(--accent)" />
                <div style="font:800 20px/1.2 var(--sans);font-stretch:115%">{{ $entry?->title() ?? $path }}</div>
                <div class="mono" style="margin-top:8px;color:#bdbdbd;font-size:13px">{{ $number($page['visitors']) }} {{ __('Visitors') }}</div>
            </div>
            <div style="display:grid;gap:10px">
                <div class="mono" style="font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#8a8a8a">{{ __('Went on to') }}</div>
                @forelse ($next as $to => $count)
                    <a href="{{ route('wake.stats.journey', ['path' => $to, 'period' => $period]) }}" style="display:flex;justify-content:space-between;gap:8px;padding:10px 12px;border:1px solid #2a2a2a;border-radius:10px;color:#fff;text-decoration:none;animation:rise .6s ease-out both;animation-delay:{{ $loop->index * 80 }}ms"><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $title($to) }}</span><span class="mono" style="color:var(--accent)">{{ $count }}</span></a>
                @empty
                    <p style="margin:0;color:#8a8a8a">{{ __('Nobody went on from here yet.') }}</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="card">
        <h2 class="h2">{{ __('Visitors per day') }}</h2>
        <div wire:key="journey-{{ $period }}" style="background:var(--night);border-radius:14px;padding:18px"><x-wake::chart.bars :series="$series" :height="160" :label="__('Visitors per day')" /></div>
    </section>
</div>

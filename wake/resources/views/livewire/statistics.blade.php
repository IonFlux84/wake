@php
    $de = app()->getLocale() === 'de';
    $number = fn ($n) => number_format($n, 0, $de ? ',' : '.', $de ? '.' : ',');
    $duration = fn (int $s) => $s >= 60 ? intdiv($s, 60).' min '.str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT).' s' : $s.' s';
    $countries = collect($report->top('countries', 8))->mapWithKeys(fn ($n, $code) => [$code => $n])->all();
    $countryNames = collect(array_keys($countries))->mapWithKeys(fn ($code) => [$code => $code === '?' ? __('Unknown') : (class_exists(\Locale::class) ? \Locale::getDisplayRegion('-'.$code, app()->getLocale()) : $code)])->all();
@endphp
<div style="display:flex;flex-direction:column;gap:20px">
    <header class="page-head">
        <div>
            <div class="eyebrow">{{ $report->from->locale(app()->getLocale())->isoFormat('D. MMM') }} – {{ $report->to->locale(app()->getLocale())->isoFormat('D. MMM YYYY') }}</div>
            <h1 class="title">{{ __('Statistics') }}</h1>
        </div>
        @include('wake::livewire.partials.period')
    </header>

    <section class="kpis" aria-label="{{ __('Key figures') }}">
        <div class="kpi"><div class="kpi-label">{{ __('Visitors') }}</div><div class="kpi-value">{{ $number($report->visitors()) }}</div><div class="kpi-note">{{ __('added up per day, without cookies') }}</div></div>
        <div class="kpi"><div class="kpi-label">{{ __('Page views') }}</div><div class="kpi-value">{{ $number($report->pageviews()) }}</div><div class="kpi-note">{{ __(':n per visitor', ['n' => $report->visitors() ? number_format($report->pageviews() / $report->visitors(), 1, $de ? ',' : '.', '') : 0]) }}</div></div>
        <div class="kpi"><div class="kpi-label">{{ __('Reading time') }}</div><div class="kpi-value">{{ $duration($report->averageDuration()) }}</div><div class="kpi-note">{{ __('active time per page view') }}</div></div>
        <div class="kpi"><div class="kpi-label">{{ __('Returning') }}</div><div class="kpi-value">{{ $number($report->returning()) }}</div><div class="kpi-note">{{ __('only visitors with the statistics cookie (:share %)', ['share' => round($report->consentShare() * 100)]) }}</div></div>
    </section>

    <section class="night window" aria-label="{{ __('Visitors per day') }}">
        <div class="window-bar"><span>stats/visitors-per-day · {{ $period }}</span><span class="grow"></span><span>{{ __('Today is the bright bar.') }}</span></div>
        <div class="window-body">
            <h2 class="h2">{{ __('Visitors per day') }}</h2>
            <div wire:key="bars-{{ $period }}"><x-wake::chart.bars :series="$report->series()" :label="__('Visitors per day')" /></div>
        </div>
    </section>

    <section class="card">
        <div class="card-head"><h2 class="h2">{{ __('Pages') }}</h2><span class="muted" style="font-size:13px">{{ __('Click a page to see its wake.') }}</span></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('Page') }}</th><th class="num">{{ __('Visitors') }}</th><th class="num">{{ __('Page views') }}</th><th class="num">{{ __('Reading time') }}</th><th class="num">{{ __('Read to the end') }}</th></tr></thead>
                <tbody>
                @forelse ($report->pages(25) as $page)
                    <tr class="link">
                        <td><a href="{{ route('wake.stats.journey', ['path' => $page['path'], 'period' => $period]) }}" style="text-decoration:none"><b>{{ $page['entry']?->title() ?? $page['path'] }}</b><br><span class="muted mono" style="font-size:12px">{{ $page['path'] }}</span></a></td>
                        <td class="num">{{ $number($page['visitors']) }}</td>
                        <td class="num">{{ $number($page['pageviews']) }}</td>
                        <td class="num">{{ $duration($page['duration']) }}</td>
                        <td class="num">{{ $page['read_rate'] === null ? '–' : round($page['read_rate'] * 100).' %' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">{{ __('No visits yet in this period.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid grid-3">
        <div class="card"><h2 class="h2">{{ __('Sources') }}</h2><x-wake::top-list :items="$report->top('referrers', 8)" />
            @if ($report->top('campaigns'))<h2 class="h2" style="margin-top:8px">{{ __('Campaigns') }}</h2><x-wake::top-list :items="$report->top('campaigns', 5)" />@endif
        </div>
        <livewire:wake.live-visitors />
        <div class="card night">
            <h2 class="h2">{{ __('AI is reading along') }}</h2>
            <div style="display:flex;align-items:baseline;gap:10px"><span class="kpi-value">{{ $number($ai['total']) }}</span><span class="muted">{{ __('visits by AI crawlers') }}</span></div>
            @forelse (array_slice($ai['bots'], 0, 6, true) as $bot => $count)
                <div style="display:flex;justify-content:space-between;font-size:14px"><span>{{ $bot }}</span><span class="mono">{{ $count }}</span></div>
            @empty
                <p class="muted" style="margin:0">{{ __('No AI crawler has visited in this period.') }}</p>
            @endforelse
        </div>
    </section>

    <section class="grid grid-3">
        <div class="card"><h2 class="h2">{{ __('Countries') }}</h2><x-wake::top-list :items="$countries" :labels="$countryNames" :empty="is_file((string) config('wake.tracking.geo')) ? null : __('Add a country database to see countries.')" /></div>
        <div class="card"><h2 class="h2">{{ __('Devices') }}</h2><x-wake::top-list :items="$report->top('devices')" :labels="['desktop' => __('Desktop'), 'mobile' => __('Mobile'), 'tablet' => __('Tablet')]" />
            <h2 class="h2" style="margin-top:8px">{{ __('Languages') }}</h2><x-wake::top-list :items="$report->top('languages', 5)" />
        </div>
        <div class="card"><h2 class="h2">{{ __('Events') }}</h2>
            <x-wake::top-list :items="collect($report->events())->map(fn ($e) => $e['count'])->all()" :empty="__('No events yet. Wake counts external links, downloads and forms by itself.')" />
            <h2 class="h2" style="margin-top:8px">{{ __('Browsers') }}</h2><x-wake::top-list :items="$report->top('browsers', 5)" />
        </div>
    </section>
</div>

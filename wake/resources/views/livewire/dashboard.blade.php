@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 11 ? __('Good morning, :name') : ($hour < 18 ? __('Hello, :name') : __('Good evening, :name'));
    $name = explode(' ', auth()->user()->name())[0];
    $number = fn ($n) => number_format($n, 0, app()->getLocale() === 'de' ? ',' : '.', app()->getLocale() === 'de' ? '.' : ',');
    $duration = fn (int $s) => $s >= 60 ? intdiv($s, 60).' min '.str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT).' s' : $s.' s';
    $delta = function (?int $d) {
        if ($d === null) return '';
        return '<span class="delta'.($d < 0 ? ' down' : '').'">'.($d > 0 ? '+' : '').$d.' %</span>';
    };
@endphp
<div style="display:flex;flex-direction:column;gap:20px">
    <header class="page-head">
        <div>
            <div class="eyebrow">{{ now()->locale(app()->getLocale())->isoFormat('dd, D. MMMM · H:mm') }}</div>
            <h1 class="title">{{ str_replace(':name', $name, $greeting) }}</h1>
            <p class="lead">
                @if ($insight)
                    {{ $insight }}
                @elseif ($live['visitors'] > 0)
                    {!! __('Calm seas today. :count are on board right now.', ['count' => '<span class="mark">'.trans_choice(':count person|:count people', $live['visitors']).'</span>']) !!}
                @else
                    {{ __('Calm seas today.') }}
                @endif
            </p>
        </div>
        <div class="actions">
            <div class="segmented" role="group" aria-label="{{ __('Period') }}">
                @foreach (['7d' => __('7 days'), '30d' => __('30 days'), '90d' => __('90 days')] as $key => $label)
                    <button type="button" wire:click="setPeriod('{{ $key }}')" @class(['on' => $period === $key])>{{ $label }}</button>
                @endforeach
            </div>
            @if (true)
                <a class="btn btn-primary" href="{{ route('wake.entries.create', ['type' => 'post']) }}"><x-wake::icon name="plus" />{{ __('New post') }}</a>
            @endif
        </div>
    </header>

    @if (auth()->user()->can('stats.view'))
    <section class="grid grid-4" aria-label="{{ __('Key figures') }}">
        <div class="card">
            <div class="kpi-label">{{ __('Visitors') }} {!! $delta($deltas['visitors']) !!}</div>
            <div class="kpi-value">{{ $number($report->visitors()) }}</div>
            <div class="kpi-note">{{ __('added up per day, without cookies') }}</div>
        </div>
        <div class="card">
            <div class="kpi-label">{{ __('Page views') }} {!! $delta($deltas['pageviews']) !!}</div>
            <div class="kpi-value">{{ $number($report->pageviews()) }}</div>
            <div class="kpi-note">{{ __('previous period: :n', ['n' => $number($previous->pageviews())]) }}</div>
        </div>
        <div class="card">
            <div class="kpi-label">{{ __('Reading time') }} {!! $delta($deltas['duration']) !!}</div>
            <div class="kpi-value">{{ $duration($report->averageDuration()) }}</div>
            <div class="kpi-note">{{ __('active time per page view') }}</div>
        </div>
        <div class="card">
            <div class="kpi-label">{{ __('With consent') }}</div>
            <div class="kpi-value">{{ round($report->consentShare() * 100) }} %</div>
            <div class="kpi-note">{{ __('counted with the statistics cookie') }}</div>
        </div>
    </section>

    <section class="night" aria-label="{{ __('Visitors and page views') }}">
        <div class="waves" aria-hidden="true">
            <svg viewBox="0 0 2000 90" preserveAspectRatio="none"><path d="M0 50 Q 125 20 250 50 T 500 50 T 750 50 T 1000 50 T 1250 50 T 1500 50 T 1750 50 T 2000 50" fill="none" stroke="var(--accent)" stroke-opacity=".16" stroke-width="1.4"/></svg>
            <svg viewBox="0 0 2000 90" preserveAspectRatio="none"><path d="M0 60 Q 100 35 200 60 T 400 60 T 600 60 T 800 60 T 1000 60 T 1200 60 T 1400 60 T 1600 60 T 1800 60 T 2000 60" fill="none" stroke="#fff" stroke-opacity=".07" stroke-width="1.2"/></svg>
        </div>
        <div style="position:relative;display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;margin-bottom:22px">
            <h2 class="h2">{{ __('Visitors and page views') }}</h2>
            <div class="muted" style="display:flex;gap:18px;font-size:13px">
                <span style="display:flex;align-items:center;gap:7px"><span style="width:16px;height:3px;border-radius:2px;background:var(--accent);box-shadow:0 0 8px var(--glow)"></span>{{ __('Visitors') }}</span>
                <span style="display:flex;align-items:center;gap:7px"><span style="width:16px;border-top:2px dashed #8a8a8a"></span>{{ __('Page views') }}</span>
            </div>
        </div>
        <div style="position:relative" wire:key="chart-{{ $period }}">
            <x-wake::chart.line :series="$report->series()" :label="__('Visitors and page views per day')" />
        </div>
    </section>

    <section class="grid grid-3">
        <div class="card">
            <div class="card-head"><h2 class="h2">{{ __('Most read') }}</h2>@if (Route::has('wake.stats'))<a class="muted" href="{{ route('wake.stats') }}" style="font-size:13px">{{ __('All') }} →</a>@endif</div>
            @forelse ($report->pages(5) as $page)
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:baseline">
                    <span style="min-width:0">
                        <span style="display:block;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $page['entry']?->title() ?? $page['path'] }}</span>
                        <span class="muted" style="font-size:12px">
                            {{ $page['read_rate'] !== null ? __(':rate % read to the end', ['rate' => round($page['read_rate'] * 100)]) : $page['path'] }}
                        </span>
                    </span>
                    <span class="mono">{{ $number($page['visitors']) }}</span>
                </div>
            @empty
                <p class="muted" style="margin:0">{{ __('No visits yet in this period.') }}</p>
            @endforelse
        </div>

        <div class="card">
            <div class="card-head"><h2 class="h2">{{ __('Sources') }}</h2></div>
            @php($sources = $report->top('referrers', 6))
            @php($maxSource = max([1, ...array_values($sources)]))
            @forelse ($sources as $source => $count)
                <div style="display:grid;gap:6px">
                    <div style="display:flex;justify-content:space-between;font-size:14px"><span>{{ $source === '' ? __('Direct') : $source }}</span><span class="mono">{{ $number($count) }}</span></div>
                    <div style="height:6px;border-radius:3px;background:var(--hover);overflow:hidden"><div style="height:100%;width:{{ round($count / $maxSource * 100) }}%;background:var(--night);border-radius:3px;transform-origin:left;animation:grow .9s ease-out both"></div></div>
                </div>
            @empty
                <p class="muted" style="margin:0">{{ __('No visits yet in this period.') }}</p>
            @endforelse
        </div>

        <livewire:wake.live-visitors />
    </section>

    @endif

    <section class="grid grid-3">
        @foreach ([[__('Drafts'), $drafts, 'draft'], [__('Scheduled'), $scheduled, 'scheduled'], [__('Recently published'), $latest, 'published']] as [$heading, $posts, $status])
            <div class="card">
                <div class="card-head"><h2 class="h2">{{ $heading }}</h2><span class="status {{ $status }}"></span></div>
                @forelse ($posts as $post)
                    <div style="display:flex;justify-content:space-between;gap:12px;font-size:14px">
                        @if (true)
                            <a href="{{ route('wake.entries.edit', ['type' => 'post', 'id' => $post->id()]) }}" style="text-decoration:none;font-weight:600">{{ $post->title() }}</a>
                        @else
                            <span style="font-weight:600">{{ $post->title() }}</span>
                        @endif
                        <span class="muted mono" style="font-size:12px;white-space:nowrap">{{ $post->date()?->locale(app()->getLocale())->isoFormat('D. MMM') }}</span>
                    </div>
                @empty
                    <p class="muted" style="margin:0">{{ __('Nothing here.') }}</p>
                @endforelse
            </div>
        @endforeach
    </section>
</div>

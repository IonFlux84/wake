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
    @unless (\Wake\Accounts\TwoFactor::isEnabled(auth()->user()))
        <a href="{{ route('wake.settings') }}#two-factor" class="flash"><x-wake::icon name="key" size="16" /><span class="grow">{{ __('Protect your account with two-factor sign-in: a stolen password alone is then not enough.') }}</span><span class="go">{{ __('Set up') }} →</span></a>
    @endunless
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
                    <button type="button" wire:click="setPeriod('{{ $key }}')" @class(['on' => $period === $key]) aria-pressed="{{ $period === $key ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
            @can('content.write')
                <a class="btn btn-primary" href="{{ route('wake.entries.create', ['type' => 'post']) }}"><x-wake::icon name="plus" />{{ __('New post') }}</a>
            @endcan
        </div>
    </header>

    @if (auth()->user()->can('stats.view'))
    <section class="kpis" aria-label="{{ __('Key figures') }}">
        <div class="kpi">
            <div class="kpi-label">{{ __('Visitors') }} {!! $delta($deltas['visitors']) !!}</div>
            <div class="kpi-value">{{ $number($report->visitors()) }}</div>
            <div class="kpi-note">{{ __('added up per day, without cookies') }}</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">{{ __('Page views') }} {!! $delta($deltas['pageviews']) !!}</div>
            <div class="kpi-value">{{ $number($report->pageviews()) }}</div>
            <div class="kpi-note">{{ __('previous period: :n', ['n' => $number($previous->pageviews())]) }}</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">{{ __('Reading time') }} {!! $delta($deltas['duration']) !!}</div>
            <div class="kpi-value">{{ $duration($report->averageDuration()) }}</div>
            <div class="kpi-note">{{ __('active time per page view') }}</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">{{ __('With consent') }}</div>
            <div class="kpi-value">{{ round($report->consentShare() * 100) }} %</div>
            <div class="kpi-note">{{ __('counted with the statistics cookie') }}</div>
        </div>
    </section>

    <section class="night window" aria-label="{{ __('Visitors and page views') }}">
        <div class="window-bar"><span>stats/visitors · {{ $period }}</span><span class="grow"></span>
            <span class="legend"><span><i class="l1"></i>{{ __('Visitors') }}</span><span><i class="l2"></i>{{ __('Page views') }}</span></span></div>
        <div class="window-body">
            <h2 class="h2">{{ __('Visitors and page views') }}</h2>
            <div wire:key="chart-{{ $period }}"><x-wake::chart.line :series="$report->series()" :label="__('Visitors and page views per day')" /></div>
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
                    <div class="bar"><i style="width:{{ round($count / $maxSource * 100) }}%"></i></div>
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
                        @can('content.write')
                            <a href="{{ route('wake.entries.edit', ['type' => 'post', 'id' => $post->id()]) }}" style="text-decoration:none;font-weight:600">{{ $post->title() }}</a>
                        @else
                            <span style="font-weight:600">{{ $post->title() }}</span>
                        @endcan
                        <span class="muted mono" style="font-size:12px;white-space:nowrap">{{ $post->date()?->locale(app()->getLocale())->isoFormat('D. MMM') }}</span>
                    </div>
                @empty
                    <p class="none">{{ ['draft' => __('No draft open.'), 'scheduled' => __('Nothing scheduled.'), 'published' => __('Nothing published yet.')][$status] }}</p>
                @endforelse
            </div>
        @endforeach
    </section>
</div>

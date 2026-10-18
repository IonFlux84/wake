@php
    $total = max(1, $summary['total']);
    $rate = fn (string $category) => $summary['total'] ? round(($summary['categories'][$category] ?? 0) / $total * 100) : 0;
    $languages = app(\Wake\Localization\Languages::class);
    $preview = $texts[$language] ?? reset($texts);
@endphp
<div style="display:flex;flex-direction:column;gap:20px">
    <header class="page-head">
        <div>
            <div class="eyebrow">{{ __('Last 30 days') }} · {{ __('Banner version :v', ['v' => $version]) }}</div>
            <h1 class="title">{{ __('Consent') }}</h1>
            <p class="lead">{{ $bannerNeeded ? __('The banner is shown to new visitors.') : __('No banner needed: nothing on this site requires consent up front. Embeds ask when clicked.') }}</p>
        </div>
    </header>

    <section class="grid grid-4">
        <div class="card"><div class="kpi-label">{{ __('Decisions') }}</div><div class="kpi-value">{{ $summary['total'] }}</div><div class="kpi-note">{{ __('anonymous records') }}</div></div>
        <div class="card"><div class="kpi-label">{{ __('Accepted all') }}</div><div class="kpi-value">{{ $summary['total'] ? round($summary['actions']['accept'] / $total * 100) : 0 }} %</div></div>
        <div class="card"><div class="kpi-label">{{ __('Declined') }}</div><div class="kpi-value">{{ $summary['total'] ? round($summary['actions']['decline'] / $total * 100) : 0 }} %</div></div>
        <div class="card"><div class="kpi-label">{{ __('Own choice') }}</div><div class="kpi-value">{{ $summary['total'] ? round($summary['actions']['custom'] / $total * 100) : 0 }} %</div></div>
    </section>

    <section class="grid grid-2">
        <form class="card form" wire:submit="save">
            <h2 class="h2">{{ __('Banner') }}</h2>
            <label class="check"><input type="checkbox" wire:model.live="statistics"> {{ __('Offer the statistics cookie (returning visitors)') }}</label>
            <label class="check"><input type="checkbox" wire:model.live="marketing"> {{ __('Ask for marketing scripts') }}</label>
            <label class="check"><input type="checkbox" wire:model.live="always"> {{ __('Always show the banner') }}</label>
            <div class="grid grid-2" style="gap:12px">
                <div class="field"><label for="position">{{ __('Position') }}</label>
                    <select id="position" class="select" wire:model.live="position"><option value="bottom">{{ __('Bottom') }}</option><option value="center">{{ __('Centre') }}</option></select></div>
                <div class="field"><label for="privacy">{{ __('Privacy policy') }}</label><input id="privacy" class="input" wire:model="privacyUrl" placeholder="/privacy"></div>
            </div>
            @if ($languages->isMultilingual())
                <div class="segmented" role="group" aria-label="{{ __('Language') }}">
                    @foreach ($languages->all() as $code => $name)
                        <button type="button" wire:click="$set('language', '{{ $code }}')" @class(['on' => $language === $code])>{{ $name }}</button>
                    @endforeach
                </div>
            @endif
            @foreach ($texts as $code => $values)
                <div @if ($code !== $language) hidden @endif style="display:grid;gap:12px">
                    <div class="field"><label>{{ __('Title') }}</label><input class="input" wire:model.live.debounce.400ms="texts.{{ $code }}.title"></div>
                    <div class="field"><label>{{ __('Text') }}</label><textarea class="textarea" wire:model.live.debounce.400ms="texts.{{ $code }}.text"></textarea></div>
                    <div class="grid grid-2" style="gap:12px">
                        <div class="field"><label>{{ __('Accept button') }}</label><input class="input" wire:model.live.debounce.400ms="texts.{{ $code }}.accept"></div>
                        <div class="field"><label>{{ __('Decline button') }}</label><input class="input" wire:model.live.debounce.400ms="texts.{{ $code }}.decline"></div>
                    </div>
                </div>
            @endforeach
            @error('texts.*')<span class="error">{{ $message }}</span>@enderror
            <div class="actions"><button class="btn btn-primary" type="submit">{{ __('Save') }}</button><span class="muted" style="font-size:13px">{{ __('Changing the texts asks every visitor again.') }}</span></div>
        </form>

        <div class="card" style="background:var(--paper-2)">
            <h2 class="h2">{{ __('Preview') }}</h2>
            <div style="position:relative;flex:1;min-height:320px;border-radius:14px;border:1px solid var(--line);background:repeating-linear-gradient(0deg,#fff 0 22px,#f4f4f2 22px 23px);overflow:hidden">
                <div style="position:absolute;left:16px;right:16px;{{ $position === 'center' ? 'top:50%;transform:translateY(-50%)' : 'bottom:16px' }};padding:18px;border-radius:14px;background:#0a0a0a;color:#fff;animation:slideup .5s ease-out both">
                    <strong style="display:block;font-size:16px;margin-bottom:6px">{{ $preview['title'] ?? '' }}</strong>
                    <p style="margin:0 0 12px;font-size:14px;opacity:.85">{{ $preview['text'] ?? '' }}</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <span style="text-align:center;padding:10px;border-radius:9px;background:#fff;color:#0a0a0a;font-weight:700;font-size:14px">{{ $preview['decline'] ?? '' }}</span>
                        <span style="text-align:center;padding:10px;border-radius:9px;background:#fff;color:#0a0a0a;font-weight:700;font-size:14px">{{ $preview['accept'] ?? '' }}</span>
                    </div>
                </div>
            </div>
            <p class="muted" style="margin:0;font-size:13px">{{ __('Decline and accept always look the same.') }}</p>
        </div>
    </section>

    <section class="grid grid-2">
        <div class="card">
            <h2 class="h2">{{ __('Agreement by category') }}</h2>
            @foreach (['statistics' => __('Statistics'), 'media' => __('External media'), 'marketing' => __('Marketing')] as $key => $label)
                <div style="display:grid;gap:6px">
                    <div style="display:flex;justify-content:space-between;font-size:14px"><span>{{ $label }}</span><span class="mono">{{ $rate($key) }} %</span></div>
                    <div style="height:6px;border-radius:3px;background:var(--hover);overflow:hidden"><div style="height:100%;width:{{ $rate($key) }}%;background:var(--night);border-radius:3px"></div></div>
                </div>
            @endforeach
            <h2 class="h2" style="margin-top:8px">{{ __('Embedded services') }}</h2>
            @forelse ($services as $key)
                <span class="badge">{{ \Wake\Consent\Services::get($key)['name'] }}</span>
            @empty
                <p class="muted" style="margin:0">{{ __('No external services embedded.') }}</p>
            @endforelse
        </div>
        <div class="card">
            <h2 class="h2">{{ __('Latest records') }}</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Choice') }}</th><th>{{ __('Version') }}</th></tr></thead>
                    <tbody>
                    @forelse ($summary['recent'] as $record)
                        <tr>
                            <td class="mono" style="font-size:12px">{{ \Carbon\CarbonImmutable::parse($record['time'])->setTimezone(config('app.timezone'))->format('d.m. H:i') }}<br><span class="muted">{{ substr($record['id'], 0, 8) }}…</span></td>
                            <td>{{ implode(', ', array_map(fn ($c) => ['statistics' => __('Statistics'), 'media' => __('External media'), 'marketing' => __('Marketing')][$c] ?? $c, array_diff($record['categories'], ['necessary']))) ?: __('Necessary only') }}</td>
                            <td class="mono">{{ $record['version'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">{{ __('No decisions yet.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

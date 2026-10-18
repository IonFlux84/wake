@php
    $scopeLabels = [
        'stats:read' => __('Read statistics'),
        'content:read' => __('Read content'),
        'content:write' => __('Write drafts'),
        'content:publish' => __('Publish'),
    ];
@endphp
<div style="display:flex;flex-direction:column;gap:20px">
    <header class="page-head">
        <div>
            <div class="eyebrow">MCP · {{ __('Model Context Protocol') }}</div>
            <h1 class="title">{{ __('Assistants') }}</h1>
            <p class="lead">{{ __('Connect Claude or another AI assistant. It can answer questions about your statistics and, if you allow it, read and write posts.') }}</p>
        </div>
    </header>

    @if ($plain)
        <section class="night" role="status">
            <h2 class="h2" style="margin-bottom:10px">{{ __('Your new token') }}</h2>
            <p class="muted" style="margin:0 0 12px">{{ __('Copy it now. Wake only stores a fingerprint and cannot show it again.') }}</p>
            <div x-data="{ copied: false }" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <div class="term" style="flex:1;min-width:0"><div class="term-bar">token · {{ __('shown once') }}</div><pre class="term-body" style="color:#fff;white-space:pre-wrap;word-break:break-all">{{ $plain }}</pre></div>
                <button type="button" class="btn btn-primary btn-small" @click="navigator.clipboard.writeText(@js($plain)); copied = true"><span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))"></span></button>
            </div>
        </section>
    @endif

    <section class="grid grid-2">
        <form class="card form" wire:submit="create">
            <h2 class="h2">{{ __('New token') }}</h2>
            <div class="field">
                <label for="token-name">{{ __('Name') }}</label>
                <input id="token-name" class="input" wire:model="name" placeholder="{{ __('e.g. Claude on my laptop') }}">
                @error('name')<span class="error">{{ $message }}</span>@enderror
            </div>
            <fieldset style="border:0;padding:0;margin:0;display:grid;gap:10px">
                <legend class="label" style="margin-bottom:8px">{{ __('What may it do?') }}</legend>
                @foreach ($allowed as $scope)
                    <label class="check"><input type="checkbox" value="{{ $scope }}" wire:model="scopes"> {{ $scopeLabels[$scope] }} <span class="badge">{{ $scope }}</span></label>
                @endforeach
                @error('scopes')<span class="error">{{ $message }}</span>@enderror
            </fieldset>
            <div class="field">
                <label for="token-days">{{ __('Valid for') }}</label>
                <select id="token-days" class="input" wire:model="days">
                    <option value="30">{{ __('30 days') }}</option>
                    <option value="90">{{ __('90 days') }}</option>
                    <option value="365">{{ __('1 year') }}</option>
                    <option value="never">{{ __('No end (not recommended)') }}</option>
                </select>
            </div>
            <p class="muted" style="margin:0;font-size:13px">{{ __('Assistants never delete anything. New posts always start as drafts.') }}</p>
            <div class="actions"><button class="btn btn-primary" type="submit"><x-wake::icon name="key" />{{ __('Create token') }}</button></div>
        </form>

        <div class="card">
            <h2 class="h2">{{ __('Connect') }}</h2>
            <p style="margin:0">{{ __('Address of the MCP server:') }}</p>
            <code class="codebox">{{ $endpoint }}</code>
            <p style="margin:0">{{ __('In claude.ai (Settings → Connectors → Add custom connector), enter only this address. You then confirm the connection here in the panel; no token needed.') }}</p>
            <p style="margin:0">{{ __('For Claude Code, run in a terminal:') }}</p>
            <div class="term"><div class="term-bar">terminal</div><pre class="term-body" style="color:#fff">claude mcp add --transport http wake {{ $endpoint }} \
  --header "Authorization: Bearer wake_…"</pre></div>
            <p class="muted" style="margin:0;font-size:13px">{{ __('Other assistants need the same two things: the address and the token as a Bearer header.') }}</p>
        </div>
    </section>

    <section class="card">
        <h2 class="h2">{{ __('Your tokens') }}</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Rights') }}</th><th>{{ __('Last used') }}</th><th>{{ __('Valid until') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($tokens as $token)
                    <tr wire:key="token-{{ $token['id'] }}">
                        <td><b>{{ $token['name'] }}</b><br><span class="muted mono" style="font-size:12px">{{ __('created :date', ['date' => \Carbon\CarbonImmutable::parse($token['created_at'])->locale(app()->getLocale())->isoFormat('D. MMM YYYY')]) }}</span></td>
                        <td>@foreach ($token['scopes'] as $scope)<span class="badge" style="margin:2px">{{ $scopeLabels[$scope] ?? $scope }}</span>@endforeach</td>
                        <td class="mono" style="font-size:13px">{{ $token['last_used_at'] ? \Carbon\CarbonImmutable::parse($token['last_used_at'])->locale(app()->getLocale())->diffForHumans() : __('never') }}</td>
                        <td class="mono" style="font-size:13px">@if (empty($token['expires_at'])){{ __('no end') }}@elseif (\Wake\Accounts\Tokens::isExpired($token))<span class="badge">{{ __('expired') }}</span>@else{{ \Carbon\CarbonImmutable::parse($token['expires_at'])->locale(app()->getLocale())->isoFormat('D. MMM YYYY') }}@endif</td>
                        <td style="text-align:right"><button type="button" class="btn btn-small btn-danger" wire:click="revoke('{{ $token['id'] }}')" wire:confirm="{{ __('Revoke this token? Assistants using it lose access immediately.') }}">{{ __('Revoke') }}</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">{{ __('No tokens yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

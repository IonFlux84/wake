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
                <code style="font:500 14px var(--mono);background:#1a1a1a;padding:10px 12px;border-radius:10px;color:var(--accent);word-break:break-all;flex:1">{{ $plain }}</code>
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
            <p class="muted" style="margin:0;font-size:13px">{{ __('Assistants never delete anything. New posts always start as drafts.') }}</p>
            <div class="actions"><button class="btn btn-primary" type="submit"><x-wake::icon name="key" />{{ __('Create token') }}</button></div>
        </form>

        <div class="card">
            <h2 class="h2">{{ __('Connect') }}</h2>
            <p style="margin:0">{{ __('Address of the MCP server:') }}</p>
            <code style="font:500 13px var(--mono);background:var(--hover);padding:10px 12px;border-radius:10px;word-break:break-all">{{ $endpoint }}</code>
            <p style="margin:0">{{ __('For Claude Code, run in a terminal:') }}</p>
            <pre style="margin:0;font:500 12px/1.6 var(--mono);background:var(--night);color:#fff;padding:12px 14px;border-radius:12px;overflow-x:auto">claude mcp add --transport http wake {{ $endpoint }} \
  --header "Authorization: Bearer wake_…"</pre>
            <p class="muted" style="margin:0;font-size:13px">{{ __('Other assistants need the same two things: the address and the token as a Bearer header.') }}</p>
        </div>
    </section>

    <section class="card">
        <h2 class="h2">{{ __('Your tokens') }}</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Rights') }}</th><th>{{ __('Last used') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($tokens as $token)
                    <tr wire:key="token-{{ $token['id'] }}">
                        <td><b>{{ $token['name'] }}</b><br><span class="muted mono" style="font-size:12px">{{ __('created :date', ['date' => \Carbon\CarbonImmutable::parse($token['created_at'])->locale(app()->getLocale())->isoFormat('D. MMM YYYY')]) }}</span></td>
                        <td>@foreach ($token['scopes'] as $scope)<span class="badge" style="margin:2px">{{ $scopeLabels[$scope] ?? $scope }}</span>@endforeach</td>
                        <td class="mono" style="font-size:13px">{{ $token['last_used_at'] ? \Carbon\CarbonImmutable::parse($token['last_used_at'])->locale(app()->getLocale())->diffForHumans() : __('never') }}</td>
                        <td style="text-align:right"><button type="button" class="btn btn-small btn-danger" wire:click="revoke('{{ $token['id'] }}')" wire:confirm="{{ __('Revoke this token? Assistants using it lose access immediately.') }}">{{ __('Revoke') }}</button></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">{{ __('No tokens yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

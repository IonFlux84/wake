@php
    $pick = fn ($value) => is_array($value) ? ($value[app()->getLocale()] ?? $value['en'] ?? reset($value)) : $value;
    $current = $themes[$active] ?? null;
@endphp
<div style="display:flex;flex-direction:column;gap:20px">
    <header class="page-head">
        <div>
            <div class="eyebrow">{{ trans_choice(':count theme|:count themes', count($themes)) }}</div>
            <h1 class="title">{{ __('Themes') }}</h1>
            <p class="lead">{{ __('A theme decides how your website looks. Your content stays the same.') }}</p>
        </div>
        <label class="btn" style="cursor:pointer"><x-wake::icon name="upload" />{{ __('Install a theme (ZIP)') }}<input type="file" accept=".zip" wire:model="zip" hidden></label>
    </header>
    @if (session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif
    @error('zip')<div class="flash error" role="alert">{{ $message }}</div>@enderror
    <span wire:loading wire:target="zip" class="muted">{{ __('Installing …') }}</span>

    <section class="grid grid-3">
        @foreach ($themes as $folder => $theme)
            <div class="card" style="padding:0;overflow:hidden;gap:0;{{ $folder === $active ? 'border-color:var(--ink);box-shadow:0 0 0 2px var(--accent)' : '' }}" wire:key="theme-{{ $folder }}">
                @if ($screenshots[$folder])
                    <img src="{{ $screenshots[$folder] }}" alt="" style="width:100%;aspect-ratio:16/10;object-fit:cover;object-position:top;border-bottom:1px solid var(--line)">
                @else
                    <div style="aspect-ratio:16/10;background:var(--night);display:flex;align-items:center;justify-content:center;color:var(--accent);font:800 28px var(--display, var(--sans))">{{ $theme->name() }}</div>
                @endif
                <div style="padding:18px 20px;display:flex;flex-direction:column;gap:10px;flex:1">
                    <div class="card-head">
                        <h2 class="h2">{{ $theme->name() }}</h2>
                        @if ($folder === $active)<span class="badge" style="background:var(--accent);color:var(--on-accent)">{{ __('Active') }}</span>@endif
                    </div>
                    <p class="muted" style="margin:0;font-size:14px">{{ $theme->description() ?: '—' }}</p>
                    <p class="muted" style="margin:0;font-size:12px">{{ collect([$theme->version() ? 'v'.$theme->version() : null, $theme->author(), 'site/themes/'.$folder])->filter()->implode(' · ') }}</p>
                    <div class="actions" style="margin-top:auto">
                        @if ($folder !== $active)
                            <button type="button" class="btn btn-small btn-primary" wire:click="activate('{{ $folder }}')">{{ __('Activate') }}</button>
                        @endif
                        <button type="button" class="btn btn-small" wire:click="duplicate('{{ $folder }}')" title="{{ __('Copy to change it safely') }}">{{ __('Duplicate') }}</button>
                        @if ($folder !== $active)
                            <button type="button" class="btn btn-small btn-danger" wire:click="remove('{{ $folder }}')" wire:confirm="{{ __('Move this theme to the trash?') }}" aria-label="{{ __('Remove') }}"><x-wake::icon name="trash" /></button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </section>

    @if ($current && $current->options())
        <form class="card form" wire:submit="saveOptions">
            <h2 class="h2">{{ __('Settings of “:name”', ['name' => $current->name()]) }}</h2>
            <div class="grid grid-2" style="gap:16px">
                @foreach ($current->options() as $key => $option)
                    @php($type = $option['type'] ?? 'text')
                    <div class="field">
                        <label for="o-{{ $key }}">{{ $pick($option['label'] ?? $key) }}</label>
                        @if ($type === 'color')
                            <div style="display:flex;gap:8px;align-items:center">
                                <input type="color" wire:model.live="options.{{ $key }}" style="width:44px;height:44px;border:1px solid var(--line);border-radius:10px;padding:2px;background:var(--paper)" aria-label="{{ $pick($option['label'] ?? $key) }}">
                                <input id="o-{{ $key }}" class="input mono" style="width:140px" wire:model.live.debounce.400ms="options.{{ $key }}">
                            </div>
                        @elseif ($type === 'toggle')
                            <label class="check"><input id="o-{{ $key }}" type="checkbox" wire:model="options.{{ $key }}"> {{ __('Yes') }}</label>
                        @elseif ($type === 'select')
                            <select id="o-{{ $key }}" class="select" wire:model="options.{{ $key }}">@foreach ((array) ($option['options'] ?? []) as $value => $text)<option value="{{ $value }}">{{ $pick($text) }}</option>@endforeach</select>
                        @elseif ($type === 'textarea')
                            <textarea id="o-{{ $key }}" class="textarea" wire:model="options.{{ $key }}" placeholder="{{ $pick($option['placeholder'] ?? '') }}"></textarea>
                        @else
                            <input id="o-{{ $key }}" class="input" wire:model="options.{{ $key }}" placeholder="{{ $pick($option['placeholder'] ?? '') }}">
                        @endif
                        @error('options.'.$key)<span class="error">{{ $message }}</span>@enderror
                    </div>
                @endforeach
            </div>
            <div class="actions"><button type="submit" class="btn btn-primary">{{ __('Save theme settings') }}</button></div>
        </form>
    @endif
</div>

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
            <div class="card" style="padding:0;overflow:hidden;gap:0;{{ $folder === $active ? 'border-color:var(--text);outline:1px solid var(--text)' : '' }}" wire:key="theme-{{ $folder }}">
                @if ($screenshots[$folder])
                    <img src="{{ $screenshots[$folder] }}" alt="" style="width:100%;aspect-ratio:16/10;object-fit:cover;object-position:top;border-bottom:1px solid var(--line)">
                @else
                    <div class="dotgrid-ink" style="aspect-ratio:16/10;background-color:var(--night);display:flex;align-items:center;justify-content:center;color:#fff;font:600 24px var(--sans);letter-spacing:-.03em">{{ $theme->name() }}</div>
                @endif
                <div style="padding:18px 20px;display:flex;flex-direction:column;gap:10px;flex:1">
                    <div class="card-head">
                        <h2 class="h2">{{ $theme->name() }}</h2>
                        @if ($folder === $active)<span class="badge on">{{ __('Active') }}</span>@endif
                    </div>
                    <p class="muted" style="margin:0;font-size:14px">{{ $theme->description() ?: '—' }}</p>
                    <p class="muted mono" style="margin:0;font-size:12px">{{ collect([$theme->version() ? 'v'.$theme->version() : null, $theme->author(), 'themes/'.$folder])->filter()->implode(' · ') }}</p>
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
                    @include('wake::livewire.partials.option', ['key' => $key, 'option' => $option, 'model' => 'options.'.$key])
                @endforeach
            </div>
            <div class="actions"><button type="submit" class="btn btn-primary">{{ __('Save theme settings') }}</button></div>
        </form>
    @endif
</div>

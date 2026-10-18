@php($pick = fn ($value) => is_array($value) ? ($value[app()->getLocale()] ?? $value['en'] ?? reset($value)) : $value)
<div style="display:flex;flex-direction:column;gap:20px">
    <header class="page-head">
        <div>
            <div class="eyebrow">{{ trans_choice(':count plugin|:count plugins', count($plugins)) }}</div>
            <h1 class="title">{{ __('Plugins') }}</h1>
            <p class="lead">{{ __('Plugins add cards, field types, addresses or tasks to Wake. Copy a plugin folder into plugins to install it.') }}</p>
        </div>
    </header>

    @if (session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif

    <p class="muted" role="note" style="margin:-8px 0 0;font-size:14px;display:flex;gap:8px;align-items:center"><x-wake::icon name="key" size="16" />{{ __('Plugins run with the same rights as Wake. Only install plugins whose source you trust.') }}</p>

    @forelse ($plugins as $folder => $info)
        @php($enabled = $manager->isEnabled($folder))
        @php($error = $manager->error($folder))
        <section class="card" wire:key="plugin-{{ $folder }}">
            <div class="card-head">
                <div>
                    <h2 class="h2">{{ $info->name() }} @if ($info->version())<span class="muted mono" style="font-size:13px">v{{ $info->version() }}</span>@endif</h2>
                    <div class="muted" style="font-size:13px">
                        <span class="mono">plugins/{{ $folder }}</span>
                        @if ($info->author()) · {{ $info->author() }}@endif
                        @if ($info->url()) · <a href="{{ $info->url() }}" target="_blank" rel="noopener">{{ __('Website') }}</a>@endif
                    </div>
                </div>
                <div class="actions">
                    @if ($error)
                        <span class="badge danger">{{ __('Error') }}</span>
                    @else
                        <span @class(['badge', 'on' => $enabled])>{{ $enabled ? __('On') : __('Off') }}</span>
                    @endif
                    <button type="button" class="btn btn-small" wire:click="toggle(@js($folder))">{{ $enabled ? __('Switch off') : __('Switch on') }}</button>
                </div>
            </div>
            @if ($info->description())<p style="margin:0">{{ $info->description() }}</p>@endif
            @if ($error)
                <div class="flash error" role="alert">{{ __('This plugin could not be started and is skipped:') }} <span class="mono" style="font-size:13px">{{ $error }}</span></div>
            @endif
            @if ($info->options())
                <form class="form" wire:submit="saveOptions(@js($folder))" style="border-top:1px solid var(--line);padding-top:16px">
                    <div class="grid grid-2" style="gap:16px">
                        @foreach ($info->options() as $key => $option)
                            @include('wake::livewire.partials.option', ['key' => $key, 'option' => $option, 'model' => 'options.'.$folder.'.'.$key])
                        @endforeach
                    </div>
                    <div class="actions"><button type="submit" class="btn">{{ __('Save settings') }}</button></div>
                </form>
            @endif
        </section>
    @empty
        <section class="card">
            <p class="muted" style="margin:0">{{ __('No plugins yet. Put a plugin folder into plugins, or write your own: see the guide in the documentation.') }}</p>
        </section>
    @endforelse
</div>

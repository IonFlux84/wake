<div style="display:flex;flex-direction:column;gap:20px">
    <header class="page-head">
        <div>
            <div class="eyebrow">{{ trans_choice(':count backup|:count backups', count($backups)) }}</div>
            <h1 class="title">{{ __('Backups') }}</h1>
            <p class="lead">{{ __('A backup is one ZIP file with your content, settings, themes, accounts, versions and consent records. Download it and keep it somewhere else.') }}</p>
        </div>
    </header>

    @if (session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif

    @if (! $available)
        <div class="flash error" role="alert">{{ __('Backups need the PHP extension zip, which is missing on this server.') }}</div>
    @else
        <section class="grid grid-2" style="gap:20px;align-items:start">
            <form class="card form" wire:submit="create">
                <h2 class="h2">{{ __('New backup') }}</h2>
                <label class="check"><input type="checkbox" wire:model="withStats"> {{ __('Include the statistics') }}</label>
                <p class="muted" style="font-size:13px;margin:0">{{ __('Not included: caches, the trash, resized images (Wake makes them again) and the .env file with the application key and mail password.') }}</p>
                <div class="actions"><button type="submit" class="btn btn-primary" wire:loading.attr="disabled"><x-wake::icon name="download" />{{ __('Write backup') }}</button><span wire:loading wire:target="create" class="muted">{{ __('Packing …') }}</span></div>
            </form>
            <div class="card form">
                <h2 class="h2">{{ __('Upload a backup') }}</h2>
                <div class="field">
                    <label for="backup-zip">{{ __('ZIP file') }}</label>
                    <input id="backup-zip" type="file" accept=".zip" wire:model="zip">
                    @error('zip')<span class="error">{{ $message }}</span>@enderror
                </div>
                <p class="muted" style="font-size:13px;margin:0">{{ __('Large backups are easier to upload by FTP into data/backups. They appear in the list below.') }}</p>
            </div>
        </section>

        <section class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>{{ __('File') }}</th><th>{{ __('Date') }}</th><th>{{ __('Size') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($backups as $backup)
                        <tr wire:key="{{ $backup['name'] }}">
                            <td class="mono" style="font-size:13px">{{ $backup['name'] }}</td>
                            <td style="white-space:nowrap">{{ \Carbon\CarbonImmutable::createFromTimestamp($backup['time'])->setTimezone(config('app.timezone'))->locale(app()->getLocale())->isoFormat('D. MMM YYYY, HH:mm') }}</td>
                            <td class="mono" style="font-size:13px;white-space:nowrap">{{ number_format($backup['size'] / 1048576, 1, ',', '.') }} MB</td>
                            <td>
                                <div class="actions" style="justify-content:flex-end">
                                    <a class="btn btn-small" href="{{ route('wake.backups.download', $backup['name']) }}"><x-wake::icon name="download" />{{ __('Download') }}</a>
                                    <button type="button" class="btn btn-small" wire:click="restore(@js($backup['name']))" wire:confirm="{{ __('Replace content, settings and accounts with this backup? The current state is saved as a backup first.') }}">{{ __('Restore') }}</button>
                                    <button type="button" class="btn btn-small btn-danger" wire:click="delete(@js($backup['name']))" wire:confirm="{{ __('Move this backup to the trash?') }}" aria-label="{{ __('Delete') }}"><x-wake::icon name="trash" /></button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">{{ __('No backup yet.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>

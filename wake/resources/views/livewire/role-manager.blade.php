<section class="card" style="gap:16px">
    <div class="card-head">
        <h2 class="h2">{{ __('Roles and rights') }}</h2>
        <span class="muted" style="font-size:13px">{{ __('Who may do what. The owner may always do everything.') }}</span>
    </div>
    @if (session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif
    @error('matrix')<div class="flash error" role="alert">{{ $message }}</div>@enderror

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th style="min-width:220px">{{ __('Right') }}</th>
                    @foreach ($roles as $key => $role)
                        <th style="text-align:center;min-width:120px">
                            @if ($role->isOwner())
                                {{ $role->label() }}
                            @else
                                <input class="input" style="min-height:34px;padding:4px 8px;font:600 12px var(--sans);text-align:center" wire:model="labels.{{ $key }}.{{ app()->getLocale() === 'de' ? 'de' : 'en' }}" aria-label="{{ __('Name of the role') }}">
                            @endif
                            <span style="display:block;margin-top:4px;font-weight:400;text-transform:none;letter-spacing:0">{{ trans_choice(':count member|:count members', $members[$key] ?? 0) }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($groups as $group => $keys)
                    <tr><td colspan="{{ count($roles) + 1 }}" class="label" style="padding-top:18px">{{ $group }}</td></tr>
                    @foreach ($keys as $capability)
                        <tr>
                            <td>{{ $capabilities[$capability] }}</td>
                            @foreach ($roles as $key => $role)
                                <td style="text-align:center">
                                    <input type="checkbox" value="{{ $capability }}" wire:model="matrix.{{ $key }}" @disabled($role->isOwner()) aria-label="{{ $role->label() }}: {{ $capabilities[$capability] }}" style="width:18px;height:18px;accent-color:var(--ink)">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
                <tr>
                    <td></td>
                    @foreach ($roles as $key => $role)
                        <td style="text-align:center">
                            @unless ($role->isOwner())
                                <button type="button" class="btn btn-small {{ $role->builtIn ? '' : 'btn-danger' }}" wire:click="deleteRole('{{ $key }}')"
                                    wire:confirm="{{ $role->builtIn ? __('Reset this role to its defaults?') : __('Remove this role?') }}">{{ $role->builtIn ? __('Reset') : __('Remove') }}</button>
                            @endunless
                        </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>

    <div class="actions" style="justify-content:space-between">
        <button type="button" class="btn btn-primary" wire:click="save">{{ __('Save roles') }}</button>
        <form class="actions" wire:submit="addRole">
            <input class="input" style="width:220px" wire:model="newRole" placeholder="{{ __('New role, e.g. Moderator') }}" aria-label="{{ __('New role') }}">
            <button type="submit" class="btn"><x-wake::icon name="plus" />{{ __('Add role') }}</button>
        </form>
    </div>
    @error('newRole')<span class="error">{{ $message }}</span>@enderror
</section>

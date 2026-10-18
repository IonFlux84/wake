<div style="display:flex;flex-direction:column;gap:20px">
    <header class="page-head">
        <div>
            <div class="eyebrow">{{ trans_choice(':count member|:count members', $accounts->count()) }}</div>
            <h1 class="title">{{ __('Team') }}</h1>
        </div>
    </header>
    @if (session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif
    @if (session('invite_link'))
        <div class="card" style="gap:8px">
            <span class="muted" style="font-size:13px">{{ __('If the mail does not arrive, send this link yourself. It works for 7 days.') }}</span>
            <code style="font:500 13px var(--mono);background:var(--hover);padding:10px 12px;border-radius:10px;word-break:break-all">{{ session('invite_link') }}</code>
        </div>
    @endif

    <section class="grid grid-2">
        <div class="card">
            <h2 class="h2">{{ __('Members') }}</h2>
            @foreach ($accounts as $account)
                <div style="display:flex;align-items:center;gap:12px" wire:key="member-{{ $account->id() }}">
                    <span class="avatar">{{ $account->initials() }}</span>
                    <span style="flex:1;min-width:0">
                        <b style="display:block">{{ $account->name() }} @if ($account->isPending())<span class="badge">{{ __('invited') }}</span>@elseif (\Wake\Accounts\TwoFactor::isEnabled($account))<span class="badge" title="{{ __('Two-factor sign-in is on') }}">2FA</span>@else<span class="badge muted" title="{{ __('Two-factor sign-in is off') }}">{{ __('no 2FA') }}</span>@endif</b>
                        <span class="muted" style="font-size:13px">{{ $account->email() }}</span>
                    </span>
                    @if ($account->role()->isOwner() || $account->id() === auth()->id())
                        <span class="badge">{{ $account->role()->label() }}</span>
                    @else
                        <select class="select" style="width:auto;min-height:36px" wire:change="changeRole('{{ $account->id() }}', $event.target.value)" aria-label="{{ __('Role') }}">
                            @foreach ($roles as $role)<option value="{{ $role }}" @selected($account->role()->key === $role)>{{ app(\Wake\Accounts\Roles::class)->get($role)->label() }}</option>@endforeach
                        </select>
                        @if ($account->isPending())<button class="btn btn-small" wire:click="resend('{{ $account->id() }}')">{{ __('Resend') }}</button>@endif
                        @if (\Wake\Accounts\TwoFactor::isEnabled($account))<button class="btn btn-small" wire:click="resetTwoFactor('{{ $account->id() }}')" wire:confirm="{{ __('Turn off two-factor sign-in for :name? Only do this if they lost their phone.', ['name' => $account->name()]) }}" title="{{ __('Turn off two-factor sign-in') }}"><x-wake::icon name="key" /></button>@endif
                        <button class="btn btn-small btn-danger" wire:click="remove('{{ $account->id() }}')" wire:confirm="{{ __('Remove :name from the team?', ['name' => $account->name()]) }}" title="{{ __('Remove') }}"><x-wake::icon name="trash" /></button>
                    @endif
                </div>
            @endforeach
        </div>

        <form class="card form" wire:submit="invite">
            <h2 class="h2">{{ __('Invite someone') }}</h2>
            <div class="field"><label for="invite-name">{{ __('Name') }}</label><input id="invite-name" class="input" wire:model="name">@error('name')<span class="error">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="invite-email">{{ __('Email address') }}</label><input id="invite-email" type="email" class="input" wire:model="email">@error('email')<span class="error">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="invite-role">{{ __('Role') }}</label>
                <select id="invite-role" class="select" wire:model="role">@foreach ($roles as $role)<option value="{{ $role }}">{{ app(\Wake\Accounts\Roles::class)->get($role)->label() }}</option>@endforeach</select></div>
            <p class="muted" style="margin:0;font-size:13px">{{ __('They get a mail with a link to choose their password. The link works for 7 days.') }}</p>
            <div class="actions"><button class="btn btn-primary" type="submit">{{ __('Send invitation') }}</button></div>
        </form>
    </section>

    <livewire:wake.role-manager />
</div>

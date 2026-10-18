<form class="card form" wire:submit="save">
    <div class="card-head">
        <h2 class="h2">{{ __('Sending mail') }}</h2>
        <span class="badge" @if ($sends) style="background:var(--accent);color:var(--on-accent)" @endif>{{ $sends ? __('Mails are sent') : __('Mails are not sent') }}</span>
    </div>
    <p class="muted" style="margin:0;font-size:14px">{{ __('Wake sends invitations and password links. Your host or mail provider tells you the SMTP details; they are often the same as in your mail program.') }}</p>
    @if (session('mail_status'))<div class="flash" role="status">{{ session('mail_status') }}</div>@endif
    @if (session('mail_error'))<div class="flash error" role="alert">{{ session('mail_error') }}</div>@endif

    <div class="field">
        <label for="mail-mailer">{{ __('Way of sending') }}</label>
        <select id="mail-mailer" class="select" wire:model.live="mailer" style="max-width:420px">
            <option value="smtp">{{ __('SMTP (recommended)') }}</option>
            <option value="sendmail">{{ __('The server sends itself (sendmail)') }}</option>
            <option value="log">{{ __('Do not send, only write to the log') }}</option>
        </select>
    </div>

    @if ($mailer === 'smtp')
        <div class="grid grid-3" style="gap:12px">
            <div class="field"><label for="mail-host">{{ __('Server') }}</label><input id="mail-host" class="input" wire:model="host" placeholder="smtp.example.org">@error('host')<span class="error">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="mail-port">{{ __('Port') }}</label><input id="mail-port" type="number" class="input" wire:model="port">@error('port')<span class="error">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="mail-enc">{{ __('Encryption') }}</label>
                <select id="mail-enc" class="select" wire:model="encryption"><option value="tls">STARTTLS (587)</option><option value="ssl">SSL/TLS (465)</option><option value="none">{{ __('None') }}</option></select></div>
        </div>
        <div class="grid grid-2" style="gap:12px">
            <div class="field"><label for="mail-user">{{ __('User name') }}</label><input id="mail-user" class="input" wire:model="username" autocomplete="off"></div>
            <div class="field"><label for="mail-pw">{{ __('Password') }}</label><input id="mail-pw" type="password" class="input" wire:model="password" autocomplete="new-password" placeholder="{{ $hasPassword ? __('stored, leave empty to keep it') : '' }}"></div>
        </div>
    @endif

    <div class="grid grid-2" style="gap:12px">
        <div class="field"><label for="mail-from">{{ __('Sender address') }}</label><input id="mail-from" type="email" class="input" wire:model="fromAddress" placeholder="hallo@example.org">@error('fromAddress')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="field"><label for="mail-name">{{ __('Sender name') }}</label><input id="mail-name" class="input" wire:model="fromName"></div>
    </div>
    <p class="muted" style="margin:0;font-size:13px">{{ __('The password is stored encrypted in storage/mail.yml. These settings replace the MAIL values in .env.') }}</p>

    <div class="actions">
        <button type="submit" class="btn btn-primary">{{ __('Save mail settings') }}</button>
        <button type="button" class="btn" wire:click="sendTest" wire:loading.attr="disabled">{{ __('Send a test mail to me') }}</button>
    </div>
</form>

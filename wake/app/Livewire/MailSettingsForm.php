<?php

namespace Wake\Livewire;

use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Livewire\Component;
use Throwable;
use Wake\Mail\MailSettings;

/**
 * How Wake sends invitations and password mails, with a test mail.
 */
class MailSettingsForm extends Component
{
    public string $mailer = 'smtp';

    public string $host = '';

    public int $port = 587;

    public string $encryption = 'tls';

    public string $username = '';

    public string $password = '';

    public string $fromAddress = '';

    public string $fromName = '';

    public function mount(MailSettings $settings): void
    {
        abort_unless(auth()->user()->can('settings.manage'), 403);

        $data = $settings->read();
        $this->mailer = $data['mailer'] ?? (MailSettings::sends() ? (string) config('mail.default') : 'smtp');
        $this->mailer = in_array($this->mailer, MailSettings::MAILERS, true) ? $this->mailer : 'smtp';
        $this->host = (string) ($data['host'] ?? config('mail.mailers.smtp.host', ''));
        $this->port = (int) ($data['port'] ?? config('mail.mailers.smtp.port', 587));
        $this->encryption = (string) ($data['encryption'] ?? 'tls');
        $this->username = (string) ($data['username'] ?? config('mail.mailers.smtp.username', ''));
        $this->fromAddress = (string) ($data['from_address'] ?? config('mail.from.address', ''));
        $this->fromName = (string) ($data['from_name'] ?? config('mail.from.name', ''));
    }

    public function save(MailSettings $settings): void
    {
        $this->validate([
            'mailer' => 'required|in:'.implode(',', MailSettings::MAILERS),
            'host' => 'required_if:mailer,smtp|nullable|string|max:190',
            'port' => 'required_if:mailer,smtp|integer|between:1,65535',
            'encryption' => 'in:tls,ssl,none',
            'username' => 'nullable|string|max:190',
            'password' => 'nullable|string|max:500',
            'fromAddress' => 'required|email|max:190',
            'fromName' => 'nullable|string|max:120',
        ]);

        $settings->save([
            'mailer' => $this->mailer, 'host' => trim($this->host), 'port' => $this->port, 'encryption' => $this->encryption,
            'username' => trim($this->username), 'password' => $this->password,
            'from_address' => trim($this->fromAddress), 'from_name' => trim($this->fromName),
        ]);

        $this->reset('password');
        session()->flash('mail_status', __('Mail settings saved.'));
    }

    public function sendTest(): void
    {
        try {
            Mail::raw(__('This is a test mail from Wake. If you can read it, invitations and password mails will arrive, too.'), function ($message) {
                $message->to(auth()->user()->email())->subject(__('Test mail from Wake'));
            });

            session()->flash('mail_status', MailSettings::sends()
                ? __('Test mail sent to :email. Check your inbox and the spam folder.', ['email' => auth()->user()->email()])
                : __('Wake only writes mails to its log file at the moment. Choose SMTP or sendmail to send them.'));
        } catch (Throwable $e) {
            session()->flash('mail_error', __('The test mail could not be sent: :error', ['error' => $e->getMessage()]));
        }
    }

    public function render(MailSettings $settings): View
    {
        return view('wake::livewire.mail-settings-form', [
            'sends' => MailSettings::sends(),
            'hasPassword' => $settings->hasPassword() || (! $settings->exists() && config('mail.mailers.smtp.password')),
        ]);
    }
}

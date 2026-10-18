<?php

namespace Wake\Mail;

use Illuminate\Support\Facades\Crypt;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use Wake\Support\Files;
use Wake\Support\Paths;

/**
 * How Wake sends mail, set in the panel and stored in data/secrets/mail.yml (not
 * in settings/, which may be committed to Git). The password is encrypted with
 * the application key. Without this file, the MAIL_* values in .env apply.
 */
class MailSettings
{
    public const MAILERS = ['smtp', 'sendmail', 'log'];

    public function file(): string
    {
        return Paths::data('secrets/mail.yml');
    }

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        try {
            $data = is_file($this->file()) ? Yaml::parseFile($this->file()) : [];
        } catch (Throwable) {
            $data = [];
        }

        return is_array($data) ? $data : [];
    }

    public function exists(): bool
    {
        return $this->read() !== [];
    }

    /**
     * Puts the stored settings into Laravel's mail configuration.
     */
    public function apply(): void
    {
        $data = $this->read();

        if (! in_array($data['mailer'] ?? null, self::MAILERS, true)) {
            return;
        }

        config([
            'mail.default' => $data['mailer'],
            'mail.mailers.smtp.host' => $data['host'] ?? '',
            'mail.mailers.smtp.port' => (int) ($data['port'] ?? 587),
            'mail.mailers.smtp.scheme' => ($data['encryption'] ?? '') === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username' => $data['username'] ?? null,
            'mail.mailers.smtp.password' => $this->password(),
            'mail.mailers.smtp.timeout' => 15,
            'mail.from.address' => $data['from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $data['from_name'] ?? config('mail.from.name'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values  an empty password keeps the stored one
     */
    public function save(array $values): void
    {
        $data = array_merge($this->read(), array_intersect_key($values, array_flip(['mailer', 'host', 'port', 'encryption', 'username', 'from_address', 'from_name'])));

        if (($values['password'] ?? '') !== '') {
            $data['password'] = Crypt::encryptString((string) $values['password']);
        }

        Files::write($this->file(), Yaml::dump($data, 2, 2));
        @chmod($this->file(), 0640);
        $this->apply();
    }

    public function hasPassword(): bool
    {
        return ($this->read()['password'] ?? '') !== '';
    }

    /**
     * Whether mails actually leave the server.
     */
    public static function sends(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    protected function password(): ?string
    {
        try {
            return $this->hasPassword() ? Crypt::decryptString((string) $this->read()['password']) : null;
        } catch (Throwable) {
            return null; // another application key
        }
    }
}

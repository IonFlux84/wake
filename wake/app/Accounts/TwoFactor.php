<?php

namespace Wake\Accounts;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sign-in with a second factor: one-time codes from an authenticator app
 * (TOTP, RFC 6238: SHA-1, 6 digits, 30 seconds) and recovery codes.
 *
 * Stored in the account file: the secret encrypted with the application key,
 * recovery codes only as hashes, and the last time step used, so a code
 * works only once.
 */
class TwoFactor
{
    public const DIGITS = 6;

    public const PERIOD = 30;

    /** Accepted clock drift in time steps, before and after. */
    public const WINDOW = 1;

    protected const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function __construct(protected Accounts $accounts) {}

    public static function isEnabled(Account $account): bool
    {
        return (string) $account->get('two_factor_secret', '') !== '';
    }

    public function newSecret(): string
    {
        return self::base32(random_bytes(20));
    }

    /**
     * The address authenticator apps read from the QR code.
     */
    public function uri(Account $account, string $secret): string
    {
        $issuer = rawurlencode(config('app.name', 'Wake'));

        return 'otpauth://totp/'.$issuer.':'.rawurlencode($account->email()).'?secret='.$secret.'&issuer='.$issuer.'&digits='.self::DIGITS.'&period='.self::PERIOD;
    }

    public function qrCode(string $uri): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd));

        return preg_replace('/<\?xml.*?\?>\s*/', '', $writer->writeString($uri));
    }

    /**
     * The code for a secret at a point in time (for tests and the setup check).
     */
    public static function code(string $secret, ?int $time = null): string
    {
        return self::codeAt($secret, intdiv($time ?? time(), self::PERIOD));
    }

    /**
     * Whether $code fits $secret now; returns the matching time step or null.
     */
    public static function verifyCode(string $secret, string $code, ?int $time = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code);

        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return null;
        }

        $step = intdiv($time ?? time(), self::PERIOD);

        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            if (hash_equals(self::codeAt($secret, $step + $i), $code)) {
                return $step + $i;
            }
        }

        return null;
    }

    /**
     * Turns two-factor sign-in on and returns the recovery codes (shown once).
     *
     * @return list<string>
     */
    public function enable(Account $account, string $secret): array
    {
        $codes = self::newRecoveryCodes();

        $this->accounts->save($account->with([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery' => array_map(fn ($code) => Hash::make($code), $codes),
            'two_factor_last' => null,
        ]));

        return $codes;
    }

    /**
     * Replaces the recovery codes and returns the new ones (shown once).
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(Account $account): array
    {
        $codes = self::newRecoveryCodes();
        $this->accounts->save($account->with(['two_factor_recovery' => array_map(fn ($code) => Hash::make($code), $codes)]));

        return $codes;
    }

    /**
     * @return list<string>
     */
    protected static function newRecoveryCodes(): array
    {
        return array_map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)), range(1, 8));
    }

    public function disable(Account $account): void
    {
        $this->accounts->save($account->with(['two_factor_secret' => null, 'two_factor_recovery' => null, 'two_factor_last' => null]));
    }

    /**
     * Checks a code from the app or a recovery code. A used code is spent.
     */
    public function verify(Account $account, string $input): bool
    {
        $input = trim($input);
        $secret = $this->secret($account);

        if ($secret === null) {
            return false;
        }

        $step = self::verifyCode($secret, $input);

        if ($step !== null) {
            if ($step <= (int) $account->get('two_factor_last', 0)) {
                return false; // used before
            }

            $this->accounts->save($account->with(['two_factor_last' => $step]));

            return true;
        }

        $codes = (array) $account->get('two_factor_recovery', []);

        foreach ($codes as $i => $hash) {
            if (Hash::check(Str::lower($input), (string) $hash)) {
                unset($codes[$i]);
                $this->accounts->save($account->with(['two_factor_recovery' => array_values($codes)]));

                return true;
            }
        }

        return false;
    }

    public function remainingRecoveryCodes(Account $account): int
    {
        return count((array) $account->get('two_factor_recovery', []));
    }

    protected function secret(Account $account): ?string
    {
        try {
            return self::isEnabled($account) ? Crypt::decryptString((string) $account->get('two_factor_secret')) : null;
        } catch (Throwable) {
            return null; // another application key, e.g. after moving the site
        }
    }

    protected static function codeAt(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 10 ** self::DIGITS), self::DIGITS, '0', STR_PAD_LEFT);
    }

    protected static function base32(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        return implode('', array_map(fn ($chunk) => self::ALPHABET[bindec(str_pad($chunk, 5, '0'))], str_split($bits, 5)));
    }

    protected static function base32Decode(string $text): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($text, '='))) as $char) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }

        return implode('', array_map(fn ($byte) => chr(bindec($byte)), array_filter(str_split($bits, 8), fn ($b) => strlen($b) === 8)));
    }
}

<?php

namespace Wake\Forms;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;

/**
 * Protection for public forms on cached pages, where a session-bound CSRF
 * token cannot work: the same HTML goes to every visitor.
 *
 * @wakeForm('contact') adds two hidden fields to a form:
 *
 * - a stamp signed with the application key: the form name and the time the
 *   page was made. Other sites cannot forge it, and a form sent within two
 *   seconds of loading, or after seven days, is refused.
 * - a honeypot field that people never see and bots like to fill.
 *
 * The middleware "wake.form:contact" checks both on the route, and allows
 * each visitor (by hashed IP) ten submissions of that form per ten minutes.
 */
class FormGuard
{
    public const MIN_SECONDS = 2;

    public const MAX_SECONDS = 7 * 86400;

    public const ATTEMPTS = 10;

    public static function fields(string $form): HtmlString
    {
        $time = now()->getTimestamp();
        $stamp = $time.'.'.self::sign($form, $time);

        return new HtmlString(
            '<input type="hidden" name="_wake_form" value="'.e($stamp).'">'
            .'<div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden">'
            .'<label>Leave this empty <input type="text" name="_wake_website" value="" tabindex="-1" autocomplete="off"></label></div>'
        );
    }

    /**
     * Why a submission is refused, or null if it may pass.
     */
    public static function refusal(Request $request, string $form): ?string
    {
        if ((string) $request->input('_wake_website', '') !== '') {
            return 'honeypot';
        }

        if (! preg_match('/^(\d{9,11})\.([a-f0-9]{64})$/', (string) $request->input('_wake_form', ''), $m)) {
            return 'stamp';
        }

        $age = now()->getTimestamp() - (int) $m[1];

        if (! hash_equals(self::sign($form, (int) $m[1]), $m[2]) || $age < self::MIN_SECONDS || $age > self::MAX_SECONDS) {
            return 'stamp';
        }

        $key = 'wake-form:'.$form.':'.hash('sha256', (string) $request->ip());

        if (RateLimiter::tooManyAttempts($key, self::ATTEMPTS)) {
            return 'too-many';
        }

        RateLimiter::hit($key, 600);

        return null;
    }

    protected static function sign(string $form, int $time): string
    {
        return hash_hmac('sha256', 'wake-form|'.$form.'|'.$time, (string) config('app.key'));
    }
}

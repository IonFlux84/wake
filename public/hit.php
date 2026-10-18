<?php

/*
 * Wake's statistics endpoint. The tracking script sends page views, reading
 * signals and events here with navigator.sendBeacon().
 *
 * On purpose this file does not start Laravel: it checks the data, builds a
 * daily, salted visitor hash, appends one line to storage/stats/raw/{day}.log
 * and answers "204 No Content". IP addresses are never written anywhere.
 *
 * The rules come from storage/stats/.config.php, which Wake writes itself.
 * See docs/concepts/analytics.md.
 */

$root = dirname(__DIR__);
$config = @include $root.'/storage/stats/.config.php';

header('Cache-Control: no-store');

if (is_array($config) && ! empty($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

function wake_done(int $status = 204): never
{
    http_response_code($status);
    exit;
}

if (! is_array($config) || empty($config['enabled']) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    wake_done();
}

// Team members signed in to the panel are not counted.
if (isset($_COOKIE[$config['session_cookie']])) {
    wake_done();
}

$userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);

if ($userAgent === '' || preg_match($config['bots'], $userAgent)) {
    wake_done();
}

$data = json_decode((string) file_get_contents('php://input', length: 4096), true);

if (! is_array($data)) {
    wake_done(400);
}

$type = (string) ($data['t'] ?? '');

if (! in_array($type, ['pageview', 'leave', 'event', 'consent'], true)) {
    wake_done(400);
}

$clean = fn ($value, int $length = 200): string => substr(str_replace(["\t", "\n", "\r"], ' ', trim((string) $value)), 0, $length);

// Consent decisions are proof records, kept apart from the statistics.
if ($type === 'consent') {
    $id = preg_replace('/[^a-z0-9]/i', '', (string) ($data['id'] ?? ''));
    $categories = array_values(array_intersect((array) ($data['c'] ?? []), $config['consent_categories']));

    if (strlen($id) < 16) {
        wake_done(400);
    }

    $line = json_encode([
        'time' => gmdate('c'),
        'id' => substr($id, 0, 40),
        'categories' => $categories,
        'action' => in_array($data['a'] ?? '', ['accept', 'decline', 'custom'], true) ? $data['a'] : 'custom',
        'version' => (int) ($data['v'] ?? 0),
        'language' => $clean($data['l'] ?? '', 10),
    ], JSON_UNESCAPED_SLASHES);

    @mkdir($root.'/storage/consent', 0775, true);
    file_put_contents($root.'/storage/consent/'.gmdate('Y-m').'.log', $line."\n", FILE_APPEND | LOCK_EX);
    wake_done();
}

$path = '/'.ltrim($clean(parse_url((string) ($data['p'] ?? '/'), PHP_URL_PATH) ?: '/', 300), '/');
parse_str((string) (parse_url((string) ($data['p'] ?? ''), PHP_URL_QUERY) ?? ''), $query);

// Level 1 (no cookie) may require consent on some sites; level 2 always does.
$level = ($data['c'] ?? 0) == 2 ? 2 : 1;
$privacySignal = ($_SERVER['HTTP_DNT'] ?? '') === '1' || ($_SERVER['HTTP_SEC_GPC'] ?? '') === '1';

if (($config['level1'] === 'consent' && ($data['c'] ?? 0) < 1) || ($privacySignal && $config['respect_privacy_signals'])) {
    wake_done();
}

// The visitor hash changes every day, because the salt does. Yesterday's
// visitors cannot be recognised today, and the IP address is never stored.
$day = date('Y-m-d');
$saltFile = $root.'/storage/stats/salt/'.$day;

if (! is_file($saltFile)) {
    @mkdir(dirname($saltFile), 0700, true);
    @file_put_contents($saltFile, bin2hex(random_bytes(32)), LOCK_EX);
}

$salt = (string) @file_get_contents($saltFile);
$visitor = substr(hash_hmac('sha256', ($_SERVER['REMOTE_ADDR'] ?? '').'|'.$userAgent.'|'.$config['host'], $salt), 0, 16);

// With consent, the wake_v cookie recognises returning visitors over days.
$returning = '';
if ($level === 2 && preg_match('/^[a-z0-9]{16,40}$/i', (string) ($_COOKIE['wake_v'] ?? ''))) {
    $visitor = 'c'.substr(hash_hmac('sha256', $_COOKIE['wake_v'], $config['key']), 0, 15);
    $returning = ($data['n'] ?? 0) ? 'new' : 'returning';
}

$referrer = '';
if (! empty($data['r'])) {
    $host = strtolower((string) parse_url((string) $data['r'], PHP_URL_HOST));
    $referrer = $host !== '' && $host !== $config['host'] ? preg_replace('/^(www|m)\./', '', $host) : '';
}

$device = preg_match('/iPad|Tablet|PlayBook|Silk|(Android(?!.*Mobile))/i', $userAgent) ? 'tablet'
    : (preg_match('/Mobi|iPhone|iPod|Android|BlackBerry|Opera Mini|IEMobile/i', $userAgent) ? 'mobile' : 'desktop');

$browser = match (true) {
    (bool) preg_match('/Edg\//', $userAgent) => 'Edge',
    (bool) preg_match('/OPR\/|Opera/', $userAgent) => 'Opera',
    (bool) preg_match('/SamsungBrowser/', $userAgent) => 'Samsung Internet',
    (bool) preg_match('/Firefox\/|FxiOS/', $userAgent) => 'Firefox',
    (bool) preg_match('/Chrome\/|CriOS/', $userAgent) => 'Chrome',
    (bool) preg_match('/Safari\//', $userAgent) => 'Safari',
    default => 'Other',
};

$os = match (true) {
    (bool) preg_match('/Windows/', $userAgent) => 'Windows',
    (bool) preg_match('/iPhone|iPad|iPod/', $userAgent) => 'iOS',
    (bool) preg_match('/Mac OS X|Macintosh/', $userAgent) => 'macOS',
    (bool) preg_match('/Android/', $userAgent) => 'Android',
    (bool) preg_match('/Linux/', $userAgent) => 'Linux',
    default => 'Other',
};

// Country from the optional offline database. Afterwards the IP is gone.
$country = '';
if (! empty($config['geo']) && is_file($config['geo'])) {
    require_once $root.'/wake/vendor/autoload.php';

    try {
        $record = (new MaxMind\Db\Reader($config['geo']))->get($_SERVER['REMOTE_ADDR'] ?? '');
        $country = (string) ($record['country']['iso_code'] ?? '');
    } catch (Throwable) {
        $country = '';
    }
}

$fields = [
    time(),
    $type,
    $visitor,
    $returning,
    $path,
    $referrer,
    $clean($query['utm_source'] ?? $query['ref'] ?? '', 80),
    $clean($query['utm_medium'] ?? '', 80),
    $clean($query['utm_campaign'] ?? '', 80),
    $clean($data['l'] ?? '', 10),
    $country,
    $device,
    $browser,
    $os,
    $type === 'event' ? $clean($data['e'] ?? '', 80) : '',
    $type === 'event' ? $clean($data['x'] ?? '', 200) : '',
    max(0, min(7200, (int) ($data['d'] ?? 0))),
    max(0, min(100, (int) ($data['s'] ?? 0))),
    $level,
];

@mkdir($root.'/storage/stats/raw', 0775, true);
file_put_contents($root.'/storage/stats/raw/'.$day.'.log', implode("\t", $fields)."\n", FILE_APPEND | LOCK_EX);

wake_done();

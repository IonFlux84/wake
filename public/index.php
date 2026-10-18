<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$root = dirname(__DIR__);

// Requests sent here by the .htaccess in the site folder (hosts without a
// public/ document root) must not end up with "/public" in their URLs.
if (getenv('REDIRECT_WAKE_ROOT_REWRITE') || getenv('WAKE_ROOT_REWRITE')) {
    $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';
}

// AI crawlers don't run the tracking script, so they are counted here: one
// line per visit in data/stats/ai, with the crawler's name and the path.
if (preg_match('/GPTBot|ChatGPT-User|OAI-SearchBot|ClaudeBot|Claude-User|Claude-SearchBot|anthropic-ai|PerplexityBot|Perplexity-User|Google-Extended|Bytespider|CCBot|Amazonbot|Applebot-Extended|meta-externalagent|cohere-ai|DuckAssistBot|MistralAI-User/i', $_SERVER['HTTP_USER_AGENT'] ?? '', $bot)) {
    @mkdir($root.'/data/stats/ai', 0775, true);
    @file_put_contents($root.'/data/stats/ai/'.date('Y-m-d').'.log', time()."\t".$bot[0]."\t".substr(strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), 0, 300)."\n", FILE_APPEND | LOCK_EX);
}

// Serve a cached page before Laravel even starts. Wake writes these files
// itself (see Wake\Http\Middleware\CachePages) and keeps the rules for
// serving them in .rules.php next to them.
if (file_exists($rules = $root.'/temp/cache/pages/.rules.php')) {
    $rules = require $rules;

    // Pages are cached per scheme and host, so an http or www version never
    // hands out links of another one. Keep in sync with PageCache::hostKey().
    $secure = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host = ($secure ? 'https_' : 'http_').preg_replace('/[^a-z0-9.-]/', '_', strtolower($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $file = $rules['dir'].'/'.$host.'/'.trim(rawurldecode(strtok($_SERVER['REQUEST_URI'] ?? '/', '?')), '/').'/index.html';

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
        && ($_SERVER['QUERY_STRING'] ?? '') === ''
        && ! isset($_COOKIE[$rules['cookie']])
        && ! str_contains($file, '..')
        && is_file($file)
        && filemtime($file) + $rules['ttl'] > time()) {
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Wake-Cache: hit');
        readfile($file);
        exit;
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $root.'/temp/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $root.'/wake/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $root.'/wake/bootstrap/app.php';

$app->handleRequest(Request::capture());

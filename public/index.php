<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$root = dirname(__DIR__);

// AI crawlers don't run the tracking script, so they are counted here: one
// line per visit in storage/stats/ai, with the crawler's name and the path.
if (preg_match('/GPTBot|ChatGPT-User|OAI-SearchBot|ClaudeBot|Claude-User|Claude-SearchBot|anthropic-ai|PerplexityBot|Perplexity-User|Google-Extended|Bytespider|CCBot|Amazonbot|Applebot-Extended|meta-externalagent|cohere-ai|DuckAssistBot|MistralAI-User/i', $_SERVER['HTTP_USER_AGENT'] ?? '', $bot)) {
    @mkdir($root.'/storage/stats/ai', 0775, true);
    @file_put_contents($root.'/storage/stats/ai/'.date('Y-m-d').'.log', time()."\t".$bot[0]."\t".substr(strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), 0, 300)."\n", FILE_APPEND | LOCK_EX);
}

// Serve a cached page before Laravel even starts. Wake writes these files
// itself (see Wake\Http\Middleware\CachePages) and keeps the rules for
// serving them in .rules.php next to them.
if (file_exists($rules = $root.'/storage/cache/pages/.rules.php')) {
    $rules = require $rules;
    $file = $rules['dir'].'/'.trim(rawurldecode(strtok($_SERVER['REQUEST_URI'] ?? '/', '?')), '/').'/index.html';

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
if (file_exists($maintenance = $root.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $root.'/wake/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $root.'/wake/bootstrap/app.php';

$app->handleRequest(Request::capture());

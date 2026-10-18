<?php

// Default settings for Wake. A site overrides any of them in site/config.php,
// which returns an array with the same keys. Every key is documented in
// docs/reference/configuration.md (a test makes sure it stays that way).

return [

    // Where Wake finds the parts that belong to the site owner.
    'paths' => [
        'site' => env('WAKE_SITE_PATH', dirname(base_path()).'/site'),
        'content' => env('WAKE_CONTENT_PATH', dirname(base_path()).'/content'),
    ],

    // Languages of the site. The first one is the default language.
    'languages' => [
        'en' => 'English',
    ],

    // Put the default language in the URL too (/en/about instead of /about).
    'prefix_default_language' => false,

    // What happens when a page has no translation in the requested language:
    // "hide" answers with 404, "fallback" shows the default language.
    'missing_translation' => 'hide',

    // Name of the folder in site/themes/ that renders the website.
    'theme' => 'tide',

    // URL pattern for posts. Placeholders: {slug}, {year}, {month}, {day}.
    'post_url' => '{slug}',

    // URL segment in front of tag pages, e.g. /tag/travel.
    'tag_url' => 'tag',

    // Posts per page on lists and in feeds.
    'per_page' => 10,

    'index' => [
        // "always" checks file times on every uncached request and rebuilds
        // the index when someone changed files via FTP or Git. "never" leaves
        // that to the panel and to `php wake/artisan wake:index`.
        'check' => 'always',
    ],

    'page_cache' => [
        // Serve finished HTML from storage/cache/pages without booting Laravel.
        'enabled' => env('WAKE_PAGE_CACHE', true),

        // Seconds a cached page may be served before Wake looks again. This
        // is how changes made via FTP and due scheduled posts show up.
        'ttl' => 300,
    ],

    'maintenance' => [
        // "requests" runs maintenance after normal page views (no cron needed),
        // "cron" leaves it to `php wake/artisan schedule:run`.
        'mode' => 'requests',

        // At most once per this many seconds.
        'interval' => 60,

        // Time budget per run in seconds.
        'budget' => 5,
    ],

];

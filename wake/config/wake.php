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

    // Closed site: only signed-in team members see it, everyone else lands on
    // the sign-in page, and search engines are kept out.
    'closed' => false,

    // Languages of the site. The first one is the default language.
    'languages' => [
        'en' => 'English',
    ],

    // Put the default language in the URL too (/en/about instead of /about).
    'prefix_default_language' => false,

    // What happens when a page has no translation in the requested language:
    // "hide" answers with 404, "fallback" shows the default language.
    'missing_translation' => 'hide',

    // Time zone for dates in content and for scheduled publishing.
    'timezone' => env('WAKE_TIMEZONE', 'UTC'),

    // Name of the folder in site/themes/ that renders the website.
    'theme' => 'tide',

    // Options for the active theme, e.g. ['accent' => '#3D5AFE'] for Tide.
    'theme_options' => [],

    // Plugins in site/plugins/: folders that are switched off, and the
    // settings of each plugin, usually changed in the panel under Plugins.
    'plugins' => [
        'disabled' => [],
        'options' => [],
    ],

    // URL pattern for posts. Placeholders: {slug}, {year}, {month}, {day}.
    'post_url' => '{slug}',

    // URL segment in front of tag pages, e.g. /tag/travel.
    'tag_url' => 'tag',

    // Posts per page on lists and in feeds.
    'per_page' => 10,

    'images' => [
        // Widths Wake may create. Requests for other widths use the next larger one.
        'widths' => [320, 640, 960, 1280, 1920],

        // JPEG, WebP and AVIF quality from 1 to 100.
        'quality' => 82,
    ],

    'feed' => [
        // Number of posts in the RSS feed.
        'limit' => 20,
    ],

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

    'tracking' => [
        // Count visits with Wake's own, cookieless statistics.
        'enabled' => env('WAKE_TRACKING', true),

        // Level 1 (no cookie): "always", or "consent" to count only after the
        // visitor agreed in the banner. Level 2 (with cookie) always needs consent.
        'level1' => 'always',

        // Ignore visitors who send Do Not Track or Global Privacy Control.
        'respect_privacy_signals' => false,

        // Days to keep raw data. Daily and monthly sums are kept forever.
        'retention' => 30,

        // Optional IP-to-country database in MaxMind format (e.g. DB-IP Lite).
        'geo' => dirname(base_path()).'/storage/stats/geo/country.mmdb',
    ],

    'mcp' => [
        // Assistants may only ask for publishing; a person approves in the panel.
        'approval' => false,
    ],

    'update' => [
        // GitHub repository whose releases the updater installs.
        'repository' => 'IonFlux84/wake',
    ],

    'panel' => [
        // Language of the panel ("de" or "en"). Everyone can choose their own
        // under Settings; this is the language for everyone else.
        'language' => 'de',

        // The one neon colour of the panel. Text on it turns black or white by itself.
        'accent' => '#C6FF3D',
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

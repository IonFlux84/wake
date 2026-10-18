<?php

// Default settings for Wake. A site overrides any of them in settings/config.php,
// which returns an array with the same keys. Every key is documented in
// docs/reference/configuration.md (a test makes sure it stays that way).

return [

    // Where Wake finds the parts that belong to the site owner.
    'paths' => [
        'content' => env('WAKE_CONTENT_PATH', dirname(base_path()).'/content'),
        'settings' => env('WAKE_SETTINGS_PATH', dirname(base_path()).'/settings'),
        'themes' => env('WAKE_THEMES_PATH', dirname(base_path()).'/themes'),
        'plugins' => env('WAKE_PLUGINS_PATH', dirname(base_path()).'/plugins'),
        'data' => env('WAKE_DATA_PATH', dirname(base_path()).'/data'),
    ],

    // Closed site: only signed-in team members see it, everyone else lands on
    // the sign-in page, and search engines are kept out.
    'closed' => false,

    // Security of the panel. With require_two_factor, everyone must set up
    // two-factor sign-in before they can use the panel.
    'security' => [
        'require_two_factor' => false,
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

    // Time zone for dates in content and for scheduled publishing.
    'timezone' => env('WAKE_TIMEZONE', 'UTC'),

    // Name of the folder in themes/ that renders the website.
    'theme' => 'tide',

    // Options for the active theme, e.g. ['accent' => '#3D5AFE'] for Tide.
    'theme_options' => [],

    // Plugins in plugins/: folders that are switched off, and the
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
        // Serve finished HTML from temp/cache/pages without booting Laravel.
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
        'level1' => 'consent',

        // Ignore visitors who send Do Not Track or Global Privacy Control.
        'respect_privacy_signals' => false,

        // Days to keep raw data. Daily and monthly sums are kept forever.
        'retention' => 30,

        // Optional IP-to-country database in MaxMind format (e.g. DB-IP Lite).
        'geo' => dirname(base_path()).'/data/stats/geo/country.mmdb',
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

        // Highlight colour for small marks only (published dot, main chart line). Black keeps the panel greyscale.
        'accent' => '#0A0A0A',
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

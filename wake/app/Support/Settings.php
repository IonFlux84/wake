<?php

namespace Wake\Support;

use Symfony\Component\Yaml\Yaml;

/**
 * Settings changed in the panel, stored in site/settings.yml. They override
 * site/config.php. Only the keys in EDITABLE can be written this way.
 */
class Settings
{
    public const EDITABLE = [
        'timezone', 'post_url', 'missing_translation', 'prefix_default_language',
        'tracking.enabled', 'tracking.level1', 'tracking.respect_privacy_signals', 'tracking.retention',
        'mcp.approval', 'panel.accent', 'panel.language', 'closed', 'theme', 'theme_options',
    ];

    public static function file(): string
    {
        return Paths::site('settings.yml');
    }

    /**
     * @return array<string, mixed>
     */
    public static function read(): array
    {
        $file = static::file();

        return is_file($file) ? (Yaml::parseFile($file) ?: []) : [];
    }

    /**
     * Store the given dotted keys and apply them to the running config.
     *
     * @param  array<string, mixed>  $values  e.g. ['tracking.retention' => 60]
     */
    public static function save(array $values): void
    {
        $settings = static::read();

        foreach ($values as $key => $value) {
            if (in_array($key, self::EDITABLE, true)) {
                data_set($settings, $key, $value);
                config(['wake.'.$key => $value]);
            }
        }

        Files::write(static::file(), Yaml::dump($settings, 4, 2));
    }
}

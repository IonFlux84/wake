<?php

namespace Wake\Plugins;

/**
 * What plugins/{folder}/plugin.yml says about a plugin.
 */
final class PluginInfo
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(public readonly string $folder, public readonly string $path, protected array $data) {}

    public function name(): string
    {
        return (string) ($this->data['name'] ?? $this->folder);
    }

    public function description(): string
    {
        $description = $this->data['description'] ?? '';

        return (string) (is_array($description) ? ($description[app()->getLocale()] ?? reset($description)) : $description);
    }

    public function version(): string
    {
        return (string) ($this->data['version'] ?? '');
    }

    public function author(): string
    {
        return (string) ($this->data['author'] ?? '');
    }

    public function url(): string
    {
        return (string) ($this->data['url'] ?? '');
    }

    /**
     * Settings the plugin offers in the panel, like theme options.
     *
     * @return array<string, array<string, mixed>>
     */
    public function options(): array
    {
        return array_filter((array) ($this->data['options'] ?? []), 'is_array');
    }
}

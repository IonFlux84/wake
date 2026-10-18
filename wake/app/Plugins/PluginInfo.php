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

    /**
     * Namespaces and the folders their classes live in, from "autoload" in
     * plugin.yml (PSR-4): { OpeningHours: src }. Folders outside the plugin
     * and invalid namespaces are left out.
     *
     * @return array<string, string> namespace prefix with trailing \ => absolute folder
     */
    public function autoload(): array
    {
        $map = [];
        $root = realpath($this->path);

        foreach ((array) ($this->data['autoload'] ?? []) as $namespace => $folder) {
            $namespace = trim((string) $namespace, '\\');
            $dir = realpath($this->path.'/'.trim((string) $folder, '/'));

            if ($root !== false && $dir !== false && is_dir($dir) && str_starts_with($dir.'/', $root.'/')
                && preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $namespace)) {
                $map[$namespace.'\\'] = $dir;
            }
        }

        return $map;
    }
}

<?php

namespace Wake\Accounts;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;
use Wake\Support\Files;
use Wake\Support\Paths;

/**
 * The roles of the site: the built-in ones, adjusted and extended in
 * site/roles.yml.
 *
 *     editor:
 *       capabilities: [content.write, content.publish, content.edit_all]
 *     moderator:
 *       label: { de: Moderation, en: Moderator }
 *       capabilities: [content.write, content.edit_all, stats.view]
 */
class Roles
{
    /** @var array<string, Role>|null */
    protected ?array $roles = null;

    /**
     * @return array<string, list<string>>
     */
    public static function defaults(): array
    {
        $all = array_keys(Capabilities::all());

        return [
            Role::Owner => $all,
            Role::Admin => $all,
            Role::Editor => ['content.write', 'content.publish', 'content.edit_all', 'content.publish_all', 'content.delete_all', 'structure.manage', 'stats.view', 'assistants.use'],
            Role::Author => ['content.write', 'content.publish', 'stats.view', 'assistants.use'],
            Role::Contributor => ['content.write', 'stats.view', 'assistants.use'],
        ];
    }

    /**
     * @return array<string, Role>
     */
    public function all(): array
    {
        if ($this->roles !== null) {
            return $this->roles;
        }

        $stored = $this->stored();
        $this->roles = [];

        foreach (self::defaults() as $key => $capabilities) {
            $this->roles[$key] = new Role($key, $stored[$key]['label'] ?? [], $key === Role::Owner ? $capabilities : ($stored[$key]['capabilities'] ?? $capabilities), builtIn: true);
        }

        foreach ($stored as $key => $data) {
            if (! isset($this->roles[$key]) && is_array($data)) {
                $this->roles[$key] = new Role($key, $data['label'] ?? [], array_values((array) ($data['capabilities'] ?? [])));
            }
        }

        return $this->roles;
    }

    public function get(?string $key): ?Role
    {
        return $key === null ? null : ($this->all()[$key] ?? null);
    }

    /**
     * @param  array<string, string>  $label
     * @param  list<string>  $capabilities
     */
    public function save(string $key, array $label, array $capabilities): Role
    {
        if ($key === Role::Owner) {
            throw new InvalidArgumentException('The owner always has every capability.');
        }

        $key = Str::slug($key, '_');

        if ($key === '') {
            throw new InvalidArgumentException('A role needs a name.');
        }

        $stored = $this->stored();
        $stored[$key] = array_filter([
            'label' => array_filter($label) ?: null,
            'capabilities' => array_values(array_intersect(array_keys(Capabilities::all()), $capabilities)),
        ], fn ($v) => $v !== null);

        $this->write($stored);

        return $this->get($key);
    }

    /**
     * Remove a custom role. Built-in roles go back to their defaults.
     */
    public function delete(string $key): void
    {
        $stored = $this->stored();
        unset($stored[$key]);
        $this->write($stored);
    }

    public function file(): string
    {
        return Paths::site('roles.yml');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function stored(): array
    {
        $file = $this->file();

        return is_file($file) ? (Yaml::parseFile($file) ?: []) : [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $stored
     */
    protected function write(array $stored): void
    {
        Files::write($this->file(), Yaml::dump($stored, 4, 2));
        $this->roles = null;
    }
}

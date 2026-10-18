<?php

namespace Wake\Accounts;

/**
 * A role in the team: a name and a set of capabilities. The five built-in
 * roles follow Ghost and can be adjusted; sites can add their own (see
 * Roles). The owner always has every capability.
 */
final class Role
{
    public const Owner = 'owner';

    public const Admin = 'admin';

    public const Editor = 'editor';

    public const Author = 'author';

    public const Contributor = 'contributor';

    /**
     * @param  array<string, string>|string  $label  per panel language
     * @param  list<string>  $capabilities
     */
    public function __construct(
        public readonly string $key,
        protected array|string $label,
        protected array $capabilities,
        public readonly bool $builtIn = false,
    ) {}

    public function isOwner(): bool
    {
        return $this->key === self::Owner;
    }

    public function can(string $capability): bool
    {
        return $this->isOwner() || in_array($capability, $this->capabilities, true);
    }

    /**
     * @return list<string>
     */
    public function capabilities(): array
    {
        return $this->isOwner() ? array_keys(Capabilities::all()) : $this->capabilities;
    }

    public function label(?string $locale = null): string
    {
        if (is_string($this->label) && $this->label !== '') {
            return $this->label;
        }

        $locale ??= app()->getLocale();

        return (string) ($this->label[$locale] ?? $this->label['en'] ?? (in_array($this->key, [self::Owner, self::Admin, self::Editor, self::Author, self::Contributor], true)
            ? __('wake::roles.'.$this->key, locale: $locale)
            : ucfirst($this->key)));
    }

    /**
     * @return array<string, string>|string
     */
    public function rawLabel(): array|string
    {
        return $this->label;
    }
}

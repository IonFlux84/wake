<?php

namespace Wake\Accounts;

use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\Access\Authorizable;

/**
 * A member of the team, stored in storage/accounts/{id}/account.yml.
 */
class Account implements Authenticatable, AuthorizableContract
{
    use Authorizable;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(protected array $attributes) {}

    public function id(): string
    {
        return $this->attributes['id'];
    }

    public function name(): string
    {
        return (string) ($this->attributes['name'] ?? $this->id());
    }

    public function email(): string
    {
        return (string) ($this->attributes['email'] ?? '');
    }

    public function role(): Role
    {
        return Role::tryFrom((string) ($this->attributes['role'] ?? '')) ?? Role::Contributor;
    }

    /**
     * Language of the panel for this person.
     */
    public function language(): string
    {
        return (string) ($this->attributes['language'] ?? config('app.locale'));
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name())))
            ->filter()
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->take(2)
            ->implode('');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function with(array $attributes): static
    {
        return new static(array_merge($this->attributes, $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    // Authenticatable ------------------------------------------------------

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->id();
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return (string) ($this->attributes['password'] ?? '');
    }

    public function getRememberToken(): ?string
    {
        return $this->attributes['remember_token'] ?? null;
    }

    public function setRememberToken($value): void
    {
        $this->attributes['remember_token'] = $value;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}

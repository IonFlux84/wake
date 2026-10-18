<?php

namespace Wake\Accounts;

use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Wake\Mail\PasswordResetMail;

/**
 * A member of the team, stored in data/accounts/{id}/account.yml.
 */
class Account implements Authenticatable, AuthorizableContract, CanResetPassword
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
        $roles = app(Roles::class);

        return $roles->get((string) ($this->attributes['role'] ?? '')) ?? $roles->get(Role::Contributor);
    }

    public function can($abilities, $arguments = []): bool
    {
        // A capability like "team.manage"; Laravel's gate abilities otherwise.
        if (is_string($abilities) && array_key_exists($abilities, Capabilities::all())) {
            return $this->role()->can($abilities);
        }

        return app(Gate::class)->forUser($this)->check($abilities, $arguments);
    }

    /**
     * Language of the panel for this person: their own choice, or else the
     * panel language of the site.
     */
    public function language(): string
    {
        return (string) ($this->attributes['language'] ?? config('wake.panel.language', 'de'));
    }

    /**
     * The language this person chose explicitly, or null to follow the site.
     */
    public function chosenLanguage(): ?string
    {
        return $this->attributes['language'] ?? null;
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

    /**
     * Invited, but has not chosen a password yet.
     */
    public function isPending(): bool
    {
        return $this->getAuthPassword() === '';
    }

    // Password reset -------------------------------------------------------

    public function getEmailForPasswordReset(): string
    {
        return $this->email();
    }

    public function sendPasswordResetNotification($token): void
    {
        try {
            Mail::to($this->email())->locale($this->language())->send(new PasswordResetMail($this, $token));
        } catch (\Throwable $e) {
            // Never tell the visitor whether an address exists; the log says why.
            Log::warning('Password mail failed: '.$e->getMessage());
        }
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

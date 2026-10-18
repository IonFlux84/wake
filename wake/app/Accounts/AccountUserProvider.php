<?php

namespace Wake\Accounts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Hash;

/**
 * Lets Laravel's authentication use Wake's file-based accounts.
 */
class AccountUserProvider implements UserProvider
{
    public function __construct(protected Accounts $accounts) {}

    public function retrieveById($identifier): ?Account
    {
        return $this->accounts->find((string) $identifier);
    }

    public function retrieveByToken($identifier, $token): ?Account
    {
        $account = $this->retrieveById($identifier);
        $stored = $account?->getRememberToken();

        return $stored !== null && hash_equals($stored, (string) $token) ? $account : null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        /** @var Account $user */
        $user->setRememberToken($token);
        $this->accounts->save($user);
    }

    public function retrieveByCredentials(array $credentials): ?Account
    {
        return isset($credentials['email']) ? $this->accounts->findByEmail($credentials['email']) : null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return $user->getAuthPassword() !== '' && Hash::check($credentials['password'] ?? '', $user->getAuthPassword());
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        /** @var Account $user */
        if (! $force && ! Hash::needsRehash($user->getAuthPassword())) {
            return;
        }

        $this->accounts->save($user->with(['password' => Hash::make($credentials['password'])]));
    }
}

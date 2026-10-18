<?php

namespace Wake\Accounts;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;
use Wake\Support\Files;

/**
 * Personal access tokens for the MCP server and the API. Each token is a
 * file in storage/accounts/{account}/tokens/. Only a SHA-256 hash of the
 * token is stored; the token itself is shown once, when it is created.
 */
class Tokens
{
    public const SCOPES = ['stats:read', 'content:read', 'content:write', 'content:publish'];

    public const PREFIX = 'wake_';

    public function __construct(protected Accounts $accounts) {}

    /**
     * @param  list<string>  $scopes
     * @return array{0: string, 1: array<string, mixed>} the plain token and its record
     */
    public function create(Account $account, string $name, array $scopes): array
    {
        $scopes = array_values(array_intersect(self::SCOPES, $scopes));

        if ($scopes === []) {
            throw new InvalidArgumentException('A token needs at least one scope.');
        }

        $plain = self::PREFIX.Str::random(40);
        $hash = hash('sha256', $plain);

        $record = [
            'id' => substr($hash, 0, 16),
            'name' => trim($name) ?: 'Token',
            'hash' => $hash,
            'scopes' => $scopes,
            'created_at' => now()->toIso8601String(),
            'last_used_at' => null,
        ];

        Files::write($this->path($account->id(), $record['id']), Yaml::dump($record));

        return [$plain, $record];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function of(Account $account): Collection
    {
        return collect(glob($this->accounts->folder($account->id()).'/tokens/*.yml') ?: [])
            ->map(fn (string $file) => Yaml::parseFile($file))
            ->sortByDesc('created_at')
            ->values();
    }

    /**
     * Find the account and record for a plain token.
     *
     * @return array{0: Account, 1: array<string, mixed>}|null
     */
    public function find(string $plain): ?array
    {
        if (! str_starts_with($plain, self::PREFIX)) {
            return null;
        }

        $hash = hash('sha256', $plain);
        $files = glob($this->accounts->directory().'/*/tokens/'.substr($hash, 0, 16).'.yml') ?: [];

        foreach ($files as $file) {
            $record = Yaml::parseFile($file);

            if (is_array($record) && hash_equals($record['hash'] ?? '', $hash)) {
                $account = $this->accounts->find(basename(dirname($file, 2)));

                return $account ? [$account, $record] : null;
            }
        }

        return null;
    }

    public function touch(Account $account, array $record): void
    {
        // Writing on every request would be wasteful; once a minute is enough.
        if ($record['last_used_at'] && now()->diffInSeconds($record['last_used_at'], true) < 60) {
            return;
        }

        $record['last_used_at'] = now()->toIso8601String();
        Files::write($this->path($account->id(), $record['id']), Yaml::dump($record));
    }

    public function revoke(Account $account, string $id): void
    {
        @unlink($this->path($account->id(), preg_replace('/[^a-f0-9]/', '', $id)));
    }

    protected function path(string $account, string $id): string
    {
        return $this->accounts->folder($account).'/tokens/'.$id.'.yml';
    }
}

<?php

namespace Wake\OAuth;

use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;
use Wake\Support\Files;
use Wake\Support\Paths;

/**
 * OAuth for the MCP server, kept in files like everything else in Wake:
 *
 *     data/oauth/clients/{client id}.yml    registered clients (no secrets)
 *     data/oauth/codes/{hash}.yml           authorization codes, 10 minutes
 *     data/oauth/refresh/{hash}.yml         refresh tokens, 180 days
 *
 * Codes and refresh tokens are stored as SHA-256 hashes and can be used once.
 * Access tokens are ordinary Wake tokens (Wake\Accounts\Tokens).
 */
class OAuthStore
{
    public const CODE_SECONDS = 600;

    public const REFRESH_DAYS = 180;

    /**
     * @param  list<string>  $redirectUris
     * @return array<string, mixed>
     */
    public function registerClient(string $name, array $redirectUris): array
    {
        $client = [
            'client_id' => 'wake-'.Str::lower(Str::random(24)),
            'client_name' => trim($name) ?: 'Assistant',
            'redirect_uris' => array_values($redirectUris),
            'created_at' => now()->toIso8601String(),
        ];

        Files::write($this->file('clients', $client['client_id']), Yaml::dump($client, 3, 2));

        return $client;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function client(string $id): ?array
    {
        return preg_match('/^wake-[a-z0-9]{24}$/', $id) ? $this->read($this->file('clients', $id)) : null;
    }

    /**
     * @param  array<string, mixed>  $data  client_id, account, scopes, redirect_uri, challenge
     */
    public function createCode(array $data): string
    {
        $code = Str::random(48);
        Files::write($this->file('codes', hash('sha256', $code)), Yaml::dump($data + ['expires' => now()->addSeconds(self::CODE_SECONDS)->getTimestamp()]));

        return $code;
    }

    /**
     * Reads and deletes a code, so it works once.
     *
     * @return array<string, mixed>|null
     */
    public function takeCode(string $code): ?array
    {
        return $this->take('codes', $code);
    }

    /**
     * @param  array<string, mixed>  $data  client_id, account, scopes, token_id
     */
    public function createRefresh(array $data): string
    {
        $token = 'wake_rt_'.Str::random(48);
        Files::write($this->file('refresh', hash('sha256', $token)), Yaml::dump($data + ['expires' => now()->addDays(self::REFRESH_DAYS)->getTimestamp()]));

        return $token;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function takeRefresh(string $token): ?array
    {
        return $this->take('refresh', $token);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function take(string $kind, string $secret): ?array
    {
        if ($secret === '' || strlen($secret) > 200) {
            return null;
        }

        $file = $this->file($kind, hash('sha256', $secret));
        $data = $this->read($file);
        @unlink($file);

        return $data !== null && ($data['expires'] ?? 0) > now()->getTimestamp() ? $data : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function read(string $file): ?array
    {
        if (! is_file($file)) {
            return null;
        }

        $data = Yaml::parseFile($file);

        return is_array($data) ? $data : null;
    }

    protected function file(string $kind, string $name): string
    {
        return Paths::data("oauth/{$kind}/{$name}.yml");
    }
}

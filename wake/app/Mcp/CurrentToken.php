<?php

namespace Wake\Mcp;

/**
 * The token of the current MCP request and what it may do.
 */
class CurrentToken
{
    /** @var array<string, mixed>|null */
    protected ?array $record = null;

    public function set(?array $record): void
    {
        $this->record = $record;
    }

    public function record(): ?array
    {
        return $this->record;
    }

    /** What the token owner's role must allow for each scope. */
    public const CAPABILITIES = [
        'stats:read' => ['stats.view'],
        'content:read' => ['content.write'],
        'content:write' => ['content.write'],
        'content:publish' => ['content.publish', 'content.publish_all'],
    ];

    /**
     * The token has the scope, and its owner's role still allows it. A
     * token never does more than the person who made it may do today.
     */
    public function can(string $scope): bool
    {
        if (! in_array($scope, $this->record['scopes'] ?? [], true)) {
            return false;
        }

        $user = auth()->user();

        return $user !== null && collect(self::CAPABILITIES[$scope] ?? [])->contains(fn (string $capability) => $user->can($capability));
    }

    public function name(): string
    {
        return (string) ($this->record['name'] ?? '');
    }
}

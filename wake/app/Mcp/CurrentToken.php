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

    public function can(string $scope): bool
    {
        return in_array($scope, $this->record['scopes'] ?? [], true);
    }

    public function name(): string
    {
        return (string) ($this->record['name'] ?? '');
    }
}

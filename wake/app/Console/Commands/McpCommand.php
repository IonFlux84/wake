<?php

namespace Wake\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Wake\Accounts\Tokens;
use Wake\Mcp\CurrentToken;

class McpCommand extends Command
{
    protected $signature = 'wake:mcp {--token= : A personal token from the panel (or set WAKE_TOKEN)}';

    protected $description = 'Start the MCP server over stdio for a local AI assistant';

    public function handle(Tokens $tokens, CurrentToken $current): int
    {
        $found = $tokens->find((string) ($this->option('token') ?: env('WAKE_TOKEN')));

        if ($found === null) {
            $this->getOutput()->getErrorStyle()->writeln('A valid token is required: --token=wake_… or WAKE_TOKEN. Create one in the panel under Assistants.');

            return self::FAILURE;
        }

        [$account, $record] = $found;
        Auth::setUser($account);
        $current->set($record);

        return $this->call('mcp:start', ['handle' => 'wake']);
    }
}

<?php

namespace Wake\Content;

use Carbon\CarbonImmutable;
use Wake\Mcp\CurrentToken;
use Wake\Support\Paths;

/**
 * Who changed what, and how: data/history/{year}-{month}.log, one JSON
 * line per change. Changes made by AI assistants are marked with "mcp" and
 * the name of the token.
 */
class History
{
    public function record(string $action, string $id, ?string $language = null): void
    {
        $token = app(CurrentToken::class);
        $account = auth()->user();

        $line = json_encode(array_filter([
            'time' => CarbonImmutable::now()->toIso8601String(),
            'action' => $action,
            'id' => $id,
            'language' => $language,
            'account' => $account?->getAuthIdentifier(),
            'via' => $token->record() !== null ? 'mcp' : (app()->runningInConsole() && ! app()->runningUnitTests() ? 'cli' : 'panel'),
            'token' => $token->record() !== null ? $token->name() : null,
        ], fn ($v) => $v !== null), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        @mkdir(Paths::data('history'), 0775, true);
        file_put_contents(Paths::data('history/'.CarbonImmutable::now()->format('Y-m').'.log'), $line."\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * The latest changes, newest first, optionally for one entry.
     *
     * @return list<array<string, mixed>>
     */
    public function latest(int $limit = 50, ?string $id = null): array
    {
        $files = glob(Paths::data('history/*.log')) ?: [];
        rsort($files);
        $result = [];

        foreach ($files as $file) {
            foreach (array_reverse(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) as $line) {
                $entry = json_decode($line, true);

                if ($entry && ($id === null || $entry['id'] === $id)) {
                    $result[] = $entry;

                    if (count($result) >= $limit) {
                        return $result;
                    }
                }
            }
        }

        return $result;
    }
}

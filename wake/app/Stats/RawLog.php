<?php

namespace Wake\Stats;

use Generator;

/**
 * The raw statistics files in storage/stats/raw, one per day.
 */
class RawLog
{
    public function directory(): string
    {
        return storage_path('stats/raw');
    }

    public function file(string $day): string
    {
        return $this->directory().'/'.$day.'.log';
    }

    /**
     * @return Generator<int, Hit>
     */
    public function hits(string $day): Generator
    {
        $handle = @fopen($this->file($day), 'r');

        if ($handle === false) {
            return;
        }

        try {
            while (($line = fgets($handle)) !== false) {
                if ($hit = Hit::fromLine($line)) {
                    yield $hit;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Hits of the last minutes, read from the end of today's file.
     *
     * @return list<Hit>
     */
    public function recent(int $seconds = 300): array
    {
        $file = $this->file(now()->toDateString());
        $size = (int) @filesize($file);

        if ($size === 0) {
            return [];
        }

        $handle = fopen($file, 'r');
        fseek($handle, max(0, $size - 262144));
        $chunk = (string) stream_get_contents($handle);
        fclose($handle);

        $since = now()->getTimestamp() - $seconds;

        return array_values(array_filter(
            array_map(fn (string $line) => Hit::fromLine($line), explode("\n", $chunk)),
            fn (?Hit $hit) => $hit !== null && $hit->time >= $since,
        ));
    }

    /**
     * @return list<string> days with a raw file, oldest first
     */
    public function days(): array
    {
        $days = array_map(fn (string $file) => basename($file, '.log'), glob($this->directory().'/*.log') ?: []);
        sort($days);

        return $days;
    }
}

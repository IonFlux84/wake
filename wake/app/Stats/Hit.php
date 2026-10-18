<?php

namespace Wake\Stats;

/**
 * One line of a raw statistics file, as written by public/hit.php.
 */
final class Hit
{
    public function __construct(
        public readonly int $time,
        public readonly string $type,
        public readonly string $visitor,
        public readonly string $returning,
        public readonly string $path,
        public readonly string $referrer,
        public readonly string $source,
        public readonly string $medium,
        public readonly string $campaign,
        public readonly string $language,
        public readonly string $country,
        public readonly string $device,
        public readonly string $browser,
        public readonly string $os,
        public readonly string $event,
        public readonly string $label,
        public readonly int $duration,
        public readonly int $scroll,
        public readonly int $level,
    ) {}

    public static function fromLine(string $line): ?self
    {
        $f = explode("\t", rtrim($line, "\n"));

        if (count($f) < 19 || ! ctype_digit($f[0])) {
            return null;
        }

        return new self((int) $f[0], $f[1], $f[2], $f[3], $f[4], $f[5], $f[6], $f[7], $f[8], $f[9], $f[10],
            $f[11], $f[12], $f[13], $f[14], $f[15], (int) $f[16], (int) $f[17], (int) $f[18]);
    }
}

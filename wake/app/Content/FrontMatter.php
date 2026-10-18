<?php

namespace Wake\Content;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads and writes Markdown with YAML front matter:
 *
 *     ---
 *     title: Hello world
 *     ---
 *
 *     The body …
 */
class FrontMatter
{
    /**
     * @return array{0: array<string, mixed>, 1: string} fields and body
     *
     * @throws InvalidContentException
     */
    public static function parse(string $source, string $file = ''): array
    {
        $source = str_replace(["\r\n", "\r"], "\n", preg_replace('/^\x{FEFF}/u', '', $source));

        if (! preg_match('/\A---[ \t]*\n(.*?)^---[ \t]*(?:\n|\z)/ms', $source, $match)) {
            return [[], $source];
        }

        try {
            $fields = Yaml::parse($match[1], Yaml::PARSE_DATETIME) ?? [];
        } catch (ParseException $e) {
            throw new InvalidContentException("Invalid front matter in {$file}: {$e->getMessage()}", previous: $e);
        }

        if (! is_array($fields)) {
            throw new InvalidContentException("The front matter in {$file} must be a list of fields.");
        }

        return [$fields, ltrim(substr($source, strlen($match[0])), "\n")];
    }

    /**
     * Write front matter the way a person would: plain values without
     * quotes where YAML allows it, short lists inline.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function dump(array $fields, string $body): string
    {
        $yaml = static::yaml($fields, 0);

        return "---\n".$yaml."---\n\n".rtrim($body)."\n";
    }

    /**
     * @param  array<mixed>  $values
     */
    protected static function yaml(array $values, int $depth): string
    {
        $indent = str_repeat('  ', $depth);
        $out = '';

        foreach ($values as $key => $value) {
            $prefix = array_is_list($values) ? $indent.'- ' : $indent.static::key((string) $key).':';

            if (is_array($value) && $value !== [] && array_is_list($value) && count(array_filter($value, 'is_array')) === 0
                && strlen(implode(', ', array_map('strval', $value))) < 80) {
                $out .= $prefix.' ['.implode(', ', array_map(fn ($v) => static::scalar($v, true), $value))."]\n";
            } elseif (is_array($value)) {
                $out .= rtrim($prefix, ' ').($value === [] ? " []\n" : "\n".static::yaml($value, $depth + 1));
            } elseif (is_string($value) && str_contains($value, "\n")) {
                $out .= $prefix." |-\n".preg_replace('/^/m', $indent.'  ', rtrim($value))."\n";
            } else {
                $out .= $prefix.' '.static::scalar($value)."\n";
            }
        }

        return $out;
    }

    protected static function key(string $key): string
    {
        return preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.-]*$/', $key) ? $key : static::quote($key);
    }

    protected static function scalar(mixed $value, bool $inFlow = false): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i'),
            default => static::plain((string) $value, $inFlow) ? (string) $value : static::quote((string) $value),
        };
    }

    /**
     * Whether a string can be written without quotes and still reads back
     * as the same string.
     */
    protected static function plain(string $value, bool $inFlow): bool
    {
        if ($value === '' || trim($value) !== $value
            || preg_match('/^[-?:,\[\]{}#&*!|>\'"%@`]/', $value)
            || str_contains($value, ': ') || str_contains($value, ' #') || str_ends_with($value, ':')
            || ($inFlow && preg_match('/[,\[\]{}]/', $value))) {
            return false;
        }

        // Dates with a time stay strings in Wake; other values YAML would turn into something else need quotes.
        if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{1,2}:\d{2})?$/', $value)) {
            return true;
        }

        try {
            return Yaml::parse($value) === $value;
        } catch (ParseException) {
            return false;
        }
    }

    protected static function quote(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}

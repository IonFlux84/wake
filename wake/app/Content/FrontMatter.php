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
     * @param  array<string, mixed>  $fields
     */
    public static function dump(array $fields, string $body): string
    {
        $fields = array_map(
            fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i') : $value,
            $fields,
        );

        $yaml = $fields === [] ? '' : Yaml::dump($fields, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);

        return "---\n".$yaml."---\n\n".rtrim($body)."\n";
    }
}

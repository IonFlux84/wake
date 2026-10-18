<?php

namespace Wake\Content;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Works out the address (URL path without language prefix) of an entry.
 */
class Addresses
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>  $entries  all rows, for parent pages
     */
    public static function path(array $row, string $language, array $entries, ContentType $type, string $default): string
    {
        $slug = static::slug($row, $language, $default);

        if ($type->layout === ContentType::TREE) {
            if ($row['parent'] === null && $row['name'] === 'home') {
                return '';
            }

            $parts = [$slug];
            $parent = $row['parent'];

            while ($parent !== null && isset($entries[$parent])) {
                array_unshift($parts, static::slug($entries[$parent], $language, $default));
                $parent = $entries[$parent]['parent'];
            }

            return implode('/', $parts);
        }

        $date = static::date($row, $language, $default);

        return trim(strtr($type->url, [
            '{slug}' => $slug,
            '{year}' => $date?->format('Y') ?? '',
            '{month}' => $date?->format('m') ?? '',
            '{day}' => $date?->format('d') ?? '',
        ]), '/');
    }

    /**
     * The slug from the front matter, or else from the folder name.
     *
     * @param  array<string, mixed>  $row
     */
    public static function slug(array $row, string $language, string $default): string
    {
        $fields = static::fields($row, $language, $default);

        return Str::slug((string) ($fields['slug'] ?? $row['name']), language: $language) ?: $row['name'];
    }

    /**
     * The publication date: "published" in the front matter, or else the
     * date at the start of the folder name.
     *
     * @param  array<string, mixed>  $row
     */
    public static function date(array $row, string $language, string $default): ?CarbonImmutable
    {
        $value = static::fields($row, $language, $default)['published'] ?? $row['date'];

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return is_int($value)
                ? CarbonImmutable::createFromTimestamp($value, config('app.timezone'))
                : CarbonImmutable::parse((string) $value, config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected static function fields(array $row, string $language, string $default): array
    {
        return ($row['languages'][$language] ?? $row['languages'][$default] ?? reset($row['languages']) ?: [])['fields'] ?? [];
    }
}

<?php

namespace Wake\Markdown\Cards;

use Illuminate\Support\Str;

/**
 * The description of a card from cards/{name}.yml, next to its view. It tells
 * the editor and assistants which settings a card has:
 *
 *     title: { en: Board member, de: Vorstandsmitglied }
 *     description: { en: A person with photo and role. }
 *     hint: 👤                       short sign for the / menu
 *     value: { label: { en: Name }, required: true }   text after the card name
 *     content: true                  whether the card holds Markdown (default)
 *     fields:
 *       role:  { type: text, label: { en: Role } }
 *       photo: { type: image }
 *
 * In Markdown this is "::: member Jane Doe role="Chair" photo="jane.jpg"".
 */
class CardType
{
    public const TYPES = ['text', 'url', 'image', 'select', 'toggle', 'number', 'date', 'color'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(public readonly string $name, public readonly string $source, protected array $data) {}

    public function title(?string $language = null): string
    {
        return $this->pick($this->data['title'] ?? null, $language) ?: Str::headline($this->name);
    }

    public function description(?string $language = null): string
    {
        return $this->pick($this->data['description'] ?? null, $language);
    }

    public function hint(): string
    {
        return mb_substr((string) ($this->data['hint'] ?? '▢'), 0, 3);
    }

    public function hasContent(): bool
    {
        return (bool) ($this->data['content'] ?? true);
    }

    /** Whether the editor offers it in the / menu. */
    public function inMenu(): bool
    {
        return (bool) ($this->data['menu'] ?? true);
    }

    /**
     * The text after the card name, if the card uses it.
     *
     * @return array{label: string, placeholder: string, required: bool}|null
     */
    public function value(?string $language = null): ?array
    {
        $value = $this->data['value'] ?? null;

        if (! is_array($value)) {
            return null;
        }

        return [
            'label' => $this->pick($value['label'] ?? null, $language) ?: __('Text'),
            'placeholder' => $this->pick($value['placeholder'] ?? null, $language),
            'required' => (bool) ($value['required'] ?? false),
        ];
    }

    /**
     * @return array<string, array{type: string, label: string, placeholder: string, required: bool, default: string, options: array<string, string>}>
     */
    public function fields(?string $language = null): array
    {
        $fields = [];

        foreach ((array) ($this->data['fields'] ?? []) as $key => $field) {
            if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9_-]*$/', $key)) {
                continue;
            }

            $field = is_array($field) ? $field : [];
            $type = in_array($field['type'] ?? 'text', self::TYPES, true) ? $field['type'] ?? 'text' : 'text';
            $options = [];

            foreach ((array) ($field['options'] ?? []) as $option => $label) {
                $options[(string) $option] = $this->pick($label, $language) ?: (string) $option;
            }

            $fields[$key] = [
                'type' => $type,
                'label' => $this->pick($field['label'] ?? null, $language) ?: Str::headline($key),
                'placeholder' => $this->pick($field['placeholder'] ?? null, $language),
                'required' => (bool) ($field['required'] ?? false),
                'default' => is_scalar($field['default'] ?? null) ? (is_bool($field['default']) ? ($field['default'] ? 'true' : '') : (string) $field['default']) : '',
                'options' => $options,
            ];
        }

        return $fields;
    }

    /**
     * What the card looks like in Markdown, with placeholders.
     */
    public function example(?string $language = null): string
    {
        $line = '::: '.$this->name;

        if ($value = $this->value($language)) {
            $line .= ' '.($value['placeholder'] ?: '…');
        }

        foreach ($this->fields($language) as $key => $field) {
            $sample = $field['default'] ?: match ($field['type']) {
                'url' => 'https://…',
                'image' => 'photo.jpg',
                'select' => (string) array_key_first($field['options']),
                'toggle' => 'true',
                'number' => '1',
                'date' => '2026-01-01',
                'color' => '#000000',
                default => $field['placeholder'] ?: '…',
            };
            $line .= ' '.$key.'="'.$sample.'"';
        }

        return $line."\n".($this->hasContent() ? "Text in Markdown.\n" : '').':::';
    }

    /**
     * Required settings that a card in the text leaves empty.
     *
     * @return list<string> labels
     */
    public function missing(Card $card, ?string $language = null): array
    {
        $missing = [];

        if (($value = $this->value($language)) && $value['required'] && $card->value === '') {
            $missing[] = $value['label'];
        }

        foreach ($this->fields($language) as $key => $field) {
            if ($field['required'] && trim($card->attributes[$key] ?? '') === '') {
                $missing[] = $field['label'];
            }
        }

        return $missing;
    }

    /**
     * Everything the editor and assistants need, in one language.
     *
     * @return array<string, mixed>
     */
    public function toArray(?string $language = null): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title($language),
            'description' => $this->description($language),
            'hint' => $this->hint(),
            'source' => $this->source,
            'value' => $this->value($language),
            'content' => $this->hasContent(),
            'menu' => $this->inMenu(),
            'fields' => collect($this->fields($language))->map(fn (array $field, string $key) => ['key' => $key] + $field)->values()->all(),
            'example' => $this->example($language),
        ];
    }

    protected function pick(mixed $text, ?string $language): string
    {
        if (! is_array($text)) {
            return trim((string) $text);
        }

        $language ??= app()->getLocale();

        return trim((string) ($text[$language] ?? $text['en'] ?? reset($text) ?: ''));
    }
}

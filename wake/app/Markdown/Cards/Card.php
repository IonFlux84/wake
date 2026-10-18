<?php

namespace Wake\Markdown\Cards;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A card block in Markdown:
 *
 *     ::: callout 💡
 *     Text inside the card, written in **Markdown**.
 *     :::
 */
class Card extends AbstractBlock
{
    /**
     * @param  array<string, string>  $attributes  key="value" pairs from the opening line
     * @param  string  $value  the rest of the opening line
     */
    public function __construct(
        public readonly string $name,
        public readonly array $attributes = [],
        public readonly string $value = '',
    ) {
        parent::__construct();
    }

    /**
     * Split the text after the card name into key="value" pairs and the rest.
     *
     * @return array{0: array<string, string>, 1: string}
     */
    public static function parseArguments(string $arguments): array
    {
        $attributes = [];

        $rest = preg_replace_callback(
            '/([a-z][a-z0-9_-]*)=(?:"([^"]*)"|\'([^\']*)\'|(\S+))/i',
            function (array $m) use (&$attributes) {
                $attributes[strtolower($m[1])] = $m[2] !== '' ? $m[2] : ($m[3] ?? '').($m[4] ?? '');

                return '';
            },
            $arguments,
        );

        return [$attributes, trim(preg_replace('/\s+/', ' ', $rest))];
    }
}

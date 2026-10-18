<?php

namespace Wake\Markdown\Cards;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

class CardStartParser implements BlockStartParserInterface
{
    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented() || ! preg_match('/^:::[ \t]*([a-z][a-z0-9-]*)(.*)$/i', $cursor->getRemainder(), $m)) {
            return BlockStart::none();
        }

        // "::: name … :::" on one line is a whole card without content.
        $closed = (bool) preg_match('/^(.*?)\s*:::[ \t]*$/', $m[2], $one);
        [$attributes, $value] = Card::parseArguments($closed ? $one[1] : $m[2]);
        $cursor->advanceToEnd();

        return BlockStart::of(new CardParser(new Card(strtolower($m[1]), $attributes, $value), $closed))->at($cursor);
    }
}

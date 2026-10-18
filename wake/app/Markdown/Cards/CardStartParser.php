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

        [$attributes, $value] = Card::parseArguments($m[2]);
        $cursor->advanceToEnd();

        return BlockStart::of(new CardParser(new Card(strtolower($m[1]), $attributes, $value)))->at($cursor);
    }
}

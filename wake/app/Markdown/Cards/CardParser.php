<?php

namespace Wake\Markdown\Cards;

use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

class CardParser extends AbstractBlockContinueParser
{
    /**
     * @param  bool  $closed  the card was opened and closed on one line
     */
    public function __construct(protected Card $card, protected bool $closed = false) {}

    public function getBlock(): Card
    {
        return $this->card;
    }

    public function isContainer(): bool
    {
        return true;
    }

    public function canContain(AbstractBlock $childBlock): bool
    {
        return ! $childBlock instanceof Card;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        if ($this->closed) {
            return BlockContinue::none();
        }

        if (! $cursor->isIndented() && preg_match('/^:::[ \t]*$/', $cursor->getRemainder())) {
            $cursor->advanceToEnd();

            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }
}

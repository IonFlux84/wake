<?php

namespace Wake\Markdown\Cards;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;

class CardExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addBlockStartParser(new CardStartParser, 80)
            ->addRenderer(Card::class, new CardRenderer);
    }
}

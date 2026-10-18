<?php

namespace Wake\Markdown\Cards;

use Closure;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;

class CardExtension implements ExtensionInterface
{
    /**
     * @param  Closure|null  $entry  returns the entry being rendered, for image fields
     */
    public function __construct(protected ?Closure $entry = null) {}

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addBlockStartParser(new CardStartParser, 80)
            ->addRenderer(Card::class, new CardRenderer($this->entry));
    }
}

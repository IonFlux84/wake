<?php

namespace Wake\Markdown\Cards;

use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Renders a card with the theme's view cards/{name}.blade.php, then Wake's
 * built-in view, and otherwise as a plain <div class="card card-{name}">.
 */
class CardRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        /** @var Card $node */
        $content = $childRenderer->renderNodes($node->children());

        foreach (["theme::cards.{$node->name}", "wake::cards.{$node->name}"] as $view) {
            if (View::exists($view)) {
                return trim(View::make($view, [
                    'name' => $node->name,
                    'value' => $node->value,
                    'attributes' => $node->attributes,
                    'content' => new HtmlString($content),
                ])->render());
            }
        }

        return (string) new HtmlElement('div', [
            'class' => 'card card-'.$node->name,
        ], $content === '' ? '' : "\n".$content."\n");
    }
}

<?php

namespace Wake\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Inline\Text;
use Wake\Content\Entry;
use Wake\Markdown\Cards\CardExtension;

/**
 * Turns the Markdown body of an entry into HTML. Besides CommonMark and
 * GitHub's extensions (tables, task lists, strikethrough, autolinks) it knows
 * Wake's cards and resolves relative file names to media URLs, so
 * ![](photo.jpg) works for a photo in the entry's folder.
 */
class Markdown
{
    protected ?MarkdownConverter $converter = null;

    protected ?Entry $entry = null;

    public function render(string $markdown, ?Entry $entry = null): string
    {
        $previous = $this->entry;
        $this->entry = $entry;

        try {
            return (string) $this->converter()->convert($markdown);
        } finally {
            $this->entry = $previous;
        }
    }

    protected function converter(): MarkdownConverter
    {
        if ($this->converter !== null) {
            return $this->converter;
        }

        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new CardExtension);
        $environment->addEventListener(DocumentParsedEvent::class, $this->resolveFiles(...));

        return $this->converter = new MarkdownConverter($environment);
    }

    protected function resolveFiles(DocumentParsedEvent $event): void
    {
        if ($this->entry === null) {
            return;
        }

        foreach ($event->getDocument()->iterator() as $node) {
            if (! $node instanceof Image && ! $node instanceof Link) {
                continue;
            }

            $url = $node->getUrl();

            if (preg_match('#^([a-z][a-z0-9+.-]*:|/|\#|\?)#i', $url)) {
                continue;
            }

            if (! $file = $this->entry->file(rawurldecode($url))) {
                continue;
            }

            if ($node instanceof Image) {
                $node->setUrl($file->url(1280));

                if ($node->firstChild() === null && $file->alt() !== '') {
                    $node->appendChild(new Text($file->alt()));
                }
            } else {
                $node->setUrl($file->url());
            }
        }
    }
}

<?php

namespace Wake\Content;

use Wake\Localization\Languages;
use Wake\Support\Files;
use Wake\Support\Paths;

/**
 * The imprint and the privacy policy every site needs. Wake creates both as
 * drafts from templates in resources/legal/ (German and English, other
 * languages get the English text), at the ids in the settings legal.imprint
 * and legal.privacy. Nothing is published by itself: the owner fills in the
 * placeholders and publishes.
 */
class LegalPages
{
    public const KINDS = ['imprint', 'privacy'];

    public function __construct(protected Index $index, protected Languages $languages) {}

    /**
     * Creates the pages that do not exist yet.
     *
     * @return list<string> ids of the pages created
     */
    public function ensure(): array
    {
        $created = [];

        foreach (self::KINDS as $kind) {
            $id = (string) config('wake.legal.'.$kind, '');

            if (! preg_match('#^pages/[a-z0-9-]+(/[a-z0-9-]+)*$#', $id) || $this->index->find($id) || is_dir(Paths::content($id))) {
                continue;
            }

            foreach ($this->languages->codes() as $code) {
                $template = resource_path("legal/{$kind}.{$code}.md");
                $template = is_file($template) ? $template : resource_path("legal/{$kind}.en.md");
                [$fields, $body] = FrontMatter::parse((string) file_get_contents($template));
                $file = $code === $this->languages->default() ? 'content.md' : "content.{$code}.md";

                Files::write(Paths::content("{$id}/{$file}"), FrontMatter::dump($fields + ['status' => 'draft'], $body));
            }

            $created[] = $id;
        }

        if ($created !== []) {
            $this->index->refresh();
        }

        return $created;
    }

    /**
     * What is left to do: "missing" (no page), "draft" (not published) or
     * nothing, per kind.
     *
     * @return array<string, string>
     */
    public function todo(): array
    {
        $todo = [];

        foreach (self::KINDS as $kind) {
            $id = (string) config('wake.legal.'.$kind, '');
            $page = $id !== '' ? (new Query)->find($id) : null;

            if ($page === null) {
                $todo[$kind] = 'missing';
            } elseif (! $page->isPublished()) {
                $todo[$kind] = 'draft';
            }
        }

        return $todo;
    }
}

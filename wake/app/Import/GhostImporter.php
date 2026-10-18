<?php

namespace Wake\Import;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use League\HTMLToMarkdown\HtmlConverter;
use Throwable;
use Wake\Content\Index;
use Wake\Content\Query;
use Wake\Content\Writer;
use Wake\Localization\Languages;

/**
 * Imports posts, pages and tags from a Ghost export (Settings → Labs →
 * Export in Ghost). HTML becomes Markdown, Ghost's cards become Wake's
 * cards, and images can be fetched from the old site.
 */
class GhostImporter
{
    /** @var list<string> */
    public array $warnings = [];

    /** @var array{posts: int, pages: int, tags: int, images: int, skipped: int} */
    public array $counts = ['posts' => 0, 'pages' => 0, 'tags' => 0, 'images' => 0, 'skipped' => 0];

    protected ?string $siteUrl = null;

    public function __construct(protected Writer $writer, protected Languages $languages, protected Index $index) {}

    /**
     * @param  string|null  $siteUrl  address of the old Ghost site, to download images from
     */
    public function import(string $json, ?string $siteUrl = null, ?string $language = null): static
    {
        $export = json_decode($json, true);
        $data = $export['db'][0]['data'] ?? $export['data'] ?? null;

        if (! is_array($data) || ! isset($data['posts'])) {
            throw new InvalidArgumentException('This is not a Ghost export. In Ghost, use Settings → Labs → Export your content.');
        }

        $this->siteUrl = $siteUrl ? rtrim($siteUrl, '/') : null;
        $language ??= $this->languages->default();

        $tags = collect($data['tags'] ?? [])->keyBy('id');
        $postTags = collect($data['posts_tags'] ?? [])->groupBy('post_id');
        $users = collect($data['users'] ?? [])->keyBy('id');
        $postAuthors = collect($data['posts_authors'] ?? [])->groupBy('post_id');

        // Tags exist as soon as a post uses them; only tags with a
        // description or a name that differs from the slug get a file.
        foreach ($tags as $tag) {
            $needsFile = ! empty($tag['description']) || Str::slug((string) $tag['name']) !== $tag['slug'];

            if (str_starts_with((string) $tag['slug'], 'hash-') || ! $needsFile || $this->index->find('tags/'.$tag['slug'])) {
                continue;
            }

            $this->writer->create('tag', ['title' => $tag['name'], 'slug' => $tag['slug']], (string) ($tag['description'] ?? ''), $language);
            $this->counts['tags']++;
        }

        foreach ($data['posts'] as $post) {
            try {
                $this->importPost($post, $language, $tags, $postTags, $users, $postAuthors);
            } catch (Throwable $e) {
                $this->counts['skipped']++;
                $this->warnings[] = sprintf('"%s" was skipped: %s', $post['title'] ?? $post['id'] ?? '?', $e->getMessage());
            }
        }

        return $this;
    }

    protected function importPost(array $post, string $language, $tags, $postTags, $users, $postAuthors): void
    {
        $type = ($post['type'] ?? 'post') === 'page' ? 'page' : 'post';

        if (($post['status'] ?? '') === 'sent' || ! empty($post['email_only'])) {
            $this->counts['skipped']++;

            return;
        }

        if ((new Query($type))->in($language)->where('slug', $post['slug'])->exists()) {
            $this->counts['skipped']++;
            $this->warnings[] = sprintf('"%s" exists already and was not imported again.', $post['title']);

            return;
        }

        $tagSlugs = collect($postTags->get($post['id'], []))
            ->sortBy('sort_order')
            ->map(fn ($link) => $tags->get($link['tag_id']))
            ->filter(fn ($tag) => $tag && ($tag['visibility'] ?? 'public') === 'public')
            ->pluck('slug')->values()->all();

        $author = collect($postAuthors->get($post['id'], []))->sortBy('sort_order')->first();
        $authorName = $author ? ($users->get($author['author_id'])['name'] ?? null) : null;

        $status = match ($post['status'] ?? 'draft') {
            'published' => 'published',
            'scheduled' => 'scheduled',
            default => 'draft',
        };

        $publishedAt = $post['published_at'] ?? null;

        $fields = array_filter([
            'title' => $post['title'],
            'slug' => $post['slug'],
            'status' => $status,
            'published' => $publishedAt ? CarbonImmutable::parse($publishedAt)->setTimezone(config('app.timezone'))->format('Y-m-d H:i') : null,
            'tags' => $type === 'post' ? $tagSlugs : null,
            'excerpt' => $post['custom_excerpt'] ?? null,
            'featured' => ! empty($post['featured']) ? true : null,
            'seo' => array_filter(['title' => $post['meta_title'] ?? null, 'description' => $post['meta_description'] ?? null]) ?: null,
            'imported_author' => $authorName,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);

        $entry = $this->writer->create($type, $fields, '', $language);

        $images = [];
        $body = $this->markdown((string) ($post['html'] ?? ''), $images);

        foreach ($images as $url => $placeholder) {
            $name = $this->download($url, $entry->id());
            $body = str_replace($placeholder, $name ?? $this->absolute($url), $body);
        }

        if (! empty($post['feature_image'])) {
            if ($cover = $this->download($post['feature_image'], $entry->id())) {
                $fields['cover'] = $cover;
            }
        }

        $this->writer->update($entry->id(), $language, ['cover' => $fields['cover'] ?? null], $body);
        $this->counts[$type === 'page' ? 'pages' : 'posts']++;
    }

    /**
     * Ghost HTML to Markdown with Wake cards. Image URLs are replaced by
     * placeholders that are filled in once the images are downloaded.
     *
     * @param  array<string, string>  $images  collected: url => placeholder
     */
    public function markdown(string $html, array &$images = []): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8"><div id="wake-root">'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);
        $cards = [];

        $replace = function (DOMElement $node, string $markdown) use ($dom, &$cards) {
            $key = 'WAKECARD'.count($cards).'X';
            $cards[$key] = $markdown;
            $node->parentNode->replaceChild($dom->createElement('p', $key), $node);
        };

        foreach (iterator_to_array($xpath->query('//*[contains(@class,"kg-callout-card")]')) as $node) {
            $emoji = trim($xpath->query('.//*[contains(@class,"kg-callout-emoji")]', $node)->item(0)?->textContent ?? '💡');
            $text = $xpath->query('.//*[contains(@class,"kg-callout-text")]', $node)->item(0);
            $inner = $text ? $this->innerHtml($text) : '';
            $replace($node, "::: callout {$emoji}\n".trim($this->converter()->convert($inner))."\n:::");
        }

        foreach (iterator_to_array($xpath->query('//*[contains(@class,"kg-button-card")]')) as $node) {
            $link = $xpath->query('.//a', $node)->item(0);
            $replace($node, '::: button href="'.($link?->getAttribute('href') ?? '/').'"'."\n".trim($link?->textContent ?? '')."\n:::");
        }

        foreach (iterator_to_array($xpath->query('//figure[contains(@class,"kg-embed-card")] | //iframe')) as $node) {
            $iframe = $node->nodeName === 'iframe' ? $node : $xpath->query('.//iframe', $node)->item(0);
            $src = $iframe?->getAttribute('src');

            if ($src && $node->parentNode) {
                $caption = $xpath->query('.//figcaption', $node)->item(0)?->textContent;
                $replace($node, "::: embed {$src}\n".trim((string) $caption)."\n:::");
            }
        }

        foreach (iterator_to_array($xpath->query('//*[contains(@class,"kg-bookmark-card")]')) as $node) {
            $link = $xpath->query('.//a', $node)->item(0);
            $title = trim($xpath->query('.//*[contains(@class,"kg-bookmark-title")]', $node)->item(0)?->textContent ?? '');
            $replace($node, '['.($title ?: $link?->getAttribute('href')).']('.$link?->getAttribute('href').')');
        }

        foreach (iterator_to_array($xpath->query('//hr')) as $node) {
            $replace($node, "::: divider\n:::");
        }

        foreach (iterator_to_array($xpath->query('//img')) as $node) {
            $src = $node->getAttribute('src');

            if ($src !== '') {
                $images[$src] ??= 'WAKEIMG'.count($images).'X';
                $node->setAttribute('src', $images[$src]);
                $node->removeAttribute('srcset');
            }
        }

        // Keep the caption of images as plain text under them.
        foreach (iterator_to_array($xpath->query('//figure[.//img]')) as $node) {
            $img = $xpath->query('.//img', $node)->item(0);
            $caption = trim($xpath->query('.//figcaption', $node)->item(0)?->textContent ?? '');
            $replace($node, '!['.$img->getAttribute('alt').']('.$img->getAttribute('src').')'.($caption !== '' ? "\n\n*{$caption}*" : ''));
        }

        $root = $dom->getElementById('wake-root');
        $markdown = $this->converter()->convert($this->innerHtml($root));

        foreach ($cards as $key => $card) {
            $markdown = str_replace($key, $card, $markdown);
        }

        return trim(preg_replace("/\n{3,}/", "\n\n", $markdown))."\n";
    }

    protected function download(string $url, string $id): ?string
    {
        $absolute = $this->absolute($url);

        if ($absolute === null || $this->siteUrl === null) {
            return null;
        }

        try {
            $response = Http::timeout(20)->get($absolute);

            if (! $response->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
                $this->warnings[] = "Image not found: {$absolute}";

                return null;
            }

            $temp = tempnam(sys_get_temp_dir(), 'wake');
            file_put_contents($temp, $response->body());
            $name = $this->writer->addMedia($id, basename(parse_url($absolute, PHP_URL_PATH)) ?: 'image.jpg', $temp);
            @unlink($temp);
            $this->counts['images']++;

            return $name;
        } catch (Throwable $e) {
            $this->warnings[] = "Image could not be downloaded: {$absolute}";

            return null;
        }
    }

    protected function absolute(string $url): ?string
    {
        $url = str_replace('__GHOST_URL__', (string) $this->siteUrl, $url);

        if (str_starts_with($url, '/') && $this->siteUrl) {
            $url = $this->siteUrl.$url;
        }

        return preg_match('#^https?://#', $url) ? $url : null;
    }

    protected function innerHtml(DOMElement $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }

        return $html;
    }

    protected function converter(): HtmlConverter
    {
        return new HtmlConverter(['strip_tags' => true, 'header_style' => 'atx', 'hard_break' => false, 'remove_nodes' => 'script style']);
    }
}

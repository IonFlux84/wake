<?php

namespace Wake\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Wake\Content\Categories;
use Wake\Content\ContentTypes;
use Wake\Content\Entry;
use Wake\Content\Query;
use Wake\Content\Site;
use Wake\Localization\Languages;
use Wake\Mcp\CurrentToken;
use Wake\Stats\Stats;

/**
 * Read-only JSON API for headless use: /wake/api/v1/…
 * Published content is public; drafts and statistics need a token.
 */
class ContentApiController
{
    public function site(Site $site, Languages $languages, ContentTypes $types): JsonResponse
    {
        return response()->json(['data' => [
            'title' => $site->title(),
            'description' => $site->description(),
            'url' => url('/'),
            'languages' => $languages->all(),
            'default_language' => $languages->default(),
            'types' => array_keys($types->all()),
        ]]);
    }

    public function entries(Request $request, ContentTypes $types): JsonResponse
    {
        $data = $request->validate([
            'type' => 'nullable|string',
            'language' => 'nullable|string|max:10',
            'tag' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:60',
            'status' => 'nullable|in:published,draft,scheduled,any',
            'limit' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'body' => 'nullable|boolean',
        ]);

        $type = $data['type'] ?? 'post';
        abort_unless($types->get($type), 404, 'Unknown content type.');

        $query = (new Query($type))->in($this->language($data['language'] ?? null));
        $status = $data['status'] ?? 'published';

        if ($status !== 'published' && ! app(CurrentToken::class)->can('content:read')) {
            abort(403, 'Drafts need a token with the scope content:read.');
        }

        match ($status) {
            'published' => $query->published(),
            'draft' => $query->drafts(),
            'scheduled' => $query->scheduled(),
            default => null,
        };

        if (! empty($data['category'])) {
            $category = app(Categories::class)->get($data['category']);
            $query->category($data['category']);

            if ($category?->dateField() && ($data['status'] ?? 'published') === 'published') {
                $query->upcoming($category->dateField());
            }
        }

        if (! empty($data['tag'])) {
            $query->tag($data['tag']);
        }

        $page = $query->paginate($data['limit'] ?? 10, $data['page'] ?? 1);
        $withBody = (bool) ($data['body'] ?? false);

        return response()->json([
            'data' => collect($page->items())->map(fn (Entry $e) => $this->entryData($e, $withBody))->values(),
            'meta' => ['page' => $page->currentPage(), 'pages' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function entry(Request $request): JsonResponse
    {
        $entry = (new Query)->in($this->language($request->query('language')))->find((string) $request->query('id'));

        abort_if($entry === null, 404, 'Not found.');
        abort_if(! $entry->isPublished() && ! app(CurrentToken::class)->can('content:read'), 404, 'Not found.');

        return response()->json(['data' => $this->entryData($entry, true)]);
    }

    public function stats(Request $request, Stats $stats): JsonResponse
    {
        abort_unless(app(CurrentToken::class)->can('stats:read'), 403, 'Statistics need a token with the scope stats:read.');

        $period = in_array($request->query('period'), ['today', 'yesterday', '7d', '30d', '90d', '12m', 'month'], true) ? $request->query('period') : '7d';

        return response()->json(['data' => $stats->period($period)]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function entryData(Entry $entry, bool $withBody): array
    {
        $image = $entry->image();

        return array_filter([
            'id' => $entry->id(),
            'type' => $entry->type(),
            'language' => $entry->language(),
            'title' => $entry->title(),
            'slug' => $entry->slug(),
            'url' => $entry->url(),
            'status' => $entry->isScheduled() ? 'scheduled' : ($entry->isPublished() ? 'published' : 'draft'),
            'date' => $entry->date()?->toIso8601String(),
            'modified' => $entry->modifiedAt()->toIso8601String(),
            'excerpt' => $entry->excerpt(),
            'category' => $entry->category() ? ['key' => $entry->category()->key, 'title' => $entry->category()->title($entry->language()), 'url' => $entry->category()->url($entry->language())] : null,
            'tags' => $entry->tags()->map(fn ($t) => ['slug' => $t->slug(), 'title' => $t->title()])->values()->all(),
            'cover' => $image?->isImage() ? ['url' => $image->url(), 'srcset' => $image->srcset(), 'width' => $image->width(), 'height' => $image->height()] : null,
            'thumbnail' => ($thumb = $entry->thumbnail()) ? ['url' => $thumb->url(), 'srcset' => $thumb->srcset(), 'width' => $thumb->width(), 'height' => $thumb->height()] : null,
            'translations' => collect($entry->translations())->mapWithKeys(fn ($code) => [$code => $entry->translation($code)->url()])->all(),
            'fields' => $entry->fields(),
            'markdown' => $withBody ? $entry->body() : null,
            'html' => $withBody ? (string) $entry->content() : null,
        ], fn ($value) => $value !== null);
    }

    protected function language(?string $language): string
    {
        return app(Languages::class)->exists($language) ? $language : app(Languages::class)->default();
    }
}

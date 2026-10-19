<?php

namespace RedirectScout;

use Wake\Cache\PageCache;
use Wake\Content\Query;
use Wake\Content\Redirects;
use Wake\Media\Media;
use Wake\Routing\Resolver;

/**
 * Saves the target of an old address in settings/redirects.yml, checked
 * the same way as on the panel's Redirects page.
 */
class Redirector
{
    public function __construct(protected Redirects $redirects, protected Resolver $resolver, protected Store $store, protected Checker $checker, protected Suggester $suggester) {}

    /**
     * @return string|null an error, or null when saved
     */
    public function save(string $path, string $target, bool $flush = true): ?string
    {
        $target = trim($target);
        [$language, $rest] = $this->resolver->split($path);

        if ($rest === '') {
            return __('The home page cannot be redirected.');
        }

        $error = match (Redirects::kind($target)) {
            'entry' => $target === '' || ! (new Query)->find($target) ? __('Choose an entry, a path like /blog, a file like media/flyer.pdf, a full address starting with https:// or "gone".') : null,
            'media' => Media::find($target) ? null : __('There is no such file in the media library.'),
            'path' => trim($target, '/') === trim($path, '/') ? __('An address cannot lead to itself.') : null,
            default => null,
        };

        if ($error !== null) {
            return $error;
        }

        $this->redirects->add($language, $rest, $target);
        $this->refresh($path, ['ignored' => false]);

        if ($flush) {
            app(PageCache::class)->flush();
        }

        return null;
    }

    public function remove(string $path): void
    {
        [$language, $rest] = $this->resolver->split($path);
        $this->redirects->remove($language, $rest);
        $this->refresh($path);
        app(PageCache::class)->flush();
    }

    /**
     * Looks at one address again and saves what this site answers now.
     *
     * @param  array<string, mixed>  $values
     */
    public function refresh(string $path, array $values = []): void
    {
        $this->store->reload();
        $result = $this->checker->check($path);
        $suggestion = $result['state'] === 'missing' ? $this->suggester->suggest($path, $this->store->address($path)['title'] ?? null) : null;

        $this->store->remember($path, [...$result, 'suggestion' => $suggestion, ...$values]);
        $this->store->save();
    }

    public function ignore(string $path, bool $ignored = true): void
    {
        $this->store->reload();
        $this->store->remember($path, ['ignored' => $ignored]);
        $this->store->save();
    }
}

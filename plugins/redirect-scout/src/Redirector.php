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

    /**
     * Saves a pattern like "strasse/*" for every address below a folder,
     * and looks at those addresses again.
     *
     * @return string|null an error, or null when saved
     */
    public function saveRule(string $folder, string $target): ?string
    {
        $folder = trim($folder, '/*');
        [$language, $rest] = $this->resolver->split($folder);
        $target = trim($target);

        if ($rest === '') {
            return __('A rule needs a folder, like strasse/.');
        }

        $error = match (Redirects::kind($target)) {
            'entry' => $target === '' || ! (new Query)->find($target) ? __('Choose an entry, a path like /blog, a file like media/flyer.pdf, a full address starting with https:// or "gone".') : null,
            'media' => Media::find($target) ? null : __('There is no such file in the media library.'),
            'path' => str_starts_with(trim($target, '/*').'/', $rest.'/') ? __('An address cannot lead to itself.') : null,
            default => null,
        };

        if ($error !== null) {
            return $error;
        }

        $this->redirects->add($language, $rest.'/*', $target);

        foreach (array_keys($this->store->addresses()) as $path) {
            if (str_starts_with($path.'/', $folder.'/')) {
                $this->refresh((string) $path);
            }
        }

        app(PageCache::class)->flush();

        return null;
    }

    /**
     * A target as people read it: a title or address, and a link.
     *
     * @return array{label: string, url: ?string}
     */
    public static function describe(string $target): array
    {
        return match (Redirects::kind($target)) {
            'gone' => ['label' => __('Gone (410)'), 'url' => null],
            'url' => ['label' => $target, 'url' => $target],
            'path' => ['label' => $target, 'url' => url(str_replace('*', '', $target))],
            'media' => ['label' => basename($target), 'url' => Media::find($target)?->url()],
            default => ($entry = (new Query)->find($target))
                ? ['label' => $entry->title(), 'url' => $entry->url()]
                : ['label' => $target, 'url' => null],
        };
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

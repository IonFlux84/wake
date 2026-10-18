<?php

namespace Wake\Media;

use Wake\Accounts\Account;
use Wake\Accounts\Permissions;
use Wake\Content\Entry;
use Wake\Content\Index;
use Wake\Content\Query;
use Wake\Support\Files;

/**
 * Who may see a file of the media library. A file is public when no entry
 * uses it, or when at least one published entry does. A file that only drafts
 * (or scheduled entries) use stays private: Wake serves it to team members who
 * may read one of those entries, and never copies it into public/media, so
 * the web server cannot hand it out by itself.
 */
class MediaAccess
{
    public static function isPublic(string $path): bool
    {
        $entries = self::usedBy($path);

        if ($entries === []) {
            return true;
        }

        foreach ($entries as $entry) {
            foreach ($entry->translations() as $language) {
                if ($entry->translation($language)?->isPublished()) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function mayView(?Account $account, string $path): bool
    {
        if (self::isPublic($path)) {
            return true;
        }

        if ($account === null) {
            return false;
        }

        foreach (self::usedBy($path) as $entry) {
            if (Permissions::mayRead($account, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes the public copies of files that became private, e.g. when the
     * only entry showing them was taken offline or deleted. Runs whenever
     * content changes.
     */
    public static function prune(): void
    {
        $index = app(Index::class);

        foreach (array_keys($index->allMedia()) as $path) {
            $folder = public_path('media/'.substr(md5('media/'.$path), 0, 10));

            if (is_dir($folder) && ! self::isPublic($path)) {
                Files::deleteDirectory($folder);
            }
        }
    }

    /**
     * @return list<Entry>
     */
    protected static function usedBy(string $path): array
    {
        $query = new Query;

        return array_values(array_filter(array_map(fn (string $id) => $query->find($id), app(Index::class)->mediaUsage($path))));
    }
}

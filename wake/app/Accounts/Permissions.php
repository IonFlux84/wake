<?php

namespace Wake\Accounts;

use Wake\Content\Entry;
use Wake\Content\Index;
use Wake\Content\Query;

/**
 * Who may do what with content (see docs/reference/roles.md). The panel and
 * the MCP server ask here, so both follow the same rules.
 */
class Permissions
{
    /**
     * Published entries are for everyone. Drafts and scheduled entries of
     * others only for people who see all entries in the panel, too.
     */
    public static function mayRead(?Account $account, Entry $entry): bool
    {
        if ($entry->isPublished()) {
            return true;
        }

        return $account !== null && ($entry->get('author') === $account->id()
            || $account->can('content.edit_all') || $account->can('content.publish'));
    }

    public static function mayEdit(Account $account, ?Entry $entry): bool
    {
        if ($entry === null) {
            return true;
        }

        $own = $entry->get('author') === $account->id();

        return $account->can('content.edit_all')
            || ($own && ($account->can('content.publish') || ($entry->isDraft() && $account->can('content.write'))));
    }

    public static function mayPublish(Account $account, ?Entry $entry): bool
    {
        if ($entry !== null && $entry->get('author') !== $account->id()) {
            return $account->can('content.publish_all');
        }

        return $account->can('content.publish') || $account->can('content.publish_all');
    }

    public static function mayDelete(Account $account, ?Entry $entry): bool
    {
        return $entry !== null && ($account->can('content.delete_all')
            || ($entry->get('author') === $account->id() && $entry->isDraft() && $account->can('content.write')));
    }

    /**
     * Files of the media library are shared. Details may be changed by people
     * who may edit every entry that uses the file, plus the entry whose
     * folder it was uploaded to ("media/posts/x/…" belongs to posts/x).
     */
    public static function mayEditMedia(Account $account, string $path): bool
    {
        if ($account->can('content.edit_all')) {
            return true;
        }

        if (! $account->can('content.write') && ! $account->can('content.publish')) {
            return false;
        }

        $query = new Query;
        $ids = app(Index::class)->mediaUsage($path);

        if (($owner = $query->find(dirname($path))) !== null) {
            $ids[] = $owner->id();
        }

        foreach (array_unique($ids) as $id) {
            if (! self::mayEdit($account, $query->find($id))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Deleting a file that is in use breaks texts, so only people who may
     * delete everything do that; others may delete unused files they may edit.
     */
    public static function mayDeleteMedia(Account $account, string $path): bool
    {
        return $account->can('content.delete_all')
            || (app(Index::class)->mediaUsage($path) === [] && self::mayEditMedia($account, $path));
    }
}

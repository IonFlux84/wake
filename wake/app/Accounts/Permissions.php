<?php

namespace Wake\Accounts;

use Wake\Content\Entry;

/**
 * Who may do what with content (see docs/reference/roles.md). The panel and
 * the MCP server ask here, so both follow the same rules.
 */
class Permissions
{
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
}

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

        $role = $account->role();
        $own = $entry->get('author') === $account->id();

        return $role->atLeast(Role::Editor) || ($own && ($role->atLeast(Role::Author) || $entry->isDraft()));
    }

    public static function mayPublish(Account $account, ?Entry $entry): bool
    {
        $role = $account->role();

        return $role->atLeast(Role::Editor)
            || ($role === Role::Author && ($entry === null || $entry->get('author') === $account->id()));
    }

    public static function mayDelete(Account $account, ?Entry $entry): bool
    {
        return $entry !== null && ($account->role()->atLeast(Role::Editor)
            || ($entry->get('author') === $account->id() && $entry->isDraft()));
    }
}

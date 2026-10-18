<?php

namespace Wake\Accounts;

/**
 * Roles in the team, modelled after Ghost.
 */
enum Role: string
{
    /** Everything, including the team and settings. Exactly one per site. */
    case Owner = 'owner';

    /** Everything except transferring ownership. */
    case Admin = 'admin';

    /** Writes, edits and publishes all content. */
    case Editor = 'editor';

    /** Writes and publishes own posts. */
    case Author = 'author';

    /** Writes own drafts, cannot publish. */
    case Contributor = 'contributor';

    public function rank(): int
    {
        return match ($this) {
            self::Owner => 5,
            self::Admin => 4,
            self::Editor => 3,
            self::Author => 2,
            self::Contributor => 1,
        };
    }

    public function atLeast(self $role): bool
    {
        return $this->rank() >= $role->rank();
    }

    public function label(): string
    {
        return __('wake::roles.'.$this->value);
    }
}

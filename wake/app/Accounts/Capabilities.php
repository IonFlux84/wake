<?php

namespace Wake\Accounts;

/**
 * Everything a role can be allowed to do in the panel and through the MCP
 * server. docs/reference/roles.md lists them; a test keeps both in sync.
 */
final class Capabilities
{
    /**
     * @return array<string, string> capability => label in the panel language
     */
    public static function all(): array
    {
        return [
            'content.write' => __('Write drafts and edit own drafts'),
            'content.publish' => __('Publish and edit own entries'),
            'content.edit_all' => __('Edit entries of others'),
            'content.publish_all' => __('Publish entries of others'),
            'content.delete_all' => __('Delete any entry'),
            'stats.view' => __('See statistics'),
            'consent.manage' => __('Manage the consent banner'),
            'categories.manage' => __('Manage categories'),
            'themes.manage' => __('Manage themes'),
            'team.manage' => __('Manage the team and roles'),
            'settings.manage' => __('Change site settings'),
            'assistants.use' => __('Create tokens for AI assistants'),
        ];
    }

    /**
     * Groups for the roles matrix in the panel.
     *
     * @return array<string, list<string>>
     */
    public static function groups(): array
    {
        return [
            __('Content') => ['content.write', 'content.publish', 'content.edit_all', 'content.publish_all', 'content.delete_all'],
            __('Insights') => ['stats.view', 'consent.manage'],
            __('Site') => ['categories.manage', 'themes.manage', 'team.manage', 'settings.manage'],
            __('Assistants') => ['assistants.use'],
        ];
    }
}

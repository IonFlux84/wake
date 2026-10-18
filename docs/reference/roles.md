---
title: Roles
description: Roles and the capabilities they grant, and how to adjust them or add your own.
---

# Roles

A role is a name and a set of **capabilities**. Every account has exactly one
role. Wake comes with five roles modelled on Ghost; you can change what they
may do and add your own under *Team → Roles and rights* (owners and anyone with
`team.manage`). The settings are stored in `site/roles.yml`.

## Capabilities

| Capability | Allows |
|---|---|
| `content.write` | writing new entries and editing one's own drafts |
| `content.publish` | publishing one's own entries and editing them once published |
| `content.edit_all` | editing entries of others |
| `content.publish_all` | publishing entries of others |
| `content.delete_all` | deleting any entry (everyone may delete their own drafts) |
| `stats.view` | the statistics, the wake view and the numbers on the dashboard |
| `consent.manage` | the consent page and banner editor |
| `structure.manage` | arranging the page tree and editing menus |
| `categories.manage` | creating and changing categories |
| `themes.manage` | choosing, configuring, installing and removing themes |
| `team.manage` | inviting, changing and removing members, and roles |
| `settings.manage` | site settings, imports and checking for updates |
| `assistants.use` | creating tokens for AI assistants |

Installing updates is reserved for the owner.

## The built-in roles

| | Owner | Administrator | Editor | Author | Contributor |
|---|:-:|:-:|:-:|:-:|:-:|
| `content.write` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `content.publish` | ✓ | ✓ | ✓ | ✓ | |
| `content.edit_all` | ✓ | ✓ | ✓ | | |
| `content.publish_all` | ✓ | ✓ | ✓ | | |
| `content.delete_all` | ✓ | ✓ | ✓ | | |
| `stats.view` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `consent.manage` | ✓ | ✓ | | | |
| `structure.manage` | ✓ | ✓ | ✓ | | |
| `categories.manage` | ✓ | ✓ | | | |
| `themes.manage` | ✓ | ✓ | | | |
| `team.manage` | ✓ | ✓ | | | |
| `settings.manage` | ✓ | ✓ | | | |
| `assistants.use` | ✓ | ✓ | ✓ | ✓ | ✓ |

The **owner** always has every capability and cannot be changed. There is one
owner per site, created during setup. Built-in roles can be adjusted and reset
to these defaults; custom roles can be removed once nobody has them. Wake keeps
`team.manage` on your own role when you save, so nobody can lock themselves
out.

People without `content.edit_all` or `content.publish` see only their own
entries in the lists. Menu entries for things a role may not do are hidden.

## `site/roles.yml`

```yaml
author:
  capabilities: [content.write, content.publish]
moderator:
  label: { de: Moderation, en: Moderator }
  capabilities: [content.write, content.edit_all, stats.view]
```

Tokens for AI assistants never have more rights than the person who created
them; see [MCP server](mcp.md#tokens).

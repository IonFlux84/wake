---
title: Back up and restore
description: Write a backup ZIP, keep it somewhere safe, and restore it on the same or another server.
---

# Back up and restore

Your whole site is files, so a backup is one ZIP file. Open *Backups* in the
panel (owners, administrators and anyone with `settings.manage`).

## What a backup contains

| In the ZIP | What it is |
|---|---|
| `content/` | posts, pages, images and other files |
| `site/` | settings, categories, menus, redirects, roles, consent settings, themes |
| `storage/accounts/` | accounts and tokens for AI assistants |
| `storage/consent/` | anonymous consent records |
| `storage/revisions/`, `storage/history/` | versions and the history of changes |
| `storage/stats/` | statistics, only when you tick *Include the statistics* |
| `wake-backup.json` | Wake version, date and what is inside |

Not included: caches, the trash, resized images (Wake makes them again on the
first request) and `.env`. `.env` holds the application key and the mail
password; keep a copy of it in a password manager. Without the old key, people
only have to sign in again.

## Write a backup

Click **Write backup**, then **Download**. Backups stay in `storage/backups`
until you delete them, so keep an eye on the disk space of your host. On the
command line: `php wake/artisan wake:backup` ([CLI](../reference/cli.md#wakebackup)).

Wake has no automatic backups yet. Many hosts back up your web space anyway;
for a schedule of your own, a cron job can run `wake:backup`.

## Restore a backup

1. Upload the ZIP under *Upload a backup*, or put it into `storage/backups` by
   FTP (easier for large files).
2. Click **Restore** next to it.

Wake checks the ZIP first: it must be a Wake backup, and it may only contain
the folders listed above. Then it saves the current state as a backup of its
own (`…-before-restore.zip`), moves the replaced folders to the trash and
unpacks the backup. A backup without statistics keeps the current ones.

The accounts come from the backup, too. If yours was not in it, you are signed
out.

### Moving to another server

Install Wake on the new server, create any account, then restore the backup
there. Copy the old `.env` over the new one if you want to keep the
application key.

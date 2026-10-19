---
title: Updating
description: Install a new version of Wake from the panel or by hand.
---

# Updating

An update replaces the `wake/` folder, the few files Wake keeps in
`public/` (`index.php`, `hit.php`, `.htaccess`) and the main folder
(`.htaccess`), and the folders of the plugins that ship with Wake, such as
`plugins/redirect-scout/`. Whether they are switched on, and their settings,
stay as they were. Don't change files in those plugin folders: the next
update replaces them. Your `content/`, `themes/`, your own plugins,
`settings/`, `data/` and `.env` are never touched
([decision 0008](../decisions/0008-application-in-its-own-folder.md)). A site
from Wake 0.1 moves to the current folder layout once with
[`wake:layout`](../reference/cli.md#wakelayout).

## In the panel

1. Open *Settings* and click *Check for updates*. Wake asks GitHub for the
   latest release. It only does so when you click: Wake never contacts other
   servers on its own.
2. If there is a newer version, read what changed and click *Install*.
3. Wake downloads the ZIP, checks its SHA-256 checksum, unpacks it, keeps the
   current `wake/` folder and the replaced plugin folders as a backup in
   `temp/updates/` and switches to the new version.

If anything goes wrong before the switch, nothing changes. If the new version
does not start, move the backup folder from `temp/updates/` back to `wake/`.

## By hand

1. Download the new ZIP and unzip it.
2. Upload its `wake/` folder next to the old one, named `wake-new/`.
3. Rename `wake/` to `wake-old/` and `wake-new/` to `wake/`.
4. Copy `public/index.php`, `public/hit.php`, `public/.htaccess` and `.htaccess`
   from the ZIP, and replace the folders in `plugins/` that the ZIP contains.
5. Delete `wake-old/` once the site works.

## For developers

With Git: `git pull`, then `composer --working-dir=wake install` and, if the
editor changed, nothing else: built assets are part of the repository.

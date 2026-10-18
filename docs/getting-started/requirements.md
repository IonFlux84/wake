---
title: System requirements
description: What a server needs to run Wake, what is optional, and how to check it.
---

# System requirements

Wake needs PHP and a web server, nothing else: no database, no Node.js, no
cron job, no SSH access. Ordinary shared hosting is enough.

## In short

| | Needed | Recommended |
|---|---|---|
| PHP | 8.4 or newer | the newest version your host offers |
| Web server | Apache 2.4 with `mod_rewrite` and `.htaccess`, or nginx | document root on `public/` |
| Disk space | about 60 MB for Wake | plus your images; backups need about as much again |
| Memory | `memory_limit` 128 MB | 256 MB for large images |
| HTTPS | for the panel and two-factor sign-in | a free Let's Encrypt certificate from your host |
| Database | **none** | |
| Cron | **none** ([why](../concepts/architecture.md#maintenance-without-cron)) | optional, for exact publishing times |

## PHP extensions

| Extension | What Wake uses it for | |
|---|---|---|
| `mbstring`, `ctype`, `tokenizer`, `filter`, `hash`, `openssl`, `session`, `json` | Laravel itself; almost always present | needed |
| `fileinfo` | checking uploaded files | needed |
| `dom`, `xml` | the Ghost import and feeds | needed |
| `gd` | resizing images; with WebP support for `.webp` versions | needed for images |
| `zip` | backups, theme installation and updates from the panel | needed for those features |
| `curl` or `allow_url_fopen` | checking for updates, importing images from Ghost | optional |
| `intl` | nicer date formats in some languages | optional |
| OPcache | keeps the content index in memory, which makes Wake much faster | strongly recommended |

Without `zip`, Wake still runs; the backup and update pages tell you what is
missing. Without WebP support in `gd`, Wake simply serves JPEG and PNG.

## Check your server

On your computer:

```bash
php -v
php -m
```

On a web host, open the panel after installing: *Settings* lists everything
Wake found wrong, for example a folder it cannot write to or a document root
that exposes your content. During [setup](installation.md) the same checks
run before the first account is created.

## Folders Wake writes to

| Folder | Why |
|---|---|
| `content/` | posts, pages and their files |
| `site/` | settings, categories, menus, redirects, themes, plugins |
| `storage/` | accounts, statistics, versions, backups, caches |
| `public/media/`, `public/assets/` | resized images, theme and plugin assets |

The user PHP runs as needs write access to these, which most hosts set up
already. Otherwise use `775` for folders and `664` for files.

## Upload sizes

Images up to 20 MB can be uploaded in the editor. Your host's PHP settings
`upload_max_filesize` and `post_max_size` must allow that size, too. Large
backups are easier to upload by FTP into `storage/backups`
([backups](../guides/backups.md)).

## Hosts

Wake is developed against Apache 2.4 with PHP-FPM and nginx with PHP-FPM. It
needs no special features, so any host with PHP 8.4 should work. If you run
Wake somewhere and something did not work, please tell us in an issue, so this
page can list hosts that are known to work.

## Browsers for the panel

The panel works in current versions of Firefox, Chrome, Edge and Safari,
including on phones. The website itself depends on your theme; Tide works in
every current browser.

---
title: Installation
description: Get a Wake site running on your computer.
---

# Installation

This page gets Wake running on your own computer, for development or to try it
out. To put a site online, see [Install on a web host](hosting.md).

## What you need

- PHP 8.4 or newer with the extensions `mbstring`, `intl`, `gd` and `zip`
- [Composer](https://getcomposer.org)

Check with:

```bash
php -v
composer -V
```

## Steps

1. **Get the code.**

   ```bash
   git clone https://github.com/IonFlux84/wake.git my-site
   cd my-site
   ```

2. **Install the dependencies.** They go into `wake/vendor/`.

   ```bash
   composer --working-dir=wake install
   ```

3. **Create the settings file** with a fresh application key.

   ```bash
   cp .env.example .env
   php wake/artisan key:generate
   ```

4. **Start the development server.**

   ```bash
   php wake/artisan serve
   ```

5. Open <http://localhost:8000>. You see the demo site from `content/`.

6. **Create your account.** Open <http://localhost:8000/wake>. On the first
   visit Wake asks for your name, email address and a password, and makes you
   the owner of the site. Accounts are stored as files in `storage/accounts/`.

## What is where

| Folder | Contents |
|---|---|
| `wake/` | the application. Don't change anything here, an update replaces it |
| `site/` | your settings (`config.php`), blueprints and theme |
| `content/` | your posts and pages |
| `storage/` | accounts, statistics and caches, never public |
| `public/` | the folder your web server serves |

Read [Architecture](../concepts/architecture.md) to see how these fit together,
and [Configuration](../reference/configuration.md) for everything you can set
in `site/config.php`.

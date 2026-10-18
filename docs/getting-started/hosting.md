---
title: Install on a web host
description: Put Wake on shared hosting by uploading a ZIP. No SSH, no Composer, no database.
---

# Install on a web host

Wake runs on ordinary shared hosting with PHP 8.4 or newer. You need neither
SSH nor Composer, and no database.

## Steps

1. **Download** the latest `wake-x.y.z.zip` from the releases page and unzip it
   on your computer.

2. **Upload** the contents of the `wake-x.y.z` folder to your web space, for
   example with FTP. You now have `wake/`, `content/`, `themes/`, `plugins/`,
   `settings/`, `data/`, `temp/`, `public/` and a few files next to each other.

3. **Point the document root to `public/`.** In your hosting control panel, set
   the folder your domain serves to `…/public`. This keeps `content/`,
   `settings/`, `data/` and `.env` out of reach of the web.

   If your host does not let you change the document root, leave it on the
   main folder: the `.htaccess` that comes with Wake then sends every request
   into `public/` and nothing else can be reached. Wake shows a warning in the
   panel as a reminder; the document root on `public/` is still the better
   choice.

4. **Allow writing.** Wake writes to `content/`, `themes/`, `plugins/`,
   `settings/`, `data/`, `temp/` and `public/`. Most hosts allow this already; otherwise set the folders to
   `775`.

5. **Open `/wake`** on your domain, with the address the site will have
   (`https://…`). On the first visit Wake creates the file `.env` with a
   secret application key and that address as `APP_URL`, and asks you to
   create the owner account.

6. **Check your address.** If you opened the site under another address
   first (say, a test domain), *Settings* shows a warning; then correct
   `APP_URL` in `.env`.

7. **Set up mail** in the panel under *Settings* → **Sending mail**, and send
   yourself a test mail (see [Invite your team](../guides/invite-your-team.md)).

## Checklist

| | |
|---|---|
| PHP | 8.4 or newer with `mbstring`, `gd`, `zip`, `fileinfo` and the usual Laravel extensions ([full list](requirements.md)) |
| Web server | Apache with `mod_rewrite` (`.htaccess` files allowed), or nginx (see below) |
| Disk | about 60 MB for Wake, plus your content |
| Cron | not needed ([maintenance](../concepts/architecture.md#maintenance-without-cron)) |

## nginx

```nginx
root /path/to/site/public;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.4-fpm.sock;
}

location ~ /\.(?!well-known) { deny all; }
```

Then continue with [Your first site](first-site.md).

## Updates

Owners and administrators find *Updates* under *Settings*. See
[Updating](updating.md).

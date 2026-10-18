---
title: Analytics
description: How Wake counts visits without sending data anywhere, and what it stores.
---

# Analytics

Wake counts visits itself. Nothing is sent to another company, and the data
stays in `data/stats/` on your server.

## Two levels

| | Level 1 · without cookies | Level 2 · with consent |
|---|---|---|
| Recognises a visitor | for one day, by a salted hash | for up to 12 months, by the cookie `wake_v` |
| Needs consent | no (configurable, see below) | yes, category *Statistics* |
| Stores on the device | nothing | one first-party cookie |
| Answers | visitors and page views per day, sources, campaigns, pages, reading time, scroll depth, countries, languages, devices, events | additionally returning visitors |

Every report shows which share of visitors was counted with consent.

### The daily hash

For level 1, `public/hit.php` computes

```
hmac_sha256(IP address | user agent | host, salt of the day)
```

and keeps the first 16 characters. The salt is a random value created each day
in `data/stats/salt/` and deleted the next day by maintenance. Once it is
gone, nobody can work out who a hash belonged to, not even you. The IP address
itself is never written to disk.

> **Legal note.** This approach is used by Plausible and Fathom and is
> widely considered consent-free. Whether reading the user agent falls under
> § 25 TDDDG (Germany) or similar rules is disputed. If you want to be on the
> safe side, set `tracking.level1` to `'consent'`. Then Wake only counts people
> who agreed in the banner.

## What is measured

The tracking script (`track.js`, about 1.3 KB compressed) sends:

- **page view:** path, query string (for `utm_*` campaigns), referrer, language
  of the page
- **leave:** active reading time (only while the tab is visible) and the
  maximum scroll depth, when the visitor leaves or switches tabs
- **events:** clicks on external links and downloads, sent forms, and your own
  events: `wake('event', 'Signed up', 'newsletter')`

`public/hit.php` adds device type, browser and operating system, which it
derives from the user agent, and the country if you installed a country
database. It skips

- bots and crawlers (a list of known user agents),
- members of your team signed in to the panel,
- visitors with Do Not Track or Global Privacy Control, if
  `tracking.respect_privacy_signals` is on.

`hit.php` does not start Laravel. A hit costs about as much as serving a small
file.

## AI crawlers

Crawlers of AI companies (GPTBot, ClaudeBot, PerplexityBot, Google-Extended and
others) never run the tracking script, so `public/index.php` recognises them by
their user agent and appends a line with the crawler's name and the path to
`data/stats/ai/{day}.log`. The statistics page shows them in the card
*AI is reading along*. They are kept as long as raw data.

## Storage

```
data/stats/
  raw/2026-10-17.log       one line per hit, tab-separated, appended with a lock
  daily/2026-10-16.json    summary of a finished day
  monthly/2026-10.json     summary of a month
  salt/2026-10-17          today's salt
  ai/2026-10-17.log        visits of AI crawlers
  .config.php              rules for hit.php, written by Wake
```

A raw line looks like this (tabs shown as `→`):

```
1792315313→pageview→812ab8b356cf39c2→→/hello-world→social.example→mastodon→→launch→en→DE→mobile→Safari→iOS→→→0→0→1
```

Maintenance turns finished days into summaries and deletes raw files after
`tracking.retention` days. Summaries are small and kept forever.

## Numbers you should know

- **Visitors over several days are added up.** Without a cookie, a person who
  visits on Monday and Tuesday counts twice in a weekly report. Every
  cookieless analytics tool works like this.
- **Reading time** is the average active time per page view that ended with a
  leave signal.
- **Read-through rate** is the share of those page views that scrolled to at
  least 90 %.
- **Sources** count entrances: the first page view of a visitor that day, or
  any page view that came from another site. An empty source means *direct*.
- **Next pages** follow a visitor through the site within a day. They power the
  post journey view.

## In code

```php
use Wake\Stats\Stats;

$report = app(Stats::class)->period('7d');   // today, yesterday, 7d, 30d, 90d, 12m, month
$report->visitors();
$report->pages();                            // with the entry behind each path
$report->top('referrers');
app(Stats::class)->realtime();               // visitors in the last five minutes
```

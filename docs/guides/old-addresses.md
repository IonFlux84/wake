---
title: Keep old addresses working
description: Find every address of the website you replace with Wake, see which ones lead nowhere now, and give them a target.
---

# Keep old addresses working

When Wake replaces an older website, links from search engines, other
websites and bookmarks still point to the old addresses. This guide finds
all of them and gives every address that leads nowhere a redirect.

Wake ships the plugin **Redirect scout** for this. Its page is *Old
addresses* in the panel, under *Manage* (people with `settings.manage`). If
you don't see it, switch the plugin on under *Plugins*.

## 1. Search the old website

Do this while the old website is still online, ideally before you switch
the domain to Wake.

1. Open *Old addresses* and enter the address of the old website, for example
   `https://www.example.org`.
2. Choose where Wake looks:
   - **Sitemap and robots.txt**: the addresses the old website lists itself.
   - **Follow the links on every page**: Wake visits each page and follows
     its links, like a search engine. It stays on the old website, skips what
     `robots.txt` forbids and pauses between visits.
   - **Wayback Machine**: addresses the [Internet Archive](https://web.archive.org)
     saw over the years, also pages nothing links to any more. This finds
     the most, and is also the only way once the old website is gone.
3. Under *Add addresses by hand*, paste addresses you know from elsewhere,
   for example the most visited pages from Google Search Console or the
   server logs.
4. Click *Start search*. Keep the page open: Wake works in small steps while
   you watch, so it needs no cron job.

With SSH you can run the same search on the command line, which is faster
for big websites:

```bash
php wake/artisan redirect-scout:scan https://www.example.org --wayback
```

## 2. Read the result

Wake asks your Wake site what it answers for every address it found, the
same way a visitor would get it. The figures on top filter the list:

| Filter | Means |
|---|---|
| *Need a target* | Wake answers 404 here. These are the ones to fix. |
| *Redirected* | A redirect already leads somewhere. |
| *Exist here* | A page, post, tag, category or file has the same address. |
| *Gone* | The address answers 410 on purpose. |
| *Ignored* | You chose not to care about it. |

Small labels under each address say where Wake found it (`sitemap`, `link`,
`wayback`, `list`) and what the old website answers today, if that is not 200.

## 3. Give each address a target

For most addresses Wake already has a **suggestion**, with a score and the
reason:

| Reason | Example | Score |
|---|---|---|
| same file name | `/uploads/flyer.pdf` and `media/…/flyer.pdf` | 100 |
| same slug | `/news/summer-fest.html` and the post `summer-fest` | 95–100 |
| same title | the old page's title equals a title here | 92 |
| same page without feed or page number | `/news/summer-fest/feed` or `/tag/club/page/3` | 95 |
| same slug without number or date | `/termin/stammtisch-42` or `/termin/stammtisch-2017-08-09` and the post `stammtisch` | 85 |
| similar slug or title | small differences in spelling | 68–90 |
| parent page | nothing similar, but `/club` exists for `/club/old-board` | 40 |

- **Take it** saves the suggestion.
- *Take … safe suggestions* saves every suggestion from 90 % on at once.
- Or type a target yourself: an entry (the field suggests titles), a path like
  `/events`, a file like `media/downloads/flyer.pdf` or a full address
  starting with `https://`.
- **410** marks a page that was removed on purpose.
- **Ignore** hides addresses nobody will miss, like old admin pages.

The figures on top are the filters: click *Exist here* or *Redirected* to see
those addresses and where they lead now.

## 4. Whole folders at once

Type a folder into the search, for example `shop/`. For all addresses shown
you can then:

- **Take the suggestions**, also those under 90 %. Check a few first.
- **410**, for example for spam pages an old hack left behind.
- **Ignore** them.
- Or save **one rule** for the folder: everything below `/strasse/*` leads to
  `/strassen`. A target ending in `/*` keeps the rest of the address, so
  `/strasse/*` → `/strassen/*` leads `/strasse/lindenweg` to
  `/strassen/lindenweg`. Rules are patterns in `settings/redirects.yml`
  ([how they work](../concepts/editing.md#redirects)).

Every target is saved in `settings/redirects.yml`, the same file the
*Redirects* page shows ([how redirects work](../concepts/editing.md#redirects)).
After you add new pages, *Check all addresses again* updates the list.

## With an assistant

An assistant connected over [MCP](connect-an-assistant.md) can do the
tedious part with you: `redirect_scout_list` lists the addresses with their
suggestions, `redirect_scout_set` saves a target. Both need a token whose
owner may manage settings; `redirect_scout_set` also needs the scope
`content:write`.

## Where the search is kept

The search and its findings live in `data/redirect-scout/scan.json`. *Forget
the search* deletes them; saved redirects stay.

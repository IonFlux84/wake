---
title: Consent
description: How Wake's consent banner, two-click embeds and consent records work.
---

# Consent

Wake has a consent manager built in. It asks only when there is something to
ask for, treats "Decline" exactly like "Accept" and keeps an anonymous record of
every decision.

## When the banner appears

By default the banner appears on every site (`always: true` in
`settings/consent.yml`), so visitors always see what the site counts and can
decide. With `always: false` it appears only when the site actually needs
consent up front:

- the statistics cookie is offered (`statistics: true`),
- marketing scripts are used (`marketing: true`), or
- level-1 statistics wait for consent (`tracking.level1: 'consent'`, the
  default).

Embedded videos and maps ask for themselves when someone clicks them (see
below).

The footer link `@wakeConsentLink` ("Cookie settings") opens the detailed
settings at any time, so visitors can change or withdraw their choice.

## Categories

| Category | What it unlocks |
|---|---|
| Necessary | the `wake_consent` cookie that stores the choice. Always active |
| Statistics | the `wake_v` cookie for level-2 statistics (returning visitors) |
| External media | all embeds (YouTube, Vimeo, maps, …) without a click |
| Marketing | your own scripts marked with `data-wake-consent="marketing"` |

## Two-click embeds

```markdown
::: embed https://www.youtube.com/watch?v=abc123
Caption of the video.
:::
```

Wake renders a placeholder that names the service and explains that data goes
to it. Nothing is loaded from the service until the visitor clicks *Load
content*. A checkbox *Always allow YouTube* remembers the choice for this
service only. Visitors who agreed to *External media* see embeds directly.

Where a service offers a privacy-friendlier player, Wake uses it
(`youtube-nocookie.com`, Vimeo with `dnt=1`).

Recognised services: YouTube, Vimeo, Google Maps, OpenStreetMap, Spotify,
SoundCloud, Instagram, X. Other addresses are embedded as "external content".

## Your own scripts

Scripts in a theme or code injection run only after consent when you mark them:

```html
<script type="text/plain" data-wake-consent="marketing" src="https://example.com/pixel.js"></script>
```

## The list for your privacy policy

`@wakeServices` prints a ready-made section for the privacy policy: Wake's
statistics, the cookies in use and every service embedded anywhere in your
content. It updates itself when you embed something new. It is a starting
point, not legal advice.

## Records

Every decision is sent to `public/hit.php` and appended to
`data/consent/{year}-{month}.log`:

```json
{"time":"2026-10-17T09:41:00+00:00","id":"3f9c…","categories":["necessary","statistics"],"action":"custom","version":2,"language":"de"}
```

The record contains a random consent id (also stored in the visitor's cookie),
the time, the categories, the language and the version of the banner texts. It
contains no IP address and nothing else that identifies a person. That is
enough to prove that a given consent was given with a given text (Art. 7 GDPR).

## Versions

Changing the banner texts raises the version in `settings/consent.yml`. Visitors
whose stored choice has an older version are asked again.

## In the panel

*Consent* shows the decisions of the last 30 days, the agreement per category,
the latest records and the embedded services. Administrators edit the banner
there, with a live preview per language.

## Settings file

`settings/consent.yml` is written by the panel. All keys are optional:

```yaml
version: 2
statistics: true          # offer the level-2 statistics cookie
marketing: false
always: true              # show the banner even if nothing needs consent (default)
position: bottom          # bottom or center
privacy_url: /privacy
texts:
  de:
    title: Ihre Privatsphäre
    accept: Alle akzeptieren
```

Texts you leave out use Wake's defaults in English or German.

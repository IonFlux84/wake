---
title: Categories and events
description: Give posts a category with its own fields and look, and publish events with dates, places and a calendar file.
---

# Categories and events

A **category** groups posts and gives them their own fields in the editor and
their own look on the website. Wake comes with the category **Termine**
(events in English).

## Writing an event

1. Create a new post and choose the category *Termine* under *Details*.
2. A card with the event fields appears in the side panel: start (required),
   end, all day, repetition, venue, address, join online, organizer, admission,
   registration link, registration deadline, contact and status (takes place,
   cancelled, postponed, sold out).
3. Write the description in the text and publish.

## Recurring events

A choir rehearsal every Tuesday stays **one** post. Choose a rule under
*Repeats*:

| Rule | Example |
|---|---|
| Every week | every Tuesday |
| Every other week | every second Tuesday |
| Every month on the same day | on the 15th |
| Every month on the same weekday | every 2nd Tuesday, or every last Friday |
| Every year | every 24 December |

*Repeat until* ends the series (leave it empty for no end). Under *Skipped on*,
list dates without the event, one per line, for example holidays. The editor
shows the next dates right away, so you can check the rule before saving.

On the website every date appears in the event list in its month, the event
page shows the next date, the rule in words and the following dates, and the
calendar file contains the whole series, so calendar apps show every date.

On a multilingual site, dates, the address and links are the same in every
language: you enter them once in the default language. Venue, organizer and
admission can be translated.

## On the website

| Address | Shows |
|---|---|
| `/events/summer-fest` (`/de/termine/sommerfest`) | the event with date, place, map link, admission, registration button and *Add to calendar* |
| `/events/summer-fest.ics` | a calendar file for Apple Calendar, Outlook or Google Calendar |
| `/events` (`/de/termine`) | upcoming events by month, past events folded away |
| the start page | the next three events in their own block |

Search engines get the event as structured data (schema.org `Event`), including
cancellations.

## Your own categories

Owners and administrators manage categories under *Categories* in the panel:

- **Name, address and description** per language.
- **Fields in the editor:** a field name (`photographer`), labels in German and
  English, a type (text, long text, number, yes/no, date, date and time,
  choice, link, email address), whether it is required for publishing, and
  whether it differs per language.
- **On the website:** a template name, addresses below the category
  (`/reports/my-post`) and the order of the category page (newest first, or by
  a date field with upcoming entries first).

Each category is a folder in `content/categories/`, so you can also write or
copy one by hand. The format is described in
[content format](../reference/content-format.md#categories), the templates in
[templates](../reference/templates.md#categories).

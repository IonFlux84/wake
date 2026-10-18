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
   end, all day, venue, address, join online, organizer, admission,
   registration link, registration deadline, contact and status (takes place,
   cancelled, postponed, sold out).
3. Write the description in the text and publish.

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

Each category is a file in `site/categories/`, so you can also write or copy
one by hand. The format is described in
[content format](../reference/content-format.md#categories), the templates in
[templates](../reference/templates.md#categories).

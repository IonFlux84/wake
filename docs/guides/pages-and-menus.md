---
title: Arrange pages and menus
description: Order and nest pages in the page tree, and decide what the main menu and the footer show.
---

# Arrange pages and menus

Both need the `structure.manage` right (owners, administrators and editors by
default).

## The page tree

*Pages* shows your pages as a tree.

- **Drag** a page onto the upper or lower edge of another page to put it
  before or after it. Drop it onto the middle of a page to put it inside, as
  its last sub-page.
- The **arrow buttons** do the same without a mouse: up, down, one level
  higher, and into the page above.
- **Add to menu** / **Remove from menu** decides whether a page appears in the
  automatic main menu (`menu: true`). Pages outside the menu, such as an
  imprint, keep their place in the tree.
- The home page stays where it is.

Behind the scenes, Wake writes the position as `order: 1`, `order: 2` … into
the `content.md` of the pages; folder names never change for a new order.
Only a new parent page moves the folder (`pages/team` becomes
`pages/about/team`). Wake keeps versions, menus and redirects pointing at the
moved pages. When the address of a published page changes because it got another
parent page, the old address redirects to the new one
([redirects](../concepts/editing.md#redirects)).

*Show as list* switches to the table with search and status filters.

## Menus

*Menus* has two menus: the **main menu** and the **footer**.

The main menu **follows the page tree** until you arrange it by hand: it
shows the top-level pages with `menu: true` in order, with their sub-pages
that have `menu: true`. That
is often all a site needs.

**Arrange by hand** turns it into a menu of its own, stored in
`settings/menus.yml`:

- Add **pages** and **links** (any address starting with `https://`, a path
  like `/search`, `mailto:` or `tel:`).
- Drag items to change the order, or drop one onto another to make it a
  sub-item. Menus have one level of sub-items.
- Open an item to change its **label**, per language. Without a label, a page
  shows its title.
- Pages that are not published are left out on the website.
- **Follow the page tree again** forgets the hand-made main menu.

The footer is empty until you add something; the Tide theme then repeats the
main menu there.

## In a theme

```blade
@foreach ($site->menu() as $item)
    <a href="{{ $item->url() }}" @if ($item->isActive()) aria-current="page" @endif>{{ $item->title() }}</a>
@endforeach

@foreach ($site->menu('footer') as $item) … @endforeach
```

See [templates](../reference/templates.md#menus) for everything a menu item
offers, and [content format](../reference/content-format.md#menus) for the
file.

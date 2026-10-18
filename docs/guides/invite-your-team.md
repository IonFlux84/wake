---
title: Invite your team
description: Give other people access to the panel with the right role.
---

# Invite your team

1. **Set up mail** so Wake can send invitations and password links. Add your
   mail server to `.env`:

   ```bash
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.example.com
   MAIL_PORT=587
   MAIL_USERNAME=wake@example.com
   MAIL_PASSWORD=…
   MAIL_FROM_ADDRESS="wake@example.com"
   ```

   Without mail, invitations still work: the panel shows the invitation link,
   and you send it yourself.

2. **Invite.** Open *Team* (owners and administrators only), enter name, email
   address and role, and click *Send invitation*.

3. **They join.** The link in the mail is valid for 7 days. The person chooses a
   password and is signed in right away. Until then the team list marks them as
   *invited*, and you can send the invitation again.

## Roles

Choose the smallest role that fits: contributors write drafts, authors publish
their own posts, editors work on everything, administrators also manage the
team and settings. The full table is in [Roles](../reference/roles.md).

## Removing someone

The bin icon next to a member removes their access. Their account folder, with
their tokens for assistants, moves to `storage/trash/`. Their posts stay.

## Forgotten passwords

*Forgot your password?* on the sign-in page sends a link that works for 60
minutes. Reset tokens are kept in the file cache, so this needs no database
either.

A new password does not skip two-factor sign-in: after the reset, Wake still
asks for the code. See [Sign in with two factors](two-factor.md).

---
title: Invite your team
description: Give other people access to the panel with the right role.
---

# Invite your team

1. **Set up mail** so Wake can send invitations and password links: open
   *Settings* → **Sending mail**, choose *SMTP* and enter server, port,
   encryption, user name, password and sender address. Your host or mail
   provider has these details; they are often the same as in your mail
   program. Then click **Send a test mail to me**.

   The settings are stored in `data/secrets/mail.yml`, the password encrypted with
   the application key. They replace the `MAIL_*` values in `.env`, which
   still work if you prefer to set mail there. Many shared hosts also offer
   *sendmail*, which needs no details.

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
their tokens for assistants, moves to `data/trash/`. Their posts stay.

## Forgotten passwords

*Forgot your password?* on the sign-in page sends a link that works for 60
minutes. Reset tokens are kept in the file cache, so this needs no database
either.

A new password does not skip two-factor sign-in: after the reset, Wake still
asks for the code. See [Sign in with two factors](two-factor.md).

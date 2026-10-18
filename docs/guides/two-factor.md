---
title: Sign in with two factors
description: Protect your account with codes from an authenticator app, and what to do when the phone is gone.
---

# Sign in with two factors

With two-factor sign-in, Wake asks for a six-digit code from an app after your
password. A stolen password alone is then not enough. Every account can turn
it on for itself.

## Turn it on

1. Open *Settings*. **Two-factor sign-in** sits right next to *Your account*
   at the top. Click **Set up**. (While it is off, the dashboard reminds you.)
2. Scan the QR code with an authenticator app: the one in your password
   manager, or an app such as Aegis, Google Authenticator or Microsoft
   Authenticator. You can also type in the key below the code.
3. Enter the six digits the app shows and click **Turn on**.
4. Wake shows eight **recovery codes**, only this once. Keep them in your
   password manager or print them out. Each one works once instead of a code
   from the app.

From now on, signing in has a second step. *Stay signed in* still works, so
you are not asked again on the same device until the session ends.

## Require it for everyone

Owners and administrators can make two-factor sign-in mandatory: *Settings* →
**Require two-factor sign-in**. Set it up for your own account first; Wake
refuses the switch otherwise, so nobody locks themselves out.

From then on:

- Anyone without two-factor sign-in is taken to a setup page right after
  signing in and can do nothing else in the panel until it is done (signing
  out still works).
- Nobody can turn it off for themselves.
- *Team* shows who has it (**2FA**) and who has not yet (**no 2FA**).
- Tokens for AI assistants keep working; they never use the password.

The setting is `security.require_two_factor`
([configuration](../reference/configuration.md#site)).

## Lost your phone?

- Sign in with one of your **recovery codes** instead of the app code. When
  only two are left, Wake reminds you to make new ones (*Settings* → **New
  recovery codes**, needs your password).
- No recovery codes either? Someone with `team.manage` (for example the owner)
  can turn two-factor sign-in off for you under *Team* (key button next to
  your name). Then sign in with your password and set it up again. If the
  site requires two-factor sign-in, Wake takes you to the setup right away.

## Good to know

- Codes are standard TOTP (RFC 6238: 6 digits, 30 seconds). If codes are
  refused, check that the time on your phone is set automatically.
- Each code works only once. Five wrong codes in a row block the step for a
  minute.
- The secret is stored in the account file, encrypted with the application key
  from `.env`; recovery codes are stored only as hashes. If you move the site
  to a server with a new application key, app codes stop working there, but
  recovery codes keep working. Keep a copy of `.env`
  ([backups](backups.md)).
- Tokens for AI assistants are not affected; they never use the password.

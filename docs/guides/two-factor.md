---
title: Sign in with two factors
description: Protect your account with codes from an authenticator app, and what to do when the phone is gone.
---

# Sign in with two factors

With two-factor sign-in, Wake asks for a six-digit code from an app after your
password. A stolen password alone is then not enough. Every account can turn
it on for itself.

## Turn it on

1. Open *Settings* and find **Two-factor sign-in**. Click **Set up**.
2. Scan the QR code with an authenticator app: the one in your password
   manager, or an app such as Aegis, Google Authenticator or Microsoft
   Authenticator. You can also type in the key below the code.
3. Enter the six digits the app shows and click **Turn on**.
4. Wake shows eight **recovery codes**, only this once. Keep them in your
   password manager or print them out. Each one works once instead of a code
   from the app.

From now on, signing in has a second step. *Stay signed in* still works, so
you are not asked again on the same device until the session ends.

## Lost your phone?

- Sign in with one of your **recovery codes** instead of the app code. When
  only two are left, Wake reminds you to make new ones (*Settings* → **New
  recovery codes**, needs your password).
- No recovery codes either? Someone with `team.manage` (for example the owner)
  can turn two-factor sign-in off for you under *Team* (key button next to
  your name). Then sign in with your password and set it up again.

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

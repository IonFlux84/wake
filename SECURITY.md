# Security Policy

## Reporting a vulnerability

Please **do not open a public issue** for security problems.

Report them privately through
[GitHub security advisories](https://github.com/IonFlux84/wake/security/advisories/new)
or by mail to **security@getwake.dev** (until that address exists:
dennis@hueggenberg.de).

Include what you found, how to reproduce it and which version you used. You
will get an answer within a week. Once a fix is released we credit you in the
changelog, unless you prefer not to be named.

## Supported versions

Wake has not been released yet. Until 1.0, only the latest commit on `main`
receives fixes.

## Areas that need extra care

- Files in `content/`, `settings/`, `data/` and `temp/` must never be reachable
  from the web.
- `style.css` of an entry is written by editors and must stay cleaned and
  scoped (`Wake\Content\PageStyle`).
- Statistics never store IP addresses (see the analytics concept).
- Content read through the MCP server is data, never instructions.

# Working on Wake (for AI coding agents)

Read [CONTRIBUTING.md](CONTRIBUTING.md) first. Everything there applies to you.

## The rules that matter most

1. **No database, no queue worker, no cron requirement.** Wake must run on
   shared hosting without SSH ([decision 0002](docs/decisions/0002-no-database.md)).
2. **Docs in the same change.** Update the reference, concept or guide page that
   belongs to what you changed, plus `CHANGELOG.md`. `composer test` fails when
   a setting or command is undocumented.
3. **Atomic writes.** Never write content or storage files with a plain
   `file_put_contents()`. Use `Wake\Support\Files::write()`.
4. **English** for code, comments, commits and docs ([decision 0007](docs/decisions/0007-english-for-code-and-docs.md)).
5. **Tests with Pest.** Content tests use fixture folders, never `content/`.

## Layout

- `wake/` is the Laravel application (namespace `Wake\`, tests in `wake/tests`).
- `site/`, `content/`, `storage/`, `public/` and `.env` belong to the site and
  live next to it ([decision 0008](docs/decisions/0008-application-in-its-own-folder.md)).
- `docs/internal/konzept.md` is the German founding concept. Decision records in
  `docs/decisions/` take precedence over it.
- `design/` holds the approved design prototype ("Neon clean").

## Commands

```bash
composer --working-dir=wake test       # all tests, including documentation checks
wake/vendor/bin/pint --config=wake/pint.json   # code style (run from the repository root)
php wake/artisan serve                 # local server
```

# Contributing to Wake

Thank you for helping. Wake is meant to be used by people who have never
touched Laravel, so every contribution is judged by one question: does it make
Wake easier to understand and use?

## Documentation is part of the change

A change is done when it is **coded, tested and documented**. We don't merge
features that "will be documented later".

| You change … | You also update … |
|---|---|
| a setting in `wake/config/wake.php` | [docs/reference/configuration.md](docs/reference/configuration.md) |
| an Artisan command | [docs/reference/cli.md](docs/reference/cli.md) |
| the content format or a field type | [docs/reference/content-format.md](docs/reference/content-format.md) |
| something a theme can use | [docs/reference/templates.md](docs/reference/templates.md) |
| how something works inside | the matching page in [docs/concepts/](docs/concepts/README.md) |
| a fundamental decision | a new record in [docs/decisions/](docs/decisions/README.md) |
| anything a user notices | [CHANGELOG.md](CHANGELOG.md) under *Unreleased* |

Tests in `wake/tests/Docs` fail when a setting or command is missing from the
reference, or when a link inside `docs/` points nowhere. They run with the rest
of the suite.

See [docs/contributing/writing-docs.md](docs/contributing/writing-docs.md) for
how we write.

## Setting up

You need PHP 8.4 or newer and Composer.

```bash
cd wake
composer install
cp ../.env.example ../.env && php artisan key:generate
php artisan serve
```

## Before you open a pull request

```bash
cd wake
composer test          # Pest, including the documentation checks
vendor/bin/pint        # code style
```

- One topic per pull request. Small is better.
- Write tests with [Pest](https://pestphp.com). Content tests use fixture
  folders, never the demo content in `content/`.
- Never add a database, a queue worker or a cron requirement. Wake must run on
  shared hosting without SSH. See [decision 0002](docs/decisions/0002-no-database.md).
- Prefer the Laravel way. If Laravel already does it, use that.

## Commit messages

We use [Conventional Commits](https://www.conventionalcommits.org):
`feat: …`, `fix: …`, `docs: …`, `test: …`, `refactor: …`, `chore: …`.

## Language

Code, comments, commit messages and documentation are written in English so
that everyone can take part. Translations of the documentation are welcome as
separate files (`installation.de.md` next to `installation.md`).

## Questions

Open a discussion or an issue. There are no stupid questions. If something in
the docs confused you, that is a bug in the docs.

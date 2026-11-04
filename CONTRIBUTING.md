# Contributing to Phrasea

Thank you for your interest in Phrasea! This guide explains how to report problems, propose
changes and get a pull request merged.

By participating in this project, you agree to abide by its [code of conduct](./CODE_OF_CONDUCT.md).

## Ways to contribute

- **Report a bug**: open a [bug report](https://github.com/alchemy-fr/phrasea/issues/new/choose)
  with the steps to reproduce it, the version and the component concerned.
- **Suggest a feature**: open a feature request describing the problem you want to solve.
- **Improve the documentation**: the documentation lives in [`doc/`](./doc/) and is published at
  [docs.phrasea.com](https://docs.phrasea.com).
- **Write code**: fix a bug or implement a feature discussed in an issue.

Security vulnerabilities must not be reported in public issues: follow the
[security policy](./SECURITY.md).

## Before you start

For anything beyond a small fix (typo, obvious bug), open an issue first to discuss the change.
It avoids working on something that does not fit the project or that someone else is already
doing.

## Development setup

Follow the [setup guide](./doc/tech/01_setup.md), then the
[development setup](./doc/tech/02_dev.md).

Everything runs in Docker Compose: PHP, Composer, Node.js and pnpm are not needed on the host.
PHP commands run inside the API containers (`databox-api-php`, `expose-api-php`,
`uploader-api-php`), JavaScript commands inside the `dev` container. The documentation uses a
`dc` shell function that loads your `.env.local`:

```bash
function dc() {
    if [ -f .env.local ]; then
        docker compose --env-file=.env.local --env-file=.env --env-file=.env.local "$@"
    else
        docker compose "$@"
    fi
}
```

The repository is a monorepo: each application (`databox`, `expose`, `uploader`, `dashboard`,
`configurator`, `report`) has its own API and/or client, and shared code lives in `lib/php/*`
(Symfony bundles) and `lib/js/*` (`@alchemy/*` packages). When a change concerns several
applications, it usually belongs in a shared library.

## Branches

- `next` is the default branch, where the next major version (5.x) is developed. Open your pull
  requests against `next`.
- `master` is the stable 4.x line. Target it only for fixes to the current stable release.

## Coding standards

PHP code follows php-cs-fixer. Run it inside the API container, or on every project at once:

```bash
dc run --rm databox-api-php composer cs
```

```bash
bin/php-cs.sh
```

JavaScript and TypeScript code follows ESLint and Prettier, run in the `dev` container:

```bash
dc run --rm dev pnpm lint
```

```bash
dc run --rm dev pnpm lint:fix
```

```bash
dc run --rm dev pnpm format
```

A pre-commit hook (Husky + lint-staged) formats the staged files; keep the code lint-clean.

## Tests

Tests are organised in three tiers, described in
[`doc/tech/Development/ci.md`](./doc/tech/Development/ci.md):

```bash
bin/test.sh quick                      # static checks and unit tests, no service needed
```

```bash
bin/setup.sh && bin/test.sh standard   # full PHP suites, needs the stack
```

Per project:

```bash
dc run --rm databox-api-php composer phpunit:compact   # PHPUnit, compact output
```

```bash
dc run --rm databox-api-php composer test              # var-dump-check + PHPStan + PHPUnit
```

```bash
dc run --rm dev pnpm --filter databox-client test      # Vitest
```

Add tests with your change: PHP tests go in `tests/unit/` (no service needed) or
`tests/functional/` (kernel, database, Elasticsearch…) of the API.

## Doctrine migrations

Migrations must be plain SQL: put schema changes and data backfills in `up()` / `down()` with
`addSql()`, or `$this->connection` for row-by-row transforms. Never load entities, repositories
or services from a migration: it is replayed on fresh installs long after the code it references
has changed.

## Commit messages

Commits follow [Conventional Commits](https://www.conventionalcommits.org/), with the component
as scope. Prefix the message with the Jira key when there is one. Examples from the history:

```text
feat(workspace): add a default animated thumbnail rendition
fix(databox): keep the relation extra metadata when copying an asset
fix(ci): build cypress image without host network
PS-1296 feat(databox): mark a story item as cover, create a story with its items
```

## Pull requests

- Keep a pull request focused on one change, and link the issue it addresses.
- Make sure `bin/test.sh quick` passes, and add or update tests.
- Update the documentation in `doc/` when the behaviour changes, and the `UPGRADE-*.md` file when
  the change breaks backward compatibility.
- Fill in the pull request template.

## License

By contributing, you agree that your contributions are licensed under the [MIT License](./LICENSE)
of the project.

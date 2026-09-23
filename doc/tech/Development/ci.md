# Tests and CI tiers

Every test of the stack belongs to one of three tiers. Each tier is a plain
script that runs the same way locally (in Docker) and in GitHub Actions.

| Tier | When (GitHub Actions) | Content | Stack | Budget |
|---|---|---|---|---|
| **quick** | every push on every branch (`quick.yaml`); pull requests from forks | static checks and unit tests | none | < 8 min |
| **standard** | pull requests and pushes on `master` and `release/**`, `workflow_dispatch` (`ci.yaml`) | image builds, full PHP suites, Cypress smoke, GHCR/ECR push | full | ≈ 20 min |
| **release** | tags, nightly on `master` (weekdays 03:00 UTC), `workflow_dispatch` (`release.yaml`) | standard + Doctrine migrations replay + indexer end-to-end; Docker Hub publication on tags only | full + `indexer` profile | ≈ 30 min |

## What each tier runs

### quick — `bin/test.sh quick`

No service is started. Per Symfony project (`composer test:quick`):

- `var-dump-check`, PHPStan, `php-cs-fixer --dry-run` (`composer lint`)
- PHPUnit suite `unit` (`composer phpunit:unit`): plain `TestCase` classes that
  need no database, Elasticsearch, Redis or S3. The suite is the explicit list
  in `phpunit.xml.dist`; everything else is `functional`.

Every lib in `lib/php/*` runs its own `composer test` (var-dump-check, plus
PHPUnit for the libs that have tests).

JS (`pnpm test:quick`): `pnpm lint` (eslint), `pnpm typecheck` (`tsc`) and
`pnpm test` (vitest: `databox/client`, `databox/indexer` unit suite,
`@alchemy/auth`, `@alchemy/i18n`).

In CI the PHP part runs on the runner with `setup-php` and a Composer cache,
the JS part with pnpm; no image is built.

### standard — `bin/test.sh standard`

Needs `db`, `redis`, `elasticsearch`, `minio`, `rabbitmq` (the script starts
them). Per API, inside its image: `composer test` = `lint` + the whole PHPUnit
run (`unit` + `functional`, with the SQLite and Elasticsearch reset of
`pre-phpunit`). Then the libs as in quick.

Cypress (`bin/dev/test-cypress.sh`) needs the stack set up by
`bin/setup.sh test` and loads the expose fixtures first.

### release — `bin/test.sh release`

`standard`, then:

- `bin/dev/test-migrations.sh`: replays every Doctrine migration of each API on
  an empty PostgreSQL database and runs `doctrine:schema:validate`.
  `bin/setup.sh` never replays migrations (it uses `doctrine:schema:update`),
  so this is the only proof that a fresh install works. Non-blocking in CI
  until the current mapping/migrations drift is fixed.
- `bin/dev/test-indexer-e2e.sh`: provisions a fixture tree, indexes it through
  the real databox API, checks the result, indexes again and checks
  idempotence (up to 5 minutes).

## Running locally

Everything goes through Docker (see the root `CLAUDE.md`):

```bash
bin/test.sh quick                      # no stack needed
bin/setup.sh && bin/test.sh standard   # or bin/test.sh (default)
bin/setup.sh && bin/test.sh release
bin/dev/test-cypress.sh                # Cypress alone, stack up
```

One project at a time:

```bash
dc run --rm --no-deps databox-api-php su app -c "composer test:quick"
dc run --rm --no-deps databox-api-php su app -c "composer phpunit:unit"
dc run --rm databox-api-php su app -c "composer phpunit:functional"   # stack up
dc run --rm --entrypoint sh dev -c "su app -c 'pnpm test:quick'"
```

`bin/dev/run-tests-in-ci-conditions.sh [quick|standard|release]` rebuilds the
images and reproduces the CI run in an isolated compose project.

## Adding a test

- A PHP test that boots the kernel or touches a service goes anywhere under
  `tests/`: it lands in the `functional` suite by default.
- A plain `TestCase` can join the `unit` suite: add it to **both** the
  `<testsuite name="unit">` block and the `<exclude>` list of the `functional`
  suite in the project's `phpunit.xml.dist`.
- A JS package gets tests by adding a `test` script (`vitest run`) and a
  `vitest.config.ts`; turbo picks it up in `pnpm test`.
- Heavy scenarios (full-stack, minutes long) go in a script under `bin/dev/`
  and are wired in `bin/test.sh release` and in the release steps of
  `ci.yaml`.

## Commit markers (push events only)

`[skip test]` skips setup, PHP tests and Cypress; `[skip php-test]` and
`[skip cypress]` skip one of them; `[documentation]` triggers the documentation
builder. They read the head commit message, which is empty on `pull_request`,
`schedule` and `workflow_dispatch`, so the tests always run there. The native
`[skip ci]` still skips every workflow.

# Tests and CI tiers

Every test of the stack belongs to one of three tiers. Each tier is a plain
script that runs the same way locally (in Docker) and in GitHub Actions.

| Tier | When (GitHub Actions) | Content | Stack | Budget |
|---|---|---|---|---|
| **quick** | every push on every branch (`quick.yaml`); pull requests from forks | static checks and unit tests | none | < 8 min |
| **standard** | pull requests and pushes on `master` and `release/**`, `workflow_dispatch` (`ci.yaml`) | image builds, full PHP suites, Cypress smoke, GHCR/ECR push | full | ≈ 20 min |
| **release** | tags, nightly on `master` (weekdays 03:00 UTC, skipped when `master` has not moved since the last successful release run), `workflow_dispatch` (`release.yaml`) | standard + Doctrine migrations replay + indexer end-to-end; Docker Hub publication on tags only | full + `indexer` profile | ≈ 30 min |

## What each tier runs

### quick — `bin/test.sh quick`

No service is started. Per Symfony project (`composer test:quick`):

- `var-dump-check`, PHPStan, `php-cs-fixer --dry-run` (`composer lint`)
- PHPUnit suite `unit` (`composer phpunit:unit`): plain `TestCase` classes that
  need no database, Elasticsearch, Redis or S3, under `tests/unit/`;
  everything that needs the stack is under `tests/functional/`.

Every lib in `lib/php/*` runs its own `composer test` (var-dump-check, plus
PHPUnit for the libs that have tests).

JS (`pnpm test:quick`): `pnpm lint` (eslint), `pnpm typecheck` (`tsc`) and
`pnpm test` (vitest: the clients, the `databox/indexer`
unit suite, `@alchemy/api`, `@alchemy/auth`, `@alchemy/next-*`).

In CI the PHP part runs on the runner with `setup-php` and a Composer cache,
the JS part with pnpm; no image is built.

### standard — `bin/test.sh standard`

Needs `db`, `redis`, `elasticsearch`, `minio`, `rabbitmq` (the script starts
them). Per API, inside its image: `composer test` = `lint` + the whole PHPUnit
run (`unit` + `functional`, with the SQLite and Elasticsearch reset of
`pre-phpunit`). Then the libs as in quick.

Cypress (`bin/dev/test-cypress.sh`) needs the stack set up by
`bin/setup.sh test` and loads the expose fixtures first. The CI stack runs with
`APP_ENV=prod` and the default secrets of `.env`: `ci.yaml` and
`bin/dev/run-tests-in-ci-conditions.sh` set `PHRASEA_ALLOW_DEFAULT_SECRETS=1`,
otherwise `bin/setup.sh` refuses them (`check_default_secrets` in `bin/functions.sh`).
They also set the `*_POSTGRES_USER`/`*_POSTGRES_PASSWORD` pairs, so that the
APIs, Keycloak and report-api connect with a role of their own, as a hardened
production would (see [Database roles](../01_setup.md#database-roles));
`bin/dev/test-migrations.sh` replays the migrations as the superuser, which
creates and drops the scratch databases. Likewise, the `*_S3_ACCESS_KEY`/
`*_S3_SECRET_KEY` pairs give the APIs, the clients and the indexer a MinIO user
restricted to their bucket (see [MinIO users](../01_setup.md#minio-users)),
created by `minio-setup` in `bin/setup.sh` and `bin/test.sh standard`.

### Image builds

`ci.yaml` builds the images with `docker buildx bake` and
[`docker-bake.hcl`](../../../docker-bake.hcl), one runner per group (`php`:
configurator and the three APIs; `node`: the Next.js clients and
`databox/indexer`). Each job first builds and pushes the base images of its
group (`php-base`, `node-base`: `infra/docker/*`), then the images built FROM
them, on the same builder. Each image is pushed to GHCR tagged with the commit
sha, next to its registry cache (`:build-cache-1`).

The bases are not passed as `target:` named contexts: a base inlined that way
gets a new cache key in every BuildKit session from its first step reading its
local context (`COPY entrypoint.sh`), so every image built on it would be
rebuilt on each run.

Always pass `-f docker-bake.hcl`: without it, bake also reads
`docker-compose.yml`, whose services share some target names.

```bash
docker buildx bake -f docker-bake.hcl --print          # resolved configuration
```

### Keycloak and report-api in CI

The Keycloak and report-api images are built outside this repository and are
pulled from `public.ecr.aws`. Anonymous pulls from the shared GitHub runners
hit its rate and data limits, so `ci.yaml` logs in to ECR Public with the
`AWS_ECR_ACCESS_KEY_ID`/`AWS_ECR_ACCESS_KEY_SECRET` secrets before pulling.

Outside the CI, a stack can still run without them:
`CONFIGURATOR_CONFIGURE_KEYCLOAK=false` and an empty `REPORT_API_URL` make
`bin/setup.sh` and `bin/test.sh` leave out Keycloak (`compose_up_services` in
`bin/functions.sh`: Keycloak has no profile, so the other services are listed
explicitly) and the `report` profile, and the configurator skip its Keycloak
configuration and migrations (`KeycloakMigrationTrait`). The PHP suites pass
that way, but Cypress and the indexer end-to-end log in through Keycloak.

### Image push

The ECR Public push of `ci.yaml` and the Docker Hub pushes of `release.yaml`
go through `bin/ci-push-images.sh`: one image at a time, with up to 5 attempts
(30, 60, 90 then 120 s apart), because a parallel push exceeds the request rate
limit of ECR Public (`Rate exceeded`). A new attempt skips the layers already
pushed. If the failures go on: `max-concurrent-uploads` in the dockerd
configuration, or the ECR Public quotas in Service Quotas (us-east-1).

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

- PHP tests of an API live under `tests/unit/` (namespace `App\Tests\Unit\`)
  or `tests/functional/` (namespace `App\Tests\Functional\`); the directory
  is the suite, `phpunit.xml.dist` lists nothing else. A plain `TestCase` that
  needs no service goes in `unit`; anything that boots the kernel or touches
  the database, Elasticsearch, Redis or S3 goes in `functional`. A unit test
  never depends on a functional helper (the reverse is fine). Shared input
  files stay in `tests/fixtures/`.
- A JS package gets tests by adding a `test` script (`vitest run`) and a
  `vitest.config.ts`; turbo picks it up in `pnpm test`.
- Heavy scenarios (full-stack, minutes long) go in a script under `bin/dev/`
  and are wired in `bin/test.sh release` and in the release steps of
  `ci.yaml`.

## Commit markers (push events only)

`[skip test]` skips setup, PHP tests and Cypress; `[skip php-test]` and
`[skip cypress]` skip one of them. They read the head commit message, which is
empty on `pull_request`, `schedule` and `workflow_dispatch`, so the tests
always run there. The native `[skip ci]` still skips every workflow.

## Documentation builder

`ci.yaml` triggers `alchemy-fr/phrasea-documentation-builder` (`documentation`
job) once the `setup` job has passed, tests included (`[skip test]` lets it
through). The builder pulls the images it reads `/srv/app/doc` from (`databox`,
`expose` and `uploader` `api-php`, `configurator`) from GHCR, tagged with the
commit sha, and checks out the branch for `doc/` (so the branch name cannot
contain a `/`). It runs:

- on every push on `next`;
- on a push on `master` or `release/**` whose commits contain `[documentation]`;
- on a pull request from this repository when one of its commits contains
  `[documentation]` (on each update of the pull request);
- on demand: `workflow_dispatch` of `ci.yaml` with the `documentation` input,
  which also works on a branch without a pull request.

Tags are left to `release.yaml`, which triggers the builder after the Docker
Hub publication to rebuild every released version.

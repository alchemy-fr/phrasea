# Tests and CI tiers

Every test of the stack belongs to one of three tiers. Each tier is a plain
script that runs the same way locally (in Docker) and in GitHub Actions.

| Tier | When (GitHub Actions) | Content | Stack | Budget |
|---|---|---|---|---|
| **quick** | every push on every branch (`quick.yaml`); pull requests from forks | static checks and unit tests | none | < 8 min |
| **standard** | pull requests and pushes on `master` and `release/**`, `workflow_dispatch` (`ci.yaml`) | image builds, full PHP suites, Cypress smoke, GHCR/ECR push | full | ≈ 15-20 min |
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

`bin/test.sh standard [PART]` splits it between CI jobs; the parts together
run exactly what `all` runs:

| PART | Content |
|---|---|
| `all` (default) | everything above, one after the other |
| `databox-functional:I/N` | shard `I` of `N` of the databox `functional` suite: `pre-phpunit`, then `bin/phpunit-shard I/N` |
| `others` | databox `lint` + `phpunit:unit`, `composer test` of expose, uploader and configurator, the libs |

`databox/api/bin/phpunit-shard I/N` sorts the `*Test.php` files of
`tests/functional` and deals them round-robin (file `k`, from 0, goes to shard
`k % N + 1`): the big directories (`Api`, `Search` and its slow
`AbstractSearchTestCase`) spread over every shard, and a new test file is
picked up without any list to maintain. `--list-files` prints the files of a
shard; the other options go to PHPUnit:

```bash
dc run --rm --no-deps databox-api-php su app -c "php bin/phpunit-shard 1/2 --list-files"
dc run --rm databox-api-php su app -c "composer pre-phpunit && php -d memory_limit=1024M bin/phpunit-shard 1/2"
```

Cypress (`bin/dev/test-cypress.sh`) needs the stack set up by
`bin/setup.sh test` and loads the expose fixtures first. `--shard I/N` runs
shard `I` of `N` only: the `cypress/cypress/e2e/**/*.cy.js` specs, sorted,
dealt round-robin like the PHPUnit files (the expose fixtures are only loaded
for a shard holding an `expose/` spec), and `--list` prints the specs that
would run without starting anything. Every spec seeds the data it needs, so
that it passes on a fresh stack whatever ran before it. The CI stack runs with
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

### Jobs of `ci.yaml`

```
build (php, node) ─┬─► php-tests  (databox-functional-1, databox-functional-2, others)
                   ├─► cypress    (shards 1/3, 2/3, 3/3)
                   ├─► release-checks (tier release only)
                   └─► push-ecr   (after every test job)
documentation_requested ─► documentation (after every test job)
```

Each test job runs on a runner of its own, with a stack of its own; the
matrices do not fail fast, so that one failing shard does not hide the others.
The shared steps (mkcert, logins to GHCR and ECR Public, image pulls) are the
local composite action `.github/actions/prepare-stack`.

- `php-tests`: `bin/test.sh standard <part>`, one matrix entry per part. No
  `bin/setup.sh` nor certificate: the script starts the services it needs.
  Only the images of the services it starts are pulled.
- `cypress`: `bin/setup.sh test`, then `bin/dev/test-cypress.sh --shard
  <shard>/<number of shards>`. Adding a shard is adding its number to the
  matrix. On failure, the screenshots, JUnit results and container logs are
  uploaded as `phrasea-output-cypress-<shard>`.
- `release-checks` (tier `release` only): `bin/setup.sh test`, then
  `bin/dev/test-migrations.sh` and `bin/dev/test-indexer-e2e.sh`.
- `push-ecr`: see [Image push](#image-push). It runs once every test job has
  passed or was skipped (`release-checks` outside the release tier, the
  [commit markers](#commit-markers-push-events-only)), never after a failed
  or cancelled one: ECR only receives tested images.

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

The `push-ecr` job of `ci.yaml` copies the images of the run from GHCR to ECR
Public with `bin/ci-copy-images.sh`, registry to registry (`docker buildx
imagetools create`: nothing is pulled nor unpacked on the runner). It copies
the image of every compose service with a `build` section, `configurator` and
`databox-indexer` included (profiles `configurator,indexer` added), from
`ghcr.io/<owner>/ps-<name>:<sha>` to
`public.ecr.aws/b2s9z7l1/ps-<name>:<branch>`, and prints the list first.

The Docker Hub pushes of `release.yaml` go through `bin/ci-push-images.sh`
(`docker compose push`). Both scripts handle one image at a time, with up to 5
attempts (30, 60, 90 then 120 s apart), because a parallel push exceeds the
request rate limit of ECR Public (`Rate exceeded`). A new attempt skips the
layers already pushed. If the failures go on: `max-concurrent-uploads` in the dockerd
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
bin/test.sh standard databox-functional:1/2   # one part, as a CI job runs it
bin/setup.sh && bin/test.sh release
bin/dev/test-cypress.sh                # Cypress alone, stack up
bin/dev/test-cypress.sh --shard 2/3    # one Cypress shard
bin/dev/test-cypress.sh --shard 2/3 --list   # its specs
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
  and are wired in `bin/test.sh release` and in the `release-checks` job of
  `ci.yaml`.
- A new PHPUnit file of databox `tests/functional` or a new Cypress spec needs
  no CI change: the shards are computed at each run.

## Commit markers (push events only)

`[skip test]` skips the `php-tests`, `cypress` and `release-checks` jobs;
`[skip php-test]` and `[skip cypress]` skip one of the first two. `push-ecr`
and `documentation` still run. They read the head commit message, which is
empty on `pull_request`, `schedule` and `workflow_dispatch`, so the tests
always run there. The native `[skip ci]` still skips every workflow.

## Documentation builder

`ci.yaml` triggers `alchemy-fr/phrasea-documentation-builder` (`documentation`
job) once the test jobs have passed (`[skip test]` lets it through). The builder pulls the images it reads `/srv/app/doc` from (`databox`,
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

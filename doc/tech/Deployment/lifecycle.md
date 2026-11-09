# Install and upgrade lifecycle

Every image that owns persistent state (`configurator`, `databox-api-php`,
`expose-api-php`, `uploader-api-php`) exposes the same entry point:

```bash
bin/lifecycle.sh <phase>
```

The orchestrators (the root [`bin/setup.sh`](@phrasea-repo/bin/setup.sh) and
[`bin/migrate.sh`](@phrasea-repo/bin/migrate.sh) for Docker Compose, the Jobs of
the `alchemy-fr/phrasea-helm-charts` chart for Kubernetes) only decide **when**
each phase runs. **What** a phase does belongs to the image.

## Contract

| Phase          | When                                                         | Preconditions                                                    | Role                                                                                         |
|----------------|--------------------------------------------------------------|------------------------------------------------------------------|----------------------------------------------------------------------------------------------|
| `pre-install`  | before the workloads of the app first start                  | infrastructure reachable: PostgreSQL, RabbitMQ, S3, ES, Keycloak | provision the resources and create the schema                                                |
| `post-install` | after the workloads started                                  | workloads of the new version started                             | steps that need the app running (none yet)                                                   |
| `pre-upgrade`  | before the workloads are replaced, with the **new** image    | infrastructure reachable; the old version may still run          | provision what the new version adds and run the migrations (backward compatible with the old version) |
| `post-upgrade` | after the new version is deployed                            | new workloads started                                            | none yet                                                                                     |

- A phase without any step exits with `0`; an unknown phase exits with `64` and
  prints the usage.
- **Order between components, within a phase:** the configurator first, then
  the APIs. The APIs are independent from each other and can run in parallel.
- **Waiting:** each phase waits by itself, within a time limit, for the services
  it needs (`LIFECYCLE_WAIT_TIMEOUT`).
- **Exit code:** non-zero on failure. No partial state prevents running the
  phase again.
- **Configuration:** environment variables only (see
  [Environment variables](#environment-variables)).

## State detection and convergent phases

Each component detects its own state with the `alchemy:lifecycle:state` command
(`alchemy/core-bundle`), which reads the connection and the table of its
Doctrine migrations configuration (`doctrine_migration_versions` for databox
and the configurator, `migration_versions` for expose and uploader):

- `installed` when the migrations table exists and holds at least one row;
- `fresh` otherwise (no table, or an empty one).

The phases converge on that state rather than trusting their name, so every
phase can be run again safely:

- a `pre-*` phase on an **installed** database migrates it (for instance a
  `pre-install` after a database restore);
- a `pre-*` phase on a **fresh** database installs it (for instance an app added
  to the stack by an upgrade).

The destructive install path (`fos:elastica:reset`, `doctrine:schema:update -f`,
`doctrine:migrations:version --add --all`) is only reachable when no migration
is recorded. An install interrupted before its last step leaves the database
`fresh`: the next run completes the partial schema and marks the migrations
only at the very end.

## Steps per component

`post-install` and `post-upgrade` do nothing yet in any component: they are the
extension points of the contract.

### Common steps

The `bin/lifecycle.sh` of each image only runs `composer install` when
`vendor/` is missing (development), then hands over to the script shared by
every component,
[`lib/php/core-bundle/Resources/lifecycle/lifecycle.sh`](@phrasea-repo/lib/php/core-bundle/Resources/lifecycle/lifecycle.sh)
(shipped with `alchemy/core-bundle`, next to `alchemy:lifecycle:state`).
`pre-install` and `pre-upgrade` run the same steps:

1. wait for PostgreSQL (`DATABASE_URL`, `CONFIGURATOR_DATABASE_URL` for the
   configurator, else `POSTGRES_HOST`/`POSTGRES_PORT`);
2. `doctrine:database:create --if-not-exists`;
3. the `prepare` hooks;
4. `alchemy:lifecycle:state`, then:
    - `fresh`: `doctrine:schema:update -f`,
      `doctrine:migrations:sync-metadata-storage`, the `install` hooks,
      `doctrine:migrations:version --add --all`;
    - `installed`: `doctrine:migrations:sync-metadata-storage`, the `upgrade`
      hooks, `doctrine:migrations:migrate`.

### Hooks

A component adds its own steps in `bin/lifecycle.d/<NN>-<name>.<step>.sh`, where
`<step>` is `prepare`, `install` or `upgrade`. The hooks of a step are sourced
in name order by the common script, so they can use `CONSOLE` (the Symfony
console), `log` and `wait_for <description> <command...>`; a failing command
fails the phase.

The steps are named after the state of the database rather than after the
phases: a `pre-upgrade` on a fresh database runs the `install` hooks.

| Component    | Hook                              | Steps                                                                       |
|--------------|-----------------------------------|-----------------------------------------------------------------------------|
| databox      | `01-database.prepare.sh`          | `app:database:configure` (idempotent PostgreSQL extensions)                 |
| databox      | `01-elasticsearch.install.sh`     | wait for Elasticsearch (`ELASTICSEARCH_URL`), `fos:elastica:reset`, `fos:elastica:populate` |
| configurator | `01-configure.install.sh`         | `configure --install` with the `CONFIGURATOR_PRESETS`                       |
| configurator | `01-configure.upgrade.sh`         | `configure` with the `CONFIGURATOR_PRESETS`                                 |

Expose and uploader have no hook. An upgrade does not need Elasticsearch: the
migrations are plain SQL, so databox only waits for it on an install.
`configure --install` builds the current state of every service, so the
Keycloak migrations are not replayed on a new installation: they are marked as
executed with the others.

`configure` provisions every enabled service (`CONFIGURATOR_CONFIGURE_<NAME>`):
databases, RabbitMQ vhosts, S3 buckets, Minio notifications, then the Keycloak
realm, roles, scopes and clients. It runs in both modes, so a resource added by
a new version is created on upgrade. Only `--install` creates the default admin
user (`DEFAULT_ADMIN_USERNAME`): on an upgrade, an admin deleted by the
operator is not created again with the default password.

## Docker Compose

Install, with the root `bin/setup.sh [preset...]`:

1. start the infrastructure (`traefik minio rabbitmq db redis elasticsearch`,
   plus `keycloak` unless `CONFIGURATOR_CONFIGURE_KEYCLOAK=false`);
2. `pre-install` on the configurator (with `CONFIGURATOR_PRESETS` set from the
   arguments), then on the three APIs in parallel;
3. `docker compose up -d`;
4. `post-install` on the configurator, then on the APIs.

Upgrade, with the root `bin/migrate.sh`:

1. start the infrastructure only (same list);
2. `pre-upgrade` on the configurator, then on the APIs, in one-off containers
   (`docker compose run --rm`), so with the new images while the old containers
   may still run;
3. `docker compose up -d`;
4. `post-upgrade` on the configurator, then on the APIs.

Running `bin/migrate.sh` on an up-to-date stack migrates nothing (the CI checks
it right after `bin/setup.sh test`).

## Helm

The chart runs one Job per (component, phase), named `<component>-<phase>`,
with `args: ["bin/lifecycle.sh", "<phase>"]`:

| Phase          | Helm hook      | configurator weight | apps weight |
|----------------|----------------|---------------------|-------------|
| `pre-install`  | `post-install` | 4                   | 5           |
| `post-install` | `post-install` | 6                   | 7           |
| `pre-upgrade`  | `pre-upgrade`  | 0                   | 1           |
| `post-upgrade` | `post-upgrade` | 0                   | 1           |

Both install phases are Helm `post-install` hooks: a real Helm `pre-install`
hook runs before any resource of the release exists, so it cannot reach the
infrastructure the chart embeds (PostgreSQL, RabbitMQ, Minio, Keycloak,
Elasticsearch). Their weights stay below the other install hooks
(`create-databox-target` at 10, fixtures at 15).

The install phases run when `stack.running` and `stack.runSetups` are true, the
upgrade phases when `stack.running` and `stack.runMigrations` are true. Every
phase of a component gets the same environment, and the configurator presets
(`configurator.presets`) reach the Job through `CONFIGURATOR_PRESETS`.

## Environment variables

| Variable                             | Used by      | Default | Description                                                                                                  |
|--------------------------------------|--------------|---------|--------------------------------------------------------------------------------------------------------------|
| `LIFECYCLE_WAIT_TIMEOUT`             | all          | `300`   | Seconds a phase waits for PostgreSQL (and Elasticsearch for a databox install) before failing.               |
| `CONFIGURATOR_PRESETS`               | configurator | (empty) | Space-separated presets of `configure` (`test`, `dev`).                                                      |
| `CONFIGURATOR_SERVICE_WAIT_TIMEOUT`  | configurator |         | Still applies to the waits of `configure` itself (RabbitMQ, S3, Minio, Keycloak).                            |
| `CONFIGURATOR_CONFIGURE_<NAME>`      | configurator | `false` | Enables a configurator (`DATABASE`, `KEYCLOAK`, `MINIO`, `RABBITMQ`, `S3`). The Keycloak migrations are skipped (and not recorded) while `CONFIGURATOR_CONFIGURE_KEYCLOAK` is not true. |

## Compatibility

The former scripts of the images remain as deprecated wrappers, so that a chart
calling them still works with the new images:

- `bin/setup.sh` runs `pre-install` then `post-install`. The configurator one
  turns its positional arguments into `CONFIGURATOR_PRESETS`;
- `bin/migrate.sh` runs `pre-upgrade` then `post-upgrade`.

The configurator `synchronize` command is removed: `configure` in upgrade mode
replaces it.

## Known limits

- **Keycloak start order with Compose:** Keycloak starts before the
  configurator created its database; it only recovers thanks to
  `restart: on-failure`.
- **Overwritten on every run:** the policy of the configurator bucket, the Minio
  notifications and the realm settings (as `synchronize` already did).
- **Retries and errors:** the configurator retries 4xx answers but not the S3
  or DBAL errors; `S3Manager` swallows every 409; bucket creation sends no
  `LocationConstraint`.
- **Hard-coded values:** the lists of apps and scopes duplicated in the Keycloak
  migrations, the `s3events` vhost, `server_version: '11.2'`.
- **Keycloak enabled after the installation:** the migrations are marked as
  executed on a fresh install even when `CONFIGURATOR_CONFIGURE_KEYCLOAK` is
  false. Enabling it later configures Keycloak through `configure`, but without
  `--install`, so the default admin user is not created.
- **Restore:** Elasticsearch is not repopulated when a restored database is
  detected as `installed`. Run `bin/console fos:elastica:populate` in
  `databox-api-php` yourself.
- **Chart hooks not migrated:** `create-databox-target`, the fixtures, the
  indexer and `es-index-cleanup` keep their own Jobs.
- **Ops scripts derived from the chart** (`bin/ops/configurator-configure.sh`,
  `bin/ops/configurator-migrate.sh`, `bin/ops/run-k8s-configurator-shell.sh`)
  point to chart templates or values that no longer exist.
- **Dead code:** `Migration20230807Command` (2.x to 3.0 migration) is left as is.
- **Miscellaneous:** the `CONFIG_IS_PUBLIC` logic of `get-config.sh` looks
  inverted, and nothing creates `/docker/worker-ready`.

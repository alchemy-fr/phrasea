---
title: 'Console commands'
---

# Console commands

The Databox API ships console commands for the operators: workspace templates, maintenance of
the attributes, the search index and the file analyses, backfills of the AI integrations,
notifications, purges. This page lists every command declared by Databox
(`databox/api/src/Command/`) and by the shared bundles it loads (`lib/php/*`), then the periodic
tasks that run some of them.

## Running a command

Every command runs inside the API container, as the `app` user:

```bash
dc run --rm databox-api-php su app -c "bin/console app:search:validate"
```

`bin/console list app` lists the Databox commands, `bin/console help app:workspace:import` shows
the arguments and options of one of them. Options are written in kebab case (`--dry-run`,
`--with-access`); a command that asks for a confirmation needs `--force` when it runs without a
terminal.

:::warning
Never run `bin/console` as root (without `su app`): it writes root-owned files into the cache
directory, which the `app` user then fails to remove.
:::

## Workspaces

| Command                       | Purpose                                                                                                      | Arguments and options                                                                                                                                                                                                 |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app:workspace:export`        | Prints a workspace as a template (JSON on stdout)                                                            | `workspace` (ID); `--with-access`: owners, access control entries, user/group targets and private data templates (only meaningful on the same instance); `--with-secrets`: the encrypted secret values (only importable on the same instance) |
| `app:workspace:import`        | Creates a workspace from a template read on stdin, or updates the existing workspace of that name            | `name`; `--slug`, `-s` (on creation, the slugified name by default); `--owner`, `-o` (required on creation); `--force`, `-f` (required to update an existing workspace)                                              |
| `app:workspace:duplicate`     | Creates a new workspace from an existing one and copies its settings                                         | `workspaceId`, `newSlug`                                                                                                                                                                                              |
| `app:workspace:delete`        | Moves the workspace to the trash if needed, then deletes it with all its content; does nothing if it does not exist | `workspaceId`; `--force`, `-f`: no confirmation                                                                                                                                                                 |
| `app:workspace:ingest-assets` | Triggers the ingest workflow of every asset having a source file                                             | `workspaces`: IDs or slugs (every workspace by default)                                                                                                                                                               |

```bash
dc run --rm -T databox-api-php su app -c "bin/console app:workspace:export 4fe7bbba-0bb3-496b-8d8c-1234567890aa" > template.json
dc run --rm -T databox-api-php su app -c "bin/console app:workspace:import 'New workspace' --owner 4fe7bbba-0bb3-496b-8d8c-1234567890aa" < template.json
```

The export and the import go through `App\Service\Workspace\WorkspaceTemplater`; **Save as
template** in the admin (**Workspaces**, detail page) builds the same template as a
**Workspace Template** (see [Administration](./administration.md)).

## Attributes and entities

| Command                                | Purpose                                                                                     | Arguments and options                                     |
| -------------------------------------- | ------------------------------------------------------------------------------------------- | --------------------------------------------------------- |
| `app:attributes:split`                 | Turns the values of a single-valued attribute definition into multiple values               | `attributeDefinitionId`; `--delimiter`, `-d` (`;`)        |
| `app:diagnose:attribute-entity-links`  | Reports the attribute values pointing to a missing attribute entity, for the definitions bound to an entity list | `--fix`: removes these attributes                         |

See [Entity lists](./entity-lists.md).

## Collections

| Command                            | Purpose                                                                                                                                                     | Arguments and options |
| ---------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------- |
| `app:collection:recompute-access`  | Rebuilds the `collection_access` table (who may list each collection: owners, privacy, access control entries of the collection, and of the workspace for the root collections) for every collection; PostgreSQL only | none                  |

See [Access control](./access-control.md).

## Search and indexing

| Command                   | Purpose                                                                                                                              | Arguments and options                                                                                                                                                                                                           |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app:search:validate`     | Checks whether the `collection` and `asset` indices must be populated again; exit code `0` synced, `1` to populate, `2` never populated | none                                                                                                                                                                                                                            |
| `app:es:populate-unlock`  | Force-releases the populate lock of one or every index after a worker died mid-populate, and closes its open populate passes          | `index` (every locked index by default); `--force`, `-f`: no confirmation; `--even-if-alive`: also release a lock whose populate gave a sign of life less than 5 minutes ago                                                    |
| `alchemy:es:debug-index`  | Prints the settings, mappings and aliases of the physical index behind each logical index (read-only)                                | `--index`, `-i` (every configured index by default)                                                                                                                                                                             |
| `alchemy:es:delete-index` | Deletes physical indices and their aliases, after listing them and asking for a confirmation                                         | `--index`, `-i` (every configured index by default); `--remove-olds`: also the dated indices left by previous populates; `--olds-only`: only these; `--force-prefix`: every physical index of the logical index, whatever its alias; `--force`, `-f` |

`fos:elastica:populate` (FOS Elastica) rebuilds the indices; the populate, its lock and its passes
are described in [Populate](./es_populate.md). The same operations are available in the admin
(**Maintenance** → **Populate Passes** and **ES Indices & Aliases**).

:::warning
Never release the lock of a populate that is still running: another populate of the same index
could then run alongside it.
:::

## Workflows

| Command                             | Purpose                                                                                                                                      | Arguments and options |
| ----------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- | --------------------- |
| `app:workflow:trigger-asset-ingest` | Assigns its source file to the asset again: resets the analysis of the file, re-picks the renditions using the source, then triggers the ingest workflow | `assetId`             |
| `alchemy:workflow:run`              | Starts a workflow by name                                                                                                                    | `name`                |
| `alchemy:workflow:dump-workflow`    | Prints the state and the plan of a workflow run                                                                                              | `id` (workflow ID)    |

See [Workflows](./workflows.md).

## Files and analyses

| Command                                  | Purpose                                                                                                                                       | Arguments and options                                                                                                   |
| ---------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| `app:file:unblock-analyses`              | Re-triggers the analysis of the files stuck in the pending state (dispatches the ingest, reruns the analyzer job or retries the failed jobs) | `--older-than`, `-t` (seconds, `3600`); `--limit`, `-l` (files per run, `500`); `--dry-run`; `-v` lists the skipped files |
| `alchemy:storage:prune-multipart-uploads` | Aborts the abandoned multipart uploads on S3 and deletes the unused ones                                                                      | `--min-age` (hours, `24`, at least `3`: the lifetime of the part URLs)                                                  |

See [File analyzers](./FileAnalyzers.mdx).

## Renditions

| Command                                   | Purpose                                                                                     | Arguments and options                                                                                                                                                                                   |
| ----------------------------------------- | ------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `alchemy:rendition-factory:create`        | Builds a rendition of a file (or of every file of a directory) from a build config YAML file | `src`, `buildConfig`; `--type`, `-t`: force the MIME type; `--working-dir`, `-w` (system temp directory); `--output`, `-o`: output file name without extension; `--debug`, `-d`: keep the working files |
| `alchemy:rendition-factory:conf:validate` | Validates a build config YAML file                                                          | `config`                                                                                                                                                                                                |

Without `--debug`, the working directory is cleaned up: give `--output` to keep the result. See
[Renditions](./Renditions.mdx).

## Metadata

| Command                             | Purpose                                                                                     | Arguments and options                                                         |
| ----------------------------------- | ------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| `alchemy:metadata-manipulator:dump` | Prints the metadata read by ExifTool from a file, or the dictionary of known tags without a file | `file` (optional); `--filter`: regular expression on the tag IDs (`^XMP`) |

## AI integrations

These commands backfill the workspaces where the integration is enabled; by default they
dispatch one message per asset to the workers.

| Command                        | Purpose                                                                                         | Arguments and options                                                                                      |
| ------------------------------ | ----------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| `app:face-recognition:index`   | Runs the face detection on the assets                                                           | `--workspace`, `-w`; `--sync`: detect in the command instead of dispatching messages                       |
| `app:face-recognition:photos`  | Gives each person of the face recognition list the photo of their best face                     | `--workspace`, `-w`                                                                                        |
| `app:face-recognition:relink`  | Replays the matching of the detected faces against the persons (no detection)                   | `--workspace`, `-w`                                                                                        |
| `app:similarity:index`         | Computes the embeddings of the assets                                                           | `--workspace`, `-w`; `--sync`: compute in the command; `--force`: recompute the existing embeddings       |

See [Integrations](./integration/01_integrations.md).

## Integrations

| Command                        | Purpose                                                                                     | Arguments and options                                              |
| ------------------------------ | ------------------------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| `app:integration:renew-tokens` | Renews the integration tokens whose refresh token expires soon                              | `--threshold`, `-t` (seconds before expiry, `7200`)                |

## Notifications

| Command                         | Purpose                                                                                                   | Arguments and options                                                                                                                                                           |
| ------------------------------- | --------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `alchemy:notifier:broadcast`    | Sends a topic notification to a whole audience (every user of the identity provider by default)          | `topic`; `payload`: JSON template parameters (`{}`); `--channel`, `-c` (repeatable: `email`, `sms`, `in_app`); `--audience`, `-a`: user directory; `--exclude-user`: a user ID to leave out |
| `alchemy:notifier:digest:flush` | Sends the notification digests whose inactivity window has elapsed                                        | `--force`, `-f`: flush every buffered digest                                                                                                                                    |

```bash
dc run --rm databox-api-php su app -c "bin/console alchemy:notifier:broadcast admin:message '{\"subject\":\"Maintenance\",\"body\":\"<p>Tonight</p>\"}'"
```

Nothing is sent while `NOTIFICATIONS_ENABLED` is off. See [Notifications](./notifications.md).

## Automations

| Command                  | Purpose                                                                           | Arguments and options |
| ------------------------ | --------------------------------------------------------------------------------- | --------------------- |
| `app:automation:run-due` | Triggers once the scheduled automations of the saved searches which are due       | none                  |

The scheduler triggers them every minute; the command is meant for development. See
[Automations](./automations.md).

## Audit log, trash and secrets

| Command                        | Purpose                                                                                                                       | Arguments and options                                                                         |
| ------------------------------ | ----------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| `app:audit-log:purge`          | Deletes, by batches, the audit log entries older than the retention delay                                                     | `--days`: instead of `AUDIT_LOG_RETENTION_DAYS` (`365` when empty; `0` keeps everything)      |
| `app:trash:empty`              | Deletes for good the collections and assets of each workspace that stayed in the trash longer than its trash retention delay | none                                                                                          |
| `app:secrets:generate-key-pair` | Prints a new key pair for the encryption of the workspace secrets                                                            | none                                                                                          |

The printed keys go to `DATABOX_SECRETS_PUBLIC_KEY` and `DATABOX_SECRETS_SECRET_KEY` (the `.env`
values are public: change them in production). See [Audit log](./audit-log.md),
[Asset lifecycle](./asset-lifecycle.md) and [Secrets](./secrets.md).

## Configuration and setup

| Command                           | Purpose                                                                                      | Arguments and options |
| --------------------------------- | -------------------------------------------------------------------------------------------- | --------------------- |
| `app:database:configure`          | Creates the PostgreSQL `ltree` extension (run by `databox/api/bin/setup.sh` before the schema) | none                  |
| `configurator:push-to-bucket`     | Writes the global configuration to the configurator bucket, like **Push Configuration** in the admin | none                  |
| `alchemy:core:documentation:dump` | Writes the code-generated documentation to a directory                                       | `destination`         |

The setup (`databox/api/bin/setup.sh`) and upgrade (`databox/api/bin/migrate.sh`) scripts chain
the framework commands: `doctrine:database:create`, `app:database:configure`,
`doctrine:schema:update`, `fos:elastica:reset` and `fos:elastica:populate` on install,
`doctrine:migrations:migrate` on upgrade. See [Configuration](./configuration.md).

## Periodic tasks

The periodic tasks are planned by Symfony Scheduler in `databox/api/src/Schedule/MainSchedule.php`
(`#[AsSchedule('main')]`) and consumed by the single-replica `databox-scheduler` container
(`WORKER_PRIORITIES=scheduler_main`). No host cron is needed.

| Task                                           | Frequency                        |
| ---------------------------------------------- | -------------------------------- |
| `DispatchDueAutomations` (due automations)     | every minute                     |
| `alchemy:notifier:digest:flush`                | hourly (`#hourly`)               |
| `app:integration:renew-tokens`                 | hourly                           |
| `app:file:unblock-analyses`                    | hourly                           |
| `app:trash:empty`                              | daily (`#midnight`)              |
| `app:audit-log:purge`                          | daily                            |
| `alchemy:storage:prune-multipart-uploads`      | daily                            |

The tasks run one after the other in one process, at a minute derived from each task (hashed
expressions), shifted by `SCHEDULER_OFFSET`; a lock and a stored state keep them from running
twice when two scheduler containers overlap, and only the last missed run is caught up. See
[Periodic tasks](../01_setup.md#periodic-tasks) and [Workers](./workers.md).

## See also

- [Administration](./administration.md)
- [Workers](./workers.md)
- [Populate](./es_populate.md)
- [Environment variables](../Configuration/01_env_var.mdx)

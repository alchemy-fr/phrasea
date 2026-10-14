# Indexer end-to-end suite

Runs `databox-indexer index` against the **real Docker stack** and asserts the
result through the databox API.

```bash
bin/dev/test-indexer-e2e.sh
```

## How it is split

`bin/dev/test-indexer-e2e.sh` only orchestrates. Every step runs in the
`databox-indexer` container; the ones that assert are vitest files, driven one
at a time because an indexation has to happen between them.

| Step | What runs |
|---|---|
| 1. provision the fixtures and the indexer config | `tests/e2e/01-provision.test.ts` |
| 2. index them | `node dist/console.mjs index e2e_fs --no-server --debug` |
| 3. check what landed in databox | `tests/e2e/02-indexation.test.ts` |
| 4. index them again | the same command |
| 5. check that nothing was duplicated | `tests/e2e/03-idempotence.test.ts` |
| 6. remove the workspace | `tests/e2e/99-cleanup.test.ts` |

The steps share a `/e2e` volume created per run: the fixtures and the config go
in at step 1, the indexer drops `run1.log` / `run2.log` next to them, and step 3
leaves a `snapshot.json` for step 5 — the two verification passes are separate
vitest runs, so the state to compare has to survive on disk.

## This suite is not part of `pnpm test`

`vitest.config.ts` only includes `tests/unit/**`, and this suite has its own
`vitest.e2e.config.ts`. `pnpm test`, and therefore `turbo run test` at the
repository root, never runs it; `pnpm test:e2e` does, but only makes sense
inside the container the script starts.

## What is checked

- the workspace exists;
- the collection tree: the name and the nesting of each collection, as a
  `/e2e/level1/level2` path;
- one asset per file, in the right reference collection — an asset is
  identified by the `indexer://` alternate URL of its source file, which is
  what validates the space and the accents end to end;
- the membership in `collections`, which is distinct from
  `referenceCollection` (the latter carries the permission inheritance);
- no asset and no collection for the hidden file and the hidden directory, and
  no soft-deleted leftover (`include_deleted=true`);
- the blacklisted files were walked and *then* filtered, proven by the indexer
  log rather than by their absence;
- the indexer logged no error;
- on the second pass: the asset and collection **ids** are unchanged, which
  proves they were found by key rather than deleted and recreated.

## Layout

| File | Role |
|---|---|
| `01-provision.test.ts` | fixture tree, config, owner id, stale workspace |
| `02-indexation.test.ts`, `03-idempotence.test.ts` | the checks |
| `99-cleanup.test.ts` | removes the workspace |
| `fixtures.ts` | the fixture tree **and** the expectations derived from it |
| `indexerConfig.ts` | the generated `config.e2e.json` |
| `databox.ts` | the typed databox API client |
| `expect.ts` | the comparison helpers |
| `paths.ts` | the layout of the shared `/e2e` volume |
| `env.ts` | the environment the container was started with |

`fixtures.ts` is the single source of truth: adding a file to `FILES_INDEXED`
moves the fixtures and the expectations together.

## Requirements

- The stack must be running: `db`, `elasticsearch`, `keycloak`, `redis`,
  `databox-api-php`, `databox-api-nginx`, plus `soketi` and `traefik` in
  `APP_ENV=dev`. The script checks and names the missing ones.
- The databox schema must be up to date
  (`docker compose exec databox-api-php su app -c "bin/console doctrine:migrations:migrate"`).
- `bin/setup.sh` must have run at least once: it is what creates the
  `databox-indexer` Keycloak client the suite authenticates with.
- Elasticsearch is needed both because the assertions read through it and
  because, in `APP_ENV=dev`, the Messenger transports are synchronous and
  deleting the workspace at cleanup reindexes. For the same reason `soketi`
  must be up in dev — the websocket broadcast that follows `POST /assets` runs
  inside the request — and `traefik` with it, since the Pusher client reaches
  soketi through its public URL.
- `redis` backs the Symfony cache. Without it every request spends 30s failing
  to reach it, which trips the indexer's own 30s timeout. That is why the
  script refuses to start without it rather than letting the indexation fail
  halfway through.

## Options

```
--slug <slug>        Workspace slug to index into (default: indexer-e2e)
--concurrency <n>    DATABOX_CONCURRENCY for the indexer (default: 1)
--timeout <seconds>  Deadline of a single indexation (default: 300)
--skip-build         Do not rebuild databox/indexer/dist first
--skip-idempotence   Index once instead of twice
--keep               Keep the workspace and the /e2e volume
```

## Design notes and known limits

- **The outer `timeout` runs `--foreground`, and the docker client gets its
  stdin from `/dev/null`.** GNU `timeout` otherwise puts its command in a
  process group of its own, which is not the foreground group of the terminal;
  `docker compose run` then touches the tty, the kernel stops it with SIGTTIN,
  and the run hangs without even answering SIGTERM. It only happens on a
  terminal, so a redirected run never shows it.
- **`--timeout` is enforced inside the container**, by a `timeout` whose child
  is node itself. Killing the `docker compose run` client from the host stops
  nothing — the container keeps running, and keeps writing to databox — so the
  outer deadline is only a backstop, and the container is named so the script
  can remove it whatever happens. The indexer turns the signal into a
  cooperative stop: the assets in flight finish, nothing more is pulled, and it
  exits 143.
- **Everything is read through the databox API**; the suite never connects to
  the database. For an admin token `GET /assets` and `GET /collections` are
  served from Elasticsearch, which is fed from the writes the indexer just
  made, so `databox.waitFor()` polls until the expected number of assets shows
  up before asserting. It also means the suite covers the indexation pipeline
  as well as the indexer: an asset that never reaches Elasticsearch fails here.
- **The API exposes neither the asset key nor the collection key**, so the
  expectations are expressed in the terms it does expose: a collection by its
  path of names, an asset by the `indexer://` alternate URL of its source file.
  `storage` and `path_public` are not exposed either and are not asserted.
- **The collection tree is walked level by level.** The listing nests only one
  level of children, so `databox.getCollectionTree()` follows the `parent`
  filter down.
- **The asset title is not asserted.** `Asset::$name` is not mapped in the API;
  it is written to `attribute` rows through the attribute definitions flagged
  `fill_from_name`, and a workspace created by `POST /workspaces` has none.
- **`ownerId` is a literal in the generated config**, resolved at step 1 from
  the `sub` claim of the access token. The indexation step therefore needs
  nothing but `CONFIG_FILE` and `--workdir /e2e`.
- **`DATABOX_API_URL` is overridden to `http://databox-api`.** Going through
  the internal network alias keeps the suite independent of the host
  networking — at the cost of not covering the Traefik/TLS path.
- **`dist/console.mjs` must be built.** The dev override bind-mounts the repo
  over the `databox-indexer` image, so the working tree is what actually runs;
  the launcher rebuilds it in that case. Without the override, the image ships
  its own build and nothing is rebuilt.
- **Source files are expected to be replaced between runs.** `handleSource()`
  in the databox API persists a new `File` on every `POST /assets` rather than
  looking one up by URL, so `asset.source` points at a fresh file after the
  second run. The suite reports how many changed instead of failing on them.
- **One blacklist assertion greps a log line.** `src/pathFilter.ts` logs
  `does not match blacklist` for a path that _does_ match — the wording is
  wrong. The grep follows the actual string; fixing the message means updating
  it here.
- **Workspace cleanup goes through `DELETE /workspaces/{id}`**, which only
  soft-deletes; the `SoftDeleteListener` dispatches a `DeleteWorkspace` message
  that empties the row for real — synchronously in `APP_ENV=dev`, through a
  consumer otherwise. On an async stack the consumer has to be running, or the
  next run trips on the unique index on `workspace.slug`, which does not filter
  `deleted_at`.
- **The suite is not re-entrant** for a given slug. Use `--slug` to run several
  at once; the `/e2e` volume is already named per process.
- **Scope**: `index` with the `fs` handler. `watch`, `index-all`, `list` and the
  `s3_amqp` / `phraseanet` handlers are not covered.

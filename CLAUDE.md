# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## ⚠️ Nothing is installed on the host — run everything through Docker

The host machine has **no PHP, no Composer, no Node/pnpm, no database, no Elasticsearch** installed. Do **not** run `php`, `composer`, `bin/console`, `phpunit`, `pnpm`, `vite`, etc. directly — they will fail or, worse, run against a non-existent environment.

Every command runs inside a Docker Compose service container. Use `dc` (see [Tooling](#tooling)) or `docker compose`:

```bash
dc run --rm databox-api-php su app -c "composer test"          # PHP / Symfony
dc run --rm databox-api-php su app -c "bin/console <cmd>"
dc run --rm dev pnpm install                        # JS / pnpm (via the `dev` container)
dc run --rm dev pnpm --filter databox-client build
```

The only things run from the host are the orchestration scripts in `bin/` (which themselves call `docker compose`) and git.

## What this is

Phrasea is an open-source Digital Asset Management (DAM) platform, built as a **monorepo of several independent applications** that share common PHP bundles and JS packages. Each application follows the same shape: a **Symfony API** (`*/api`) plus a **React client** (`*/client`).

The applications:

- **databox** — the core DAM (asset storage, collections, metadata, search, workflows). This is the main product; most work happens here.
- **expose** — public/shared asset publication.
- **uploader** — asset ingestion front-end.
- **dashboard** — client-only shell that ties the apps together (no API of its own).
- **configurator** — a standalone Symfony app that generates per-tenant configuration.
- **report** — reporting service.
- **databox/indexer** — a Node.js service (not a client) for external indexing.

## Repository layout

- `databox/`, `expose/`, `uploader/` — each has `api/` (Symfony) and `client/` (React/Vite).
- `dashboard/client/` — React client only.
- `lib/php/*` — shared Symfony bundles (e.g. `core-bundle`, `auth-bundle`, `configurator-bundle`, `storage-bundle`, `es-bundle`, `notify-bundle`, `report-bundle`, `workflow-bundle`, `rendition-factory`). Consumed by the API apps as Composer **`type: path` repositories, symlinked** — editing a bundle immediately affects the apps that depend on it.
- `lib/js/*` — shared React/TS packages published under the **`@alchemy/*`** scope (e.g. `@alchemy/core`, `@alchemy/auth`, `@alchemy/api`, `@alchemy/phrasea-ui`, `@alchemy/react-hooks`). Consumed via pnpm `workspace:*`.
- `bin/` — orchestration scripts (setup, build, migrate, test); `bin/dev/` — developer helpers.
- `infra/`, `docker-compose*.yml` — deployment and local stack.
- `doc/tech/` — the authoritative setup/dev docs (`01_setup.md`, `02_dev.md`, and per-app folders).

## Tooling

- **JS:** pnpm workspaces (`pnpm-workspace.yaml`) orchestrated by **Turbo** (`turbo.json`). Root scripts fan out across packages.
- **PHP:** each API is Symfony (PHP ^8.5) using **API Platform**, **Doctrine ORM**, **FOS Elastica / Elasticsearch**, and **Symfony Messenger over AMQP** for async work.
- **Everything runs in Docker Compose.** Nothing is installed on the host (see the warning at the top). PHP commands run inside the API containers (e.g. `databox-api-php`, `expose-api-php`, `uploader-api-php`); JS/pnpm commands run inside the `dev` container.

The docs recommend defining a `dc` shell function that wraps `docker compose` with the env files:

```bash
function dc() {
    if [ -f .env.local ]; then
        docker compose --env-file=.env --env-file=.env.local "$@"
    else
        docker compose "$@"
    fi
}
```

## Common commands

### First-time / stack setup

```bash
bin/build.sh              # build images in cache-optimal order
bin/setup.sh              # create databases + initial config
bin/migrate.sh            # run migrations against an already-deployed stack
bin/install-fixtures.sh   # WARNING: wipes DBs, loads fixtures, re-runs setup
dc up -d                  # start the whole stack
```

See `doc/tech/01_setup.md` and `doc/tech/02_dev.md` for the full dev bootstrap (mkcert, `/etc/hosts`, dev container).

### Frontend (pnpm/Turbo, run inside the `dev` container)

Prefix pnpm commands with `dc run --rm dev` (the clients normally run via `dc up`; use the `dev` container for one-off commands):

```bash
dc run --rm dev pnpm install
dc run --rm dev pnpm lint          # eslint across packages (Turbo)
dc run --rm dev pnpm lint:fix
dc run --rm dev pnpm format        # prettier
dc run --rm dev pnpm build         # tsc + vite build across packages
```

Per-client: `pnpm --filter databox-client <script>` (scripts: `lint`, `build`, `cs` = lint:fix + format).

**Frontend tests use Vitest** (only where present, e.g. `databox/client`):

```bash
dc run --rm dev pnpm --filter databox-client test                      # vitest run
dc run --rm dev pnpm --filter databox-client test -- path/to/file.test.ts   # single file
dc run --rm dev pnpm --filter databox-client test -- -t "test name"         # single test by name
```

### Backend (Symfony, run inside the container)

Each API defines Composer scripts. `composer test` runs var-dump-check + PHPStan + PHPUnit.

```bash
dc run --rm databox-api-php composer test       # full check
dc run --rm databox-api-php composer phpstan     # static analysis only
dc run --rm databox-api-php composer cs          # php-cs-fixer
dc run --rm databox-api-php composer phpunit     # resets test DB + elastica, then PHPUnit
```

**PHPUnit needs 1G of memory.** The `composer phpunit` scripts already pass
`-d memory_limit=1024M`; when calling `bin/phpunit` directly, pass it yourself —
the databox suite peaks above 512M and dies with an "Allowed memory size
exhausted" fatal partway through.

Single PHP test (PHPUnit filter):

```bash
dc run --rm -e APP_ENV=test databox-api-php php -d memory_limit=1024M bin/phpunit --filter SomeTest tests/Path/SomeTest.php
```

Symfony console: `dc run --rm databox-api-php bin/console <cmd>`.

### Whole-repo / CI test flow

- `bin/test.sh` — runs `composer test` for every Symfony API and every PHP lib inside containers.
- `bin/dev/run-tests-in-ci-conditions.sh` — reproduces CI: builds, brings up the stack, runs `bin/test.sh`, then Cypress (`cypress/`) end-to-end.
- `bin/php-cs.sh` — php-cs-fixer across all Symfony projects and PHP libs.

The canonical project lists (used by the whole-repo scripts) live in `bin/vars.sh`: `SYMFONY_PROJECTS`, `CLIENT_PROJECTS`, `PHP_LIBS`, `JS_LIBS`.

## Architecture notes

- **API pattern:** Doctrine entities in `src/Entity`, exposed as API Platform resources; async processing via Messenger consumers (`src/Consumer`) reading from RabbitMQ; search backed by Elasticsearch through `es-bundle`/FOS Elastica. Files and renditions go through `storage-bundle` and `rendition-factory`.
- **Shared code first:** cross-app concerns (auth, config, storage, notifications, reporting, workflow) live in `lib/php/*` bundles and `lib/js/*` packages rather than being duplicated per app. When a behavior spans multiple apps, the change usually belongs in a lib, not in one app.
- **Auth** is centralized (Keycloak-based; see `lib/php/auth-bundle`, `lib/js/auth`, and `doc/tech/Authentication/`).
- **databox client search** uses a custom AQL grammar built with nearley: `src/components/Media/Search/AQL/grammar.ne` compiled via `pnpm compile-grammar` (do not hand-edit the generated `grammar.ts`).

## Conventions

- Frontend: React 18 + TypeScript + Vite, MUI (`@mui/material`) for UI, TanStack React Query for data, i18next for translations (`pnpm translate` runs the i18next scanner).
- A **pre-commit hook** (Husky + lint-staged) runs formatting/CS on staged files; keep code lint-clean.
- New PHP shared bundles use the modern structure (`src/` + `config/` + an `AbstractBundle` class) rather than the legacy layout.

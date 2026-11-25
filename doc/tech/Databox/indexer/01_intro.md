---
title: 'Databox Indexer'
---

# Databox Indexer

The Databox Indexer (`databox/indexer`, Node.js) imports (indexes) content into Databox from
external **locations**: a directory, S3 buckets fed by AMQP events, or a Phraseanet instance. It
creates the workspace, the collections, the attribute and rendition definitions it needs, and the
assets, through the Databox API.

## Key features

- Index a location once (`index`), or every configured location in a row (`index-all`).
- Watch the `fs` and `s3_amqp` locations and apply their changes as they come (`watch`).
- List the configured locations (`list`).
- Configuration in a JSON file, with environment variables injected into it.

## Service and image

The `databox-indexer` Compose service belongs to the `indexer` profile, which is not in the
default `COMPOSE_PROFILES`: it is not started by `dc up`, and runs as one-off commands
(`dc run --rm databox-indexer …`; Compose enables the profile of a service it is asked to run).
Build its image with `dc --profile indexer build databox-indexer`.

The entrypoint of the image is the console of the indexer (`docker/entrypoint.sh`, which runs
`node dist/console.mjs`, already built in the image): the arguments of `dc run` are its command.

## Commands

```bash
# Index a location
dc run --rm databox-indexer index <location-name>

# Index every configured location, one after the other
dc run --rm databox-indexer index-all

# Watch the configured locations for changes
dc run --rm databox-indexer watch

# List the configured locations (with their resolved configuration)
dc run --rm databox-indexer list
```

Options of `index` and `index-all`:

| Option | Effect |
| --- | --- |
| `-n`, `--create-new-workspace` | Flush the target workspace if it exists (`fs` and `phraseanet` locations), so that the indexation starts from an empty workspace. A `fs` location can also set `createNewWorkspace: true` in its options. |
| `--debug` | Debug log level. |
| `--no-server` | Do not start the asset HTTP server (see below): the process exits once the indexation is done. |

`watch` also takes `--debug`. Its `-l`, `--location` option is declared but has no effect:
`watch` always watches every configured location (the `phraseanet` locations have no watcher).

An indexation is idempotent: assets and collections are looked up by key, so running it again
updates rather than duplicates. `SIGINT` or `SIGTERM` stops it cooperatively: the assets in
flight finish, no new one is started, and the command exits with code 130 or 143; a second signal
exits at once.

## Asset HTTP server

For the `fs` and `s3_amqp` locations, Databox does not receive the files: the source file of
each asset is a URL of the HTTP server of the indexer,
`PUBLIC_URL/assets?source=<location>&path=<path>`, kept as such (a file stored as a URL, not
copied into the Databox storage), from which Databox reads the file whenever it needs it
(renditions, downloads). The server serves the file of a `fs` location, and redirects to a
signed URL of the bucket for a `s3_amqp` location (only for the buckets of the location).

- `PUBLIC_URL` is set by Compose from `DATABOX_INDEXER_URL`
  (`https://databox-indexer.<PHRASEA_DOMAIN>`, routed by Traefik to the container); it must be
  reachable by the Databox workers.
- The server listens on `SERVER_PORT` (`3000` in the image).
- `index` and `index-all` start it and keep running once the indexation is done, so that Databox
  can still fetch the files; `watch` always starts it. The files of such locations stay
  readable only while a server of the indexer runs. Pass `--no-server` when Databox does not
  need it: a `phraseanet` location, whose files are the Phraseanet permalinks.

## Development

`docker-compose.dev.yml` bind-mounts the repository over the image and resets the entrypoint
(`entrypoint: ''`): commands run as is, from `databox/indexer`, with the dependencies installed
in the repository (`dc run --rm dev pnpm install`).

```bash
# Build dist/ from the sources, then run the console
dc run --rm databox-indexer pnpm build
dc run --rm databox-indexer pnpm console index <location-name>

# Or build and run in one go (nodemon: rebuilds and restarts on every change of src/)
dc run --rm databox-indexer pnpm dev index <location-name>
```

## Configuration

The locations are described in a JSON file of `databox/indexer/config/` (mounted in the
container), `config.json` by default: set `INDEXER_CONFIG_FILE` in `.env.local` to use another
file of this directory (the container reads it from `CONFIG_FILE`).

Any string of the file can reference environment variables: `"%env(NAME)%"`, cast with
`%env(bool:NAME)%` or `%env(int:NAME)%`. Compose passes the `INDEXER_*` variables of `.env`
under the names the default `config.json` uses (`INDEXER_DATABOX_CLIENT_ID` →
`DATABOX_CLIENT_ID`…).

```json
{
    "databox": {
        "url": "%env(DATABOX_API_URL)%",
        "clientId": "%env(DATABOX_CLIENT_ID)%",
        "clientSecret": "%env(DATABOX_CLIENT_SECRET)%",
        "ownerId": "%env(DATABOX_OWNER_ID)%",
        "verifySSL": "%env(bool:DATABOX_VERIFY_SSL)%",
        "concurrency": "%env(int:DATABOX_CONCURRENCY)%"
    },
    "blacklist": ["(^|/)\\..+$"],
    "alternateUrls": [],
    "locations": []
}
```

### `databox`

| Key | Variable (`.env`) | Description |
| --- | --- | --- |
| `url` | `DATABOX_API_URL` | URL of the Databox API. |
| `clientId`, `clientSecret` | `INDEXER_DATABOX_CLIENT_ID`, `INDEXER_DATABOX_CLIENT_SECRET` | OAuth client of the indexer, authenticated with the client credentials grant (scope `admin`). |
| `ownerId` | `INDEXER_DATABOX_OWNER_ID` | ID of the user who owns the workspaces, collections and assets created by the indexer. Required: empty by default in `.env`. |
| `verifySSL` | `VERIFY_SSL` | Verify the TLS certificate of the API (default `true`). |
| `concurrency` | `INDEXER_DATABOX_CONCURRENCY` | Number of assets sent to Databox in parallel (default `1` when unset, `3` in `.env`). The same variable sets the AMQP prefetch of `watch`. |

### `whitelist` and `blacklist`

Lists of regular expressions matched against the path of each asset, for every location
(`index` and `watch`): an asset is skipped when a `whitelist` is set and no expression of it
matches, or when an expression of `blacklist` matches. The default configuration blacklists the
hidden files and directories (`(^|/)\..+$`).

### `alternateUrls`

Alternate URLs added to the source file of each asset, at the root of the file or in a location
(which then replaces the root list): `name` is the type of the URL, `pathPattern` its value, where
`${path}` and `${sourcePath}` are replaced by the path of the asset and its source path (the
`sourceDir` of a `fs` location):

```json
"alternateUrls": [
    {"name": "indexer", "pathPattern": "indexer://${sourcePath}"}
]
```

### Locations

Each location has a `name` (the argument of `index`), a `type` and `options`:

- `fs`: local filesystem source ([details](./02_fs_s3.md));
- `s3_amqp`: S3 buckets, and their events consumed through AMQP ([details](./02_fs_s3.md));
- `phraseanet`: a Phraseanet instance ([details](./conf_phraseanet.md)).

## Resuming a Phraseanet indexation

A `phraseanet` location records its progress in a lock file,
`<INDEXER_LOCK_DIR>/<location>_<Phraseanet databox name>_<workspace slug>.lock` (`config/` of the
indexer when `INDEXER_LOCK_DIR` is unset; `databox/indexer/config`, mounted from the host, in
`.env`). It holds the ID of the last story and of the last record imported, and the next run
only searches the records after them (in the `searchOrder` of the location). The file is kept at
the end of a run: delete it to index the whole databox again.

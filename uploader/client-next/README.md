# Uploader client (Next.js)

New generation of the Uploader web client, built like the Databox client:
Next.js (App Router), React 19, TypeScript, Tailwind CSS v4, TanStack Query,
Zustand and Radix primitives. It implements the features listed in
[`../uploader-client-next.md`](../uploader-client-next.md) and runs next to
the former client (`uploader/client`) until it replaces it.

## Development

Everything runs through Docker (nothing is installed on the host):

```bash
# install deps (from the repository root)
dc run --rm dev pnpm install --filter uploader-client-next

# start the dev server (profile `uploader-next`)
dc --profile uploader-next up -d uploader-client-next
# => https://uploader-next.${PHRASEA_DOMAIN}

# checks
dc run --rm dev pnpm --filter uploader-client-next typecheck
dc run --rm dev pnpm --filter uploader-client-next lint
dc run --rm dev pnpm --filter uploader-client-next test
```

`uploader-next.${PHRASEA_DOMAIN}` must resolve to the stack (`/etc/hosts`).
Its Keycloak public client (`UPLOADER_NEXT_CLIENT_ID`, redirect URIs on
`UPLOADER_NEXT_CLIENT_URL`) is provisioned by the configurator: run
`bin/setup.sh` (or the configurator) once after adding the profile.

## Runtime configuration

The same image runs against any stack: the configuration is read from the
container environment and the stack configuration at request time (see
`src/lib/config/server.ts`), never baked at build time.

| Source | Description |
|---|---|
| `UPLOADER_API_URL` | Uploader API base URL |
| `UPLOADER_CLIENT_URL` | Public URL of this client (`UPLOADER_NEXT_CLIENT_URL` in compose) |
| `KEYCLOAK_URL`, `KEYCLOAK_REALM_NAME`, `CLIENT_ID` | OpenID Connect settings |
| `AUTO_CONNECT_IDP` | Optional IdP hint |
| `S3_MAX_OBJECT_SIZE`, `ALLOWED_FILE_TYPES` | Upload limits (see below) |
| `DASHBOARD_CLIENT_URL`, `DISPLAY_SERVICES_MENU` | Services menu |
| `MATOMO_URL`, `MATOMO_SITE_ID`, `MATOMO_DISABLE_COOKIES` | Page views sent to Matomo |
| `DEV_MODE` | "Debug user" entry of the user menu |
| `CONFIGURATOR_S3_*`, `CONFIG_IS_PUBLIC`, `STACK_CONFIG_SRC`, `STACK_CONFIG_REFRESH_INTERVAL` | Stack configuration, read like the Databox client does |
| stack `uploader.max_upload_file_size` | Maximum size of a file (falls back to `S3_MAX_OBJECT_SIZE`) |
| stack `uploader.max_upload_commit_size` | Maximum size of a batch |
| stack `uploader.max_upload_file_count` | Maximum number of files of a batch (`1`: single file mode) |
| stack `uploader.client.logo` (`src`, `margin`) | Logo of the top bar, else the global `logo` |
| stack `databox.theme` | Organisation theme, defined in Databox and shared |

The batch limits are also enforced by the API on commit.

## Layout

```
src/
  app/            Next.js routes (App Router); the private ones under app/(app)
  components/     Layout (the design system is `@alchemy/next-ui`, shared)
  features/
    targets/      Target list
    upload/       Upload wizard, and its engine (engine/: queue, resume, transport)
    download/     Upload by URL
    liform/       LiForm schema compiler and renderer
    admin/        Form schema and target data editors
  lib/            Config, auth (OIDC/PKCE), API client, navigation guard
  i18n/           Languages and translations of the uploader (en, fr, de, es)
  types/          API contracts
```

## Upload engine

Files go straight to the storage (S3 multipart, `@alchemy/api`
`multipartUpload`): the API presigns every part, three parts of a file are
PUT at a time, two files at a time.

- An expired part URL is renewed from the first missing part; the renewal is
  retried with a back-off (warning toast while it is).
- The parts sent are remembered per user in `localStorage`
  (`upl.uploads.<username>`): dropping the same file again — after a
  reload, in another tab — resumes it from the first missing part.
- A failing file is retried five times, unless the API refused it (4xx);
  then it is left aside with its error, **without blocking the batch**: it
  can be retried or removed, the others keep going.
- A file cut by a lost connection waits for the browser to get back online.
- The batch is committed once every file is uploaded and the form is valid;
  the "done" screen only shows once the API accepted the commit, an error
  is shown otherwise (retry, or edit the form).

## Differences with the former client

The defects listed in §13 of the feature inventory are fixed:

- the commit is awaited and its errors are shown (1);
- the `{errors}` answered by `POST /form/validate` and `POST /downloads`
  are shown on the form (2);
- a failed file never blocks the batch: retry or remove it (3);
- resuming reads the stored state back, across reloads (4);
- the queue owns its listeners and the `online` listener is removed with
  the screen (5);
- rejected files (size, type) are reported (6, 7);
- each file shows its name, size and progress, the done screen lists what
  was sent (8);
- every widget is rendered, including `date`, `compatible-date(time)`,
  `choice-expanded` and the fields of a `oneOf` option (9);
- numeric widgets (`money`, `percent` included) send numbers (10);
- the schema is compiled into a new object, never mutated (11);
- the target data editor has its own wording, "All locales" is translated (13);
- the JSON editors guard unsaved changes (14);
- the locale mode of a schema is editable (15);
- every page of the target list is loaded (16) — the API answers a single
  page of the targets the user may read (filtered after pagination,
  `TargetDataProvider`), so this only matters once it paginates;
- dead code and unused configuration keys are gone (17).

Left out on purpose:

- **notifications**: the Uploader API exposes no in-app notification
  endpoint (see `uploader/api/config/routes/alchemy_notifier.yaml`), so
  there is no bell nor realtime connection;
- **multi-step forms** (12): `doc/tech/Uploader/form-steps.md` describes a
  `POST /schema` endpoint the API does not implement;
- **Sentry**: not wired, like in the Databox client.

# Uploader client

Uploader web client, built like the Databox client: Next.js (App Router),
React 19, TypeScript, Tailwind CSS v4, TanStack Query, Zustand and Radix
primitives.

## Development

Everything runs through Docker (nothing is installed on the host):

```bash
# install deps (from the repository root)
dc run --rm dev pnpm install --filter uploader-client

# start the dev server (profile `uploader`)
dc up -d uploader-client
# => https://uploader.${PHRASEA_DOMAIN}

# checks
dc run --rm dev pnpm --filter uploader-client typecheck
dc run --rm dev pnpm --filter uploader-client lint
dc run --rm dev pnpm --filter uploader-client test
```

The Keycloak client (`UPLOADER_CLIENT_ID`, redirect URIs on
`UPLOADER_CLIENT_URL`) is provisioned by the configurator.

## Runtime configuration

The same image runs against any stack: the configuration is read from the
container environment and the stack configuration at request time (see
`src/lib/config/server.ts`), never baked at build time.

| Source | Description |
|---|---|
| `UPLOADER_API_URL` | Uploader API base URL |
| `UPLOADER_CLIENT_URL` | Public URL of this client |
| `KEYCLOAK_URL`, `KEYCLOAK_REALM_NAME`, `CLIENT_ID` | OpenID Connect settings |
| `AUTO_CONNECT_IDP` | Optional IdP hint |
| `S3_MAX_OBJECT_SIZE`, `ALLOWED_FILE_TYPES` | Upload limits (see below) |
| `DASHBOARD_CLIENT_URL`, `DISPLAY_SERVICES_MENU` | Services menu |
| `MATOMO_URL`, `MATOMO_SITE_ID`, `MATOMO_DISABLE_COOKIES` | Page views sent to Matomo |
| `SENTRY_DSN`, `SENTRY_ENVIRONMENT`, `SENTRY_TUNNEL_RATE_LIMIT_PER_IP`, `SENTRY_TUNNEL_RATE_LIMIT_GLOBAL` | Browser errors reported to Sentry (see below) |
| `DEV_MODE` | "Debug user" entry of the user menu |
| `CONFIGURATOR_S3_*`, `CONFIG_IS_PUBLIC`, `STACK_CONFIG_SRC`, `STACK_CONFIG_REFRESH_INTERVAL` | Stack configuration, read like the Databox client does |
| stack `uploader.max_upload_file_size` | Maximum size of a file (falls back to `S3_MAX_OBJECT_SIZE`) |
| stack `uploader.max_upload_commit_size` | Maximum size of a batch |
| stack `uploader.max_upload_file_count` | Maximum number of files of a batch (`1`: single file mode) |
| stack `uploader.client.logo` (`src`, `margin`) | Logo of the top bar, else the global `logo` |
| stack `databox.theme` | Organisation theme, defined in Databox and shared |

The batch limits are also enforced by the API on commit.

## Error monitoring (Sentry)

When `SENTRY_DSN` is set (`CLIENT_SENTRY_DSN` of the stack), the browser
errors are reported to Sentry by the browser SDK (`@sentry/react`, errors
only: neither tracing nor replay), tagged with `app.id` / `app.name` and the
signed-in user. The SDK posts its envelopes to the same origin route
`/api/relay` (no ad blocker, no CORS), which relays them to the project of
`SENTRY_DSN` only, behind an in-memory rate limit of the client process:
`SENTRY_TUNNEL_RATE_LIMIT_PER_IP` envelopes per minute and per IP (30) and
`SENTRY_TUNNEL_RATE_LIMIT_GLOBAL` per minute for all IPs (300). The shared
code lives in `@alchemy/next-core` (`monitoring/*`, `server/sentryTunnel*`).

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

## Not implemented

- **notifications**: the Uploader API exposes no in-app notification
  endpoint (see `uploader/api/config/routes/alchemy_notifier.yaml`), so
  there is no bell nor realtime connection;
- **multi-step forms**: `doc/tech/Uploader/form-steps.md` describes a
  `POST /schema` endpoint the API does not implement.

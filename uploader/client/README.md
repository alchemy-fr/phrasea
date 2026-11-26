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
| `ALLOW_INDEXING` | Whether the search engines may index the client, in `robots.txt` and the robots meta tag (default `false`, `UPLOADER_CLIENT_ALLOW_INDEXING` in `.env`) |
| `KEYCLOAK_URL`, `KEYCLOAK_REALM_NAME`, `CLIENT_ID` | OpenID Connect settings |
| `AUTO_CONNECT_IDP` | Optional IdP hint |
| `S3_MAX_OBJECT_SIZE`, `ALLOWED_FILE_TYPES` | Upload limits (see below) |
| `DASHBOARD_CLIENT_URL`, `DISPLAY_SERVICES_MENU` | Services menu |
| `MATOMO_URL`, `MATOMO_SITE_ID`, `MATOMO_DISABLE_COOKIES`, `MATOMO_COOKIE_CONSENT` | Page views sent to Matomo |
| `SENTRY_DSN`, `SENTRY_ENVIRONMENT`, `SENTRY_TUNNEL_RATE_LIMIT_PER_IP`, `SENTRY_TUNNEL_RATE_LIMIT_GLOBAL` | Browser errors reported to Sentry (see below) |
| `CSP_REPORT_ONLY`, `CSP_EXTRA_SOURCES` | Content Security Policy (see below) |
| `DEV_MODE` | "Debug user" entry of the user menu |
| `APP_ID` | Identifier of the application in the Sentry events (`app.id`, default `uploader`) |
| `NEXT_ALLOWED_DEV_ORIGINS` | `next dev` only: hosts allowed to reach the dev server besides the host of `UPLOADER_CLIENT_URL`, comma separated (`next.config.ts`) |
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
code lives in `@alchemy/core` (`monitoring/*`, `server/sentryTunnel*`).

## Content Security Policy

`src/proxy.ts` sets the Content Security Policy of the pages at request time,
with a new nonce for every response (`@alchemy/core/security/*`): a
script only runs if it bears the nonce or was loaded by one that does
(`'strict-dynamic'`). Next puts it on its own scripts; the inline scripts of
the layout (theme pre-paint) read it from the `x-nonce` request
header. The other sources come from the environment
(`src/lib/security/csp.ts`): the API, the dashboard (services menu), Keycloak, the buckets
(`CONFIGURATOR_S3_ENDPOINT`, `CLOUD_FRONT_URL`), Matomo and
`CSP_EXTRA_SOURCES` (space separated, for any other host serving files to the
pages). Styles stay allowed inline.

The policy is only reported (`Content-Security-Policy-Report-Only`) unless
`CSP_REPORT_ONLY` is false. The violations show in the browser
console and, when `SENTRY_DSN` is set, are relayed to its project by the same
origin route `/api/relay/csp` (same rate limits as `/api/relay`; the DSN
never reaches the browser). `/silent-check-sso.html` has no policy: a static
page whose inline script cannot bear the nonce.

## Layout

```
src/
  app/            Next.js routes (App Router); the private ones under app/(app)
  components/
    layout/       Application shell and top bar (the design system is `@alchemy/ui`, shared)
    form/code/    Code editor of the JSON fields (target data)
  features/
    targets/      Target list
    upload/       Upload wizard, and its engine (engine/: queue, resume, transport)
    download/     Upload by URL
    upload-link/  Page of an upload link (/l/[token]), without account
    form-builder/ Forms of the targets: definition, editor (editor/) and renderer (render/)
    admin/        Target editors (properties, form, data) and upload links
    user/         Current user (`GET /me`) and the "Debug user" dialog
  lib/            Config, auth (OIDC/PKCE), API client, analytics, monitoring, privacy, security, SEO
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

## Routes

| Route | Page |
|---|---|
| `/` | Targets the user may upload to; opens the target directly when there is only one |
| `/upload/[id]` | Upload wizard of a target: files, information (form), finalization, done |
| `/download/[id]` | Import of a file from a URL into a target (`POST /downloads`) |
| `/admin/targets` | Targets: list and creation (admins), with their form and data |
| `/admin/targets/[id]` | Target editor: Properties, Form and Data tabs, each behind its permission |
| `/admin/upload-links` | Upload links of the user (every link for an admin) |
| `/l/[token]` | Page of an upload link: public, no account; neither indexed nor sent as a referrer |

`/admin/forms[/id]` and `/admin/target-data[/id]`, the former form and target
data editors, redirect permanently to `/admin/targets[/id]`
(`next.config.ts`).

The API, the flow of an upload and the configuration are documented in
[doc/tech/Uploader](../../doc/tech/Uploader/01_intro.md).

## Not implemented

- **notifications**: the Uploader API exposes no in-app notification
  endpoint (see `uploader/api/config/routes/alchemy_notifier.yaml`), so
  there is no bell nor realtime connection. The notifications asked with
  "Notify me when done" reach the user by email; their in-app copy is only
  recorded by the API (admin > Notifications).

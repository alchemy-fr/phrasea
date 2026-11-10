# Dashboard client

Dashboard, the entry point of a Phrasea stack: it lists
the applications deployed (Databox, Expose, Uploader), the Keycloak console
and, for the technical profiles, the operation tools. It is also the services
menu of the other clients, which frame it. Built like `databox/client`:
Next.js (App Router), React 19, TypeScript, Tailwind CSS v4, Radix
primitives, TanStack Query, Zustand.

## Development

Everything runs through Docker (nothing is installed on the host):

```bash
# install deps (from the repository root)
dc run --rm dev pnpm install --filter dashboard-client

# start the dev server (profile `dashboard`)
dc up -d dashboard-client
# => https://dashboard.${PHRASEA_DOMAIN}

# checks
dc run --rm dev pnpm --filter dashboard-client typecheck
dc run --rm dev pnpm --filter dashboard-client lint
dc run --rm dev pnpm --filter dashboard-client test
```

The Keycloak client (`DASHBOARD_CLIENT_ID`, redirect URIs on
`DASHBOARD_CLIENT_URL`) is provisioned by the configurator.

## Runtime configuration

The same image runs against any stack: the configuration is read from the
container environment and the stack configuration at request time
(`src/lib/config/server.ts`), never baked at build time. It is served to any
visitor with the page.

| Source | Description |
|---|---|
| `DASHBOARD_CLIENT_URL` | Public URL of this client |
| `KEYCLOAK_URL`, `KEYCLOAK_REALM_NAME`, `CLIENT_ID` | OpenID Connect settings, URLs of the Keycloak consoles |
| `AUTO_CONNECT_IDP` | Optional IdP hint |
| `DATABOX_API_URL`, `EXPOSE_API_URL`, `UPLOADER_API_URL` | An application is deployed when its API URL is set; its admin is allowed to frame the dashboard |
| `DATABOX_CLIENT_URL`, `EXPOSE_CLIENT_URL`, `UPLOADER_CLIENT_URL` | Link of the card (none when empty); allowed to frame the dashboard |
| `ALLOWED_FRAME_ANCESTORS` | Other sites allowed to frame the dashboard (space separated), set by `src/proxy.ts` |
| `PGADMIN_URL`, `PHPMYADMIN_URL`, `MAILPIT_URL`, `MATOMO_URL`, `RABBITMQ_CONSOLE_URL`, `TRAEFIK_CONSOLE_URL`, `SOCKUDO_USAGE_URL` | Technical tools (role `tech`) |
| `STACK_NAME`, `STACK_VERSION` | Title and version of the stack (compose project name and `DOCKER_TAG`) |
| `DEV_MODE` | "Developer mode" banner |
| `MATOMO_URL`, `MATOMO_SITE_ID`, `MATOMO_DISABLE_COOKIES` | Page views sent to Matomo |
| `SENTRY_DSN`, `SENTRY_ENVIRONMENT`, `SENTRY_TUNNEL_RATE_LIMIT_PER_IP`, `SENTRY_TUNNEL_RATE_LIMIT_GLOBAL` | Browser errors reported to Sentry (see below) |
| `VERIFY_SSL` | Certificate check of the API probes and of the stack configuration |
| `STATUS_TTL` | Seconds an API status is kept before probing again (15) |
| `GIT_LOG_SRC` | Commit history file (`build-info/git-log.tsv`) |
| `CONFIGURATOR_S3_*`, `CONFIG_IS_PUBLIC`, `STACK_CONFIG_SRC`, `STACK_CONFIG_REFRESH_INTERVAL` | Stack configuration (logo, organisation theme), as for `databox/client` |

The organisation theme is the one administrators define in the Databox
client (`databox.theme` entry of the stack configuration): it is offered in
the theme menu next to the built-in presets.

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

## Routes

| Route | Screen | Access |
|---|---|---|
| `/` | Dashboard | public |
| `/git-log` | Last 30 commits of the build (`/git-log.html` redirects to it) | public |
| `/auth/callback` | OpenID Connect callback | public |
| `/auth/window` | Sign-in window opened from the services menu | public |
| `/api/status` | Status of each API, polled by the cards | public |
| `/api/theme-fonts` | Fonts of the organisation theme | public |

## Behaviour

- **Cards**, in order: *Identity Manager* (roles `group-admin`,
  `user-admin`, or `admin` — the latter reaches the `master` console), then
  every application whose API URL is set, then, in the services menu only,
  the *Dashboard* itself. The *Admin* action follows the rule of the APIs
  (`RoleMapper` of auth-bundle: `admin` or `<app>-admin`); *API
  documentation* is offered to signed-in users only.
- **Status**: the server probes the `/_health/check` route of each API
  (shared by every visitor, refreshed every 15 seconds), the page polls it
  every minute. A card shows *Operational*, *Degraded* (a check of the API
  fails) or *Unavailable* (no answer, or the proxy answering for it). An API
  refusing an anonymous health check counts as operational: it answered.
  In compose, the API hosts resolve to traefik (`extra_hosts`).
- **Links** open in the current tab — in the tab of the client when the
  dashboard is framed (`target="_top"`); a modified click opens a new one.
- **Services menu**: framed, the dashboard drops its top bar (decided by the
  frame, not by the width, before the first paint) and shows who is signed
  in instead. The session is picked up silently from Keycloak; otherwise
  *Sign in* opens a window (Keycloak cannot be framed) that hands the tokens
  back. Signing out is left to the client framing the menu.
- **Commit history**: `bin/git-log.sh` writes `build-info/git-log.tsv`
  (overwritten, never appended) before the image is built — by
  `bin/build.sh` and the CI. Without it the page says so.
- **Framing**: only the clients of the stack, the admins of its APIs (the
  services menu of `lib/php/admin-bundle` frames `DASHBOARD_CLIENT_URL`) and
  `ALLOWED_FRAME_ANCESTORS` may frame the dashboard.

## Not implemented

- **ACL-based admin links**: the APIs expose no "can I open the admin"
  endpoint; the role rule is the one their `access_control` applies;
- **Status of Keycloak and of the tools**: they have no health route
  reachable from the client.

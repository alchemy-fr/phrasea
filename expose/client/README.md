# Expose client

Web client of Expose, the public showcase of Phrasea: it displays the
publications (sets of assets pushed from Databox) and lets their editors
manage them. Built like `databox/client`: Next.js (App Router), React 19,
TypeScript, Tailwind CSS v4, Radix primitives, TanStack Query, Zustand.

## Development

Everything runs through Docker (nothing is installed on the host):

```bash
# install deps (from the repository root)
dc run --rm dev pnpm install --filter expose-client

# start the dev server (profile `expose`)
dc up -d expose-client
# => https://expose.${PHRASEA_DOMAIN}

# checks
dc run --rm dev pnpm --filter expose-client typecheck
dc run --rm dev pnpm --filter expose-client lint
dc run --rm dev pnpm --filter expose-client test
```

The Keycloak client (`EXPOSE_CLIENT_ID`, redirect URIs on `EXPOSE_CLIENT_URL`)
is provisioned by the configurator.

## Runtime configuration

The same image runs against any stack: the configuration is read from the
container environment at request time (`src/lib/config/server.ts`), never
baked at build time.

| Variable | Description |
|---|---|
| `EXPOSE_API_URL` | Expose API base URL |
| `EXPOSE_CLIENT_URL` | Public URL of this client |
| `ALLOW_INDEXING` | Whether the search engines may index the client, in `robots.txt` and the robots meta tag (default `true`, `EXPOSE_CLIENT_ALLOW_INDEXING` in `.env`); the administration, the embedded players, the sign-in and the share links never are |
| `KEYCLOAK_URL`, `KEYCLOAK_REALM_NAME`, `CLIENT_ID` | OpenID Connect settings |
| `AUTO_CONNECT_IDP` | Optional IdP hint |
| `DISABLE_INDEX_PAGE` | No public list of publications at `/` (nor link to it) |
| `ALLOWED_FRAME_ANCESTORS` | Sites allowed to embed the pages (CSP `frame-ancestors`, space separated), set by `src/proxy.ts` |
| `DASHBOARD_CLIENT_URL`, `DISPLAY_SERVICES_MENU` | Services menu |
| `MATOMO_URL`, `MATOMO_SITE_ID`, `MATOMO_MEDIA_PLUGIN_ENABLED`, `MATOMO_DISABLE_COOKIES`, `MATOMO_COOKIE_CONSENT` | Matomo analytics |
| `SENTRY_DSN`, `SENTRY_ENVIRONMENT`, `SENTRY_TUNNEL_RATE_LIMIT_PER_IP`, `SENTRY_TUNNEL_RATE_LIMIT_GLOBAL` | Browser errors reported to Sentry (see below) |
| `CSP_REPORT_ONLY`, `CSP_EXTRA_SOURCES` | Content Security Policy (see below) |
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

## Content Security Policy

`src/proxy.ts` sets the Content Security Policy of the pages at request time,
with a new nonce for every response (`@alchemy/next-core/security/*`): a
script only runs if it bears the nonce or was loaded by one that does
(`'strict-dynamic'`). Next puts it on its own scripts; the inline scripts of
the layout (theme pre-paint) read it from the `x-nonce` request
header. The other sources come from the environment
(`src/lib/security/csp.ts`): the API, the dashboard (services menu), Keycloak, the buckets
(`CONFIGURATOR_S3_ENDPOINT`, `CLOUD_FRONT_URL`), Matomo and
`CSP_EXTRA_SOURCES` (space separated, for any other host serving files to the
pages). Styles stay allowed inline.

The policy is only reported (`Content-Security-Policy-Report-Only`) unless
`CSP_REPORT_ONLY` is false: `frame-src` and `frame-ancestors` (`ALLOWED_FRAME_ANCESTORS`) stay enforced meanwhile, along with a `script-src` allowing any source that carries the nonce for Next: inline scripts and event handlers without it are blocked. The violations show in the browser
console and, when `SENTRY_DSN` is set, are relayed to its project by the same
origin route `/api/relay/csp` (same rate limits as `/api/relay`; the DSN
never reaches the browser). `/silent-check-sso.html` has no policy: a static
page whose inline script cannot bear the nonce.

## Routes

| Route | Screen | Access |
|---|---|---|
| `/` | Publications listed publicly (404 with `DISABLE_INDEX_PAGE`) | public |
| `/:publication` | Publication, by slug or id | public |
| `/:publication/:asset` | Publication with an asset opened (id or slug of the asset) | public |
| `/s/:token` | Permalink of a share link: keeps its token, then redirects to the publication | public |
| `/embed/:asset` | Player of an asset alone, for iframes | public |
| `/publications` | Publications the user can edit (sub-publications included) | signed in |
| `/publications/:id/edit` | Edition of a publication | signed in |
| `/profiles`, `/profiles/create`, `/profiles/:id/edit` | Publication profiles | signed in |
| `/auth/callback` | OpenID Connect callback | public |
| `/auth/window` | Sign-in window opened by an embedded page | public |
| `/robots.txt`, `/manifest.webmanifest` | Robots rules (`ALLOW_INDEXING`), web app manifest | public |
| `/api/og/publication/:publication[/asset/:asset]` | Image of the link previews | public |
| `/api/relay`, `/api/relay/csp` | Sentry relays (see above) | public |
| `/api/theme-fonts` | Fonts of the organisation theme | public |

`auth`, `embed`, `profiles`, `publications`, `api` and `s` are therefore not
usable as publication slugs (`src/lib/routes.ts`).

The publication is rendered by the layout of its segment
(`app/[publication]/layout.tsx`): moving from one asset to another only
changes the URL, the grid and the media loaded stay in place.

**Link previews** (Open Graph, Twitter): the pages of a publication, of one of
its assets and of a share link (`/s/:token`) carry its title, description and
image, read anonymously from the API by the server
(`src/lib/seo/publicationMetadata.ts`); the image is served under a stable URL,
`/api/og/publication/:publication[/asset/:asset]`, the API giving URLs signed
for a while only. A publication that is not readable anonymously (nor publicly
listed) gets the metadata of the app.

## Behaviour worth knowing

- **Access**: a publication protected by a password asks for it once per
  security container (the publication of the tree defining the method); the
  passwords are kept in the `passwds` cookie and sent in `X-Passwords`. A
  publication requiring authentication sends the visitor to Keycloak — unless
  they just signed out, or the page is embedded: Keycloak cannot be framed,
  the sign-in then happens in a separate window which hands the tokens back
  by message (the storage of an embedded page is partitioned). A signed-in
  user who is not allowed gets a "not allowed" screen, not a sign-in loop.
- **Terms**: until the terms of use are accepted, the content of the
  publication is not rendered at all (no media is loaded behind the dialog).
  The acceptance lives in a cookie; nothing is checked server side.
- **Downloads**: download terms first (once), then a link by email or the
  file itself. Nothing is offered when downloads are disabled. The API
  enforces these settings: with download terms, the file is downloaded through
  a short-lived URL that `POST /publications/{id}/download-url` gives in
  exchange of `acceptTerms: true` (also required by the email requests).
- **Layouts**: the layout of the publication applies whatever the number of
  assets; `mapbox` is displayed as a grid. The grid shows up at once and
  settles as the thumbnails load, the assets being added as the user scrolls.
- **HTML**: descriptions and terms are sanitized (DOMPurify) before being
  rendered.
- **Logs**: one publication view per visit once its content is shown, one
  asset view per asset displayed for a second (viewer, gallery, embed).
- **Administration**: the Publications / Profiles entries are shown to the
  Expose administrators and to the users the API lists something for (ACL
  rights). Every inheritable setting of a publication linked to a profile
  shows the value of the profile until overridden; "Public" overrides a
  protected profile (`securityMethod: ''`). The API answers with fresh data
  after any change: the lists are invalidated, not kept for 5 minutes.

## Not implemented

- Real-time notifications: the Expose API has no in-app notification
  endpoint.
- Edition of the date of a publication: the API does not accept it.
- Map layout, edition of the description, translations, CSS, theme, logo
  and assets of a publication (EasyAdmin of the API).

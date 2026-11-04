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
| `KEYCLOAK_URL`, `KEYCLOAK_REALM_NAME`, `CLIENT_ID` | OpenID Connect settings |
| `AUTO_CONNECT_IDP` | Optional IdP hint |
| `DISABLE_INDEX_PAGE` | No public list of publications at `/` (nor link to it) |
| `ALLOWED_FRAME_ANCESTORS` | Sites allowed to embed the pages (CSP `frame-ancestors`, space separated), set by `src/proxy.ts` |
| `DASHBOARD_CLIENT_URL`, `DISPLAY_SERVICES_MENU` | Services menu |
| `MATOMO_URL`, `MATOMO_SITE_ID`, `MATOMO_MEDIA_PLUGIN_ENABLED`, `MATOMO_DISABLE_COOKIES` | Matomo analytics |
| `SENTRY_DSN`, `SENTRY_ENVIRONMENT` | Monitoring (exposed, no SDK wired yet) |
| `CONFIGURATOR_S3_*`, `CONFIG_IS_PUBLIC`, `STACK_CONFIG_SRC`, `STACK_CONFIG_REFRESH_INTERVAL` | Stack configuration (logo, organisation theme), as for `databox/client` |

The organisation theme is the one administrators define in the Databox
client (`databox.theme` entry of the stack configuration): it is offered in
the theme menu next to the built-in presets.

## Routes

| Route | Screen | Access |
|---|---|---|
| `/` | Publications listed publicly (404 with `DISABLE_INDEX_PAGE`) | public |
| `/:publication` | Publication, by slug or id | public |
| `/:publication/:asset` | Publication with an asset opened (id or slug of the asset) | public |
| `/embed/:asset` | Player of an asset alone, for iframes | public |
| `/publications` | Publications the user can edit (sub-publications included) | signed in |
| `/publications/:id/edit` | Edition of a publication | signed in |
| `/profiles`, `/profiles/create`, `/profiles/:id/edit` | Publication profiles | signed in |
| `/auth/callback` | OpenID Connect callback | public |
| `/auth/window` | Sign-in window opened by an embedded page | public |

`auth`, `embed`, `profiles` and `publications` are therefore not usable as
publication slugs.

The publication is rendered by the layout of its segment
(`app/[publication]/layout.tsx`): moving from one asset to another only
changes the URL, the grid and the media loaded stay in place.

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
  file itself. Nothing is offered when downloads are disabled.
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
- Sentry wiring (variables exposed, no SDK yet).

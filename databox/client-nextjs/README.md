# Databox client (Next.js)

Next generation of the Databox web client (`databox/client`), built with
Next.js (App Router), React 19, TypeScript, Tailwind CSS v4, TanStack Query,
Zustand, react-hook-form + zod and Radix primitives.

## Development

Everything runs through Docker (nothing is installed on the host):

```bash
# install deps (from the repository root)
dc run --rm dev pnpm install --filter databox-client-nextjs

# start the dev server (profile `databox-next`)
COMPOSE_PROFILES=...,databox-next dc up -d databox-client-next
# => https://databox-next.${PHRASEA_DOMAIN}

# checks
dc run --rm dev pnpm --filter databox-client-nextjs typecheck
dc run --rm dev pnpm --filter databox-client-nextjs lint
dc run --rm dev pnpm --filter databox-client-nextjs test
```

The Keycloak client is provisioned by the configurator when
`DATABOX_NEXT_CLIENT_ID` / `DATABOX_NEXT_CLIENT_URL` are set.

## Runtime configuration

The same image runs against any stack: the configuration is read from the
container environment at request time (see `src/lib/config/server.ts`), never
baked at build time.

| Variable | Description |
|---|---|
| `DATABOX_API_URL` | Databox API base URL |
| `DATABOX_NEXT_CLIENT_URL` | Public URL of this client |
| `KEYCLOAK_URL`, `KEYCLOAK_REALM_NAME`, `CLIENT_ID` | OpenID Connect settings |
| `AUTO_CONNECT_IDP` | Optional IdP hint |
| `SOKETI_HOST`, `SOKETI_KEY`, `NOTIFICATIONS_ENABLED` | Realtime |
| `S3_MULTIPART_*`, `S3_MAX_OBJECT_SIZE`, `ALLOWED_FILE_TYPES` | Uploads |
| `DASHBOARD_CLIENT_URL`, `DISPLAY_SERVICES_MENU` | Services menu |
| `MATOMO_URL`, `MATOMO_SITE_ID`, `SENTRY_DSN`… | Analytics / monitoring |
| `DATABOX_TICKETING_ENABLED`, `DATABOX_TICKETING_JIRA_*` | Ticketing (see below) |
| `CONFIGURATOR_S3_*`, `CONFIG_IS_PUBLIC`, `STACK_CONFIG_SRC`, `STACK_CONFIG_REFRESH_INTERVAL` | Stack configuration (see below) |

## Stack configuration and themes

Like the other clients, this one reads the **stack configuration**: the
configurator entries (`Global Config` in the API admin) that the API dumps to
`config.json` in the static bucket. It carries the `logo` and the
**organisation theme** (`databox.theme`). The legacy clients fetch it once at
container start (`lib/bash/configurator/get-config.sh`) and compile it into
their HTML; this server does the same at request time, so that the theme is
served as CSS with the page (no flash, no extra request):

- when `CONFIGURATOR_S3_ENDPOINT` / `CONFIGURATOR_S3_BUCKET_NAME` are set,
  `config.json` is fetched from the bucket (same URL and signature rules as
  `get-config.sh`, `VERIFY_SSL` honoured) and cached for
  `STACK_CONFIG_REFRESH_INTERVAL` seconds (default 60), so that a change made
  by an administrator needs no restart;
- otherwise (or when the bucket cannot be reached) the file written at
  container start is read (`STACK_CONFIG_SRC`, default
  `/etc/app/stack-config.json`).

The theme menu has two independent choices: the **appearance** (light / dark
/ system, handled by next-themes with the `dark` class) and the **theme**
(`data-theme` on `<html>`): the base palette, ten built-in presets or, when
one is defined, the organisation theme. Every preset is a light palette with a
dark alternative and its own font, corner radius, base size and letter spacing
(`src/app/globals.css`, `[data-theme='<id>']` and `.dark[data-theme='<id>']`).
Administrators define the organisation theme from *Customize theme…*
(`/admin/theme`): a light palette of hex colors, an optional dark alternative,
the corner radius, the base font size, the letter spacing and the font family,
previewed live on the page in either appearance. Saving goes through
`PUT /client-theme` on the API, which stores the `databox.theme` configurator
entry (validated with the same rules as the schema of the entry) and schedules
the push of `config.json` to the bucket.
The "apply by default" option makes it the theme of users who never picked one.

## Ticketing (JIRA)

When `DATABOX_TICKETING_ENABLED` is true, a floating button (bottom right of
the app shell, signed-in users only) opens a report form. The ticket is pushed
to JIRA by the `POST /api/ticketing` route handler, enriched with:

- the page the user was on (URL, title, locale, theme, timezone, viewport,
  screen, user agent, last uncaught JS errors of the session),
- their session (user id, username, email, roles, groups, Keycloak session id),
  rebuilt **server side** from the access token — the browser never sends its
  own identity, and the token is validated against Keycloak's userinfo
  endpoint before anything is created,
- an optional screenshot (Screen Capture API, or an image pasted with Ctrl+V),
  uploaded as an issue attachment.

The JIRA credentials stay in the server container: only `ticketing.enabled`
reaches the browser. Ticket creation is rate limited to 5 per user / 10 min
and per process.

| Variable | Description |
|---|---|
| `DATABOX_TICKETING_ENABLED` | Enables the module (default `false`) |
| `DATABOX_TICKETING_JIRA_URL` | JIRA base URL, e.g. `https://acme.atlassian.net` |
| `DATABOX_TICKETING_JIRA_USER` | JIRA Cloud account email (Basic auth). Leave empty on Server/DC to authenticate with a PAT |
| `DATABOX_TICKETING_JIRA_API_TOKEN` | API token (Cloud) or personal access token (Server/DC) |
| `DATABOX_TICKETING_JIRA_PROJECT_KEY` | Target project key |
| `DATABOX_TICKETING_JIRA_ISSUE_TYPE` | Issue type for improvements / questions (default `Task`) |
| `DATABOX_TICKETING_JIRA_BUG_ISSUE_TYPE` | Issue type for bugs (defaults to the above) |
| `DATABOX_TICKETING_JIRA_LABELS` | Labels added to every issue (default `databox`); `databox-<kind>` is always added |
| `DATABOX_TICKETING_JIRA_API_VERSION` | `3` = Cloud / ADF (default), `2` = Server/DC / wiki markup |
| `KEYCLOAK_INTERNAL_URL` | Keycloak URL reachable from the container, used to verify the reporter's token (falls back to `KEYCLOAK_URL`) |

## Layout

```
src/
  app/            Next.js routes (App Router). Shareable dialogs are
                  intercepting routes under app/(app)/@modal.
  components/ui   Design-system primitives (Radix + Tailwind)
  features/       Domain modules (search, assets, collections, upload…)
  lib/            Config, auth (OIDC/PKCE), API client, realtime, utils
  i18n/           i18next setup and translations
  types/          API contracts
```

The top bar (`components/layout/TopBar`) is permanent chrome: it always shows
the logo, where the user is and the user menu. Screens that take over the
window — asset viewer, basket view, batch attribute editor — are laid out
*below* it (`belowTopBar` in `components/layout/chrome.ts`), and publish their
name to it with `usePageTrail(label, href?)`, which renders as
`Assets › Basket X › Asset Y`: the trail is the way back out (and stacks when
a screen opens over another).

## Feature coverage (vs. `databox/features.md`)

Implemented:

- Search: full-text, AQL conditions (hand-written parser/serializer/validator,
  builder UI), facets (list, boolean, date histogram, geo distance, settings
  and ordering), sort & grouping, saved searches (privacy, management),
  debug ES dialog, URL-addressable search state.
- Results: grid & list layouts (virtualized), dividers, selection (Ctrl+A,
  ranges), hover preview, display options, context menu, infinite scroll.
- Assets: viewer (image zoom/pan, video/audio, PDF), resizable side panel
  whose single-line tabs (the rest under “…”) hold everything about the asset
  — info (attributes, metrics, attachments, discussion, integrations,
  appears-in), renditions, versions, permissions, workflow, operations, ES
  document — plus an edit mode toggled from the toolbar, story carousel
  (browses the items of the story in place), photo editor (Toast UI
  integration), copy/move/delete/restore/export/replace source/save as,
  batch attribute editor (undo/redo, preview).
- Files & quarantine: analysis chips, quarantine banner, duplicate merge,
  file manage dialog.
- Upload: dropzone, URL import, templates, stories, pending uploads, toasts.
- Workspaces admin: info/edit/permissions, tags, entity lists (values,
  import/export, merge, moderation), attribute definitions & policies,
  rendition definitions & policies, asset policies, integrations, tag filter
  rules (the API has no attribute filter rule resource).
- Collections tree (CRUD, move, notifications, permissions, trash),
  baskets (panel, view, manage, integrations), share links & embed,
  ACL editor with inherited permissions, display profiles (organize + grid
  card editor), CMS pages (TipTap editor with asset/carousel/grid/search
  widgets, public rendering), workflows view, operation tasks, discussion
  with mentions, notifications & realtime (Soketi/Pusher), preferences
  (theme, UI & data locale), keyboard shortcuts, runtime configuration.
- Themes: light / dark / system appearance, ten presets (palette, font,
  radius, tracking, each with a dark alternative), and the organisation theme
  customized by administrators (stack configuration, compiled server side).
- Ticketing: floating button creating a JIRA issue with the page context, the
  user session and an optional screenshot.

Not (fully) implemented yet:

- Annotations / drawing tools on the viewer.
- Leaflet / OpenStreetMap maps (the geo facet is list-only).
- Page-by-page PDF player (iframe fallback is used).
- Matomo / Sentry wiring (env variables are exposed, no SDK yet).
- Vendor-specific integration UIs, apart from the Toast UI photo editor
  (Rekognition boxes, Remove.bg compare).
- Translations: `en` and `fr` are complete, `de` / `es` fall back to English.

## End-to-end tests (Cypress)

The e2e suite lives in the repository-level Cypress project
(`cypress/cypress/e2e/databox-next/`), one spec per feature domain of
`databox/features.md` (`01-navigation-auth` … `27-stories`). Each spec
seeds its own workspace through the API with the `databox-admin` service
account (`lib/api.js`), logs in once through Keycloak with `cy.session`
(`lib/app.js`) and cleans up after itself. Stable hooks are exposed with
`data-testid` attributes (`cy.getBySel(...)`).

Run the whole suite against a running stack (profiles `databox-next` and
`cypress` enabled):

```bash
dc run --rm cypress
```

Run a single spec:

```bash
dc run --rm cypress --spec cypress/e2e/databox-next/02-search.cy.js
```

The CI flow (`bin/dev/run-tests-in-ci-conditions.sh`) starts
`databox-client-next` and runs the same suite.

Things the specs rely on (see `lib/api.js` / `lib/app.js`):

- A workspace created through the API has none of the defaults the admin UI
  gives it: the seed replicates `WorkspaceCreator` (rendition policy, the
  Main / Preview / Thumbnail chain, the read-metadata and rendition
  integrations) so that uploads get renditions, and adds a `Title` attribute
  flagged *fill from name* because the asset name is stored as an attribute.
- Asset names are indexed for prefix matching only: seeded assets use single
  distinct words (`Alpha`, `Bravo`…).
- AQL conditions use the attribute `slug`; sort and facet keys use the
  `searchSlug` (`keywords_text_m`).
- Radix portals (select lists, popovers, toasts) live outside the dialogs:
  `cy.selectOption()`, `expectToastText()` and `cy.dialog()` escape any
  `.within()` scope and wait for the open animation before typing.

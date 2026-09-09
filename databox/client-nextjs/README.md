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

## Feature coverage (vs. `databox/features.md`)

Implemented:

- Search: full-text, AQL conditions (hand-written parser/serializer/validator,
  builder UI), facets (list, boolean, date histogram, geo distance, settings
  and ordering), sort & grouping, saved searches (privacy, management),
  debug ES dialog, URL-addressable search state.
- Results: grid & list layouts (virtualized), dividers, selection (Ctrl+A,
  ranges), hover preview, display options, context menu, infinite scroll.
- Assets: viewer (image zoom/pan, video/audio, PDF), side panel (attributes,
  info, metrics, attachments, discussion, integrations, appears-in), story
  carousel, manage dialog (info, edit, renditions, versions, permissions,
  workflow, operations, ES document), copy/move/delete/restore/export/
  replace source/save as, batch attribute editor (undo/redo, preview).
- Files & quarantine: analysis chips, quarantine banner, duplicate merge,
  file manage dialog.
- Upload: dropzone, URL import, templates, stories, pending uploads, toasts.
- Workspaces admin: info/edit/permissions, tags, entity lists (values,
  import/export, merge, moderation), attribute definitions & policies,
  rendition definitions & policies, asset policies, integrations, filter rules.
- Collections tree (CRUD, move, notifications, permissions, trash),
  baskets (panel, view, manage, integrations), share links & embed,
  ACL editor with inherited permissions, display profiles (organize + grid
  card editor), CMS pages (TipTap editor with asset/carousel/grid/search
  widgets, public rendering), workflows view, operation tasks, discussion
  with mentions, notifications & realtime (Soketi/Pusher), preferences
  (theme, UI & data locale), keyboard shortcuts, runtime configuration.

Not (fully) implemented yet:

- Annotations / drawing tools on the viewer.
- Leaflet / OpenStreetMap maps (the geo facet is list-only).
- Page-by-page PDF player (iframe fallback is used).
- Matomo / Sentry wiring (env variables are exposed, no SDK yet).
- Vendor-specific integration UIs (Rekognition boxes, Remove.bg compare,
  TUI image editor).
- Translations: `en` and `fr` are complete, `de` / `es` fall back to English.

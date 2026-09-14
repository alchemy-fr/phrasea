# Databox client — Server-Side Rendering

The databox client can render the **public share page** (`/s/:id/:token`) server-side.
Every other route keeps the classic SPA behavior. SSR gives shared links a real
`<title>`, OpenGraph tags (social previews) and a fully rendered first paint
(terms, assets, attachments) before JavaScript loads.

## How it works

- `src/entry-server.tsx` — matches the share route, prefetches the share through
  the API (react-query), renders the standalone `ShareApp` tree with
  `renderToString`, extracts critical CSS with `@emotion/server`, and builds the
  head tags.
- `src/ShareApp.tsx` — the reduced provider tree (query client + theme +
  `ShareView`), rendered identically on the server and on hydration. It must stay
  free of browser-only providers (auth, analytics, modals, router).
- `src/index.tsx` — hydrates with `hydrateRoot` when the server injected
  `window.__REACT_QUERY_STATE__` on a share URL; otherwise mounts the SPA as
  before.
- `server.mjs` — Node server; vite middleware mode in dev (HMR included),
  `dist/client` + `dist/server` in production. On any SSR error it falls back to
  serving the SPA shell.

## Running it

```bash
# dev (inside the databox client container or the dev container)
pnpm --filter databox-client dev:ssr

# production build + run
pnpm --filter databox-client build:ssr
NODE_ENV=production pnpm --filter databox-client start:ssr
```

Environment variables:

| Variable | Purpose |
|---|---|
| `SSR_PORT` | Listen port (default `3000`) |
| `SSR_API_BASE_URL` | Internal API endpoint for server-side fetches (e.g. `http://databox-api-nginx`) when the public hostname/TLS is not reachable from the container |

## Constraints for SSR-safe code

The server evaluates the whole module graph of the share page. When touching the
share subtree (`ShareView`, `AssetShare`, `FilePlayer`, attributes…):

- no `window` / `document` access at module scope (guard with
  `typeof document !== 'undefined'`); the server provides a minimal `window`
  shim (config, navigator, location) but **no `document`** — emotion and
  js-cookie rely on its absence to stay in server mode;
- browser-only libraries (leaflet…) must be behind `React.lazy` — see
  `GeoPointType` / `FileAnalysisChipWrapper`;
- do not import `routes.ts` (it drags the entire application) nor package
  barrels that pull browser-only modules — prefer deep imports as in
  `ShareLogo` / `ShareApp`;
- CJS packages breaking Node ESM interop belong to `ssr.noExternal` in
  `vite.config.ts`.

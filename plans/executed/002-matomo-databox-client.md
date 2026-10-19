---
id: 002-matomo-databox-client
title: Matomo dans le client Databox et tracking des interactions
status: executed
finished: 2026-10-19 14:49
started: 2026-10-19 11:33
run_base: bd9d98b44e664cf3b0e6b4dea62d54e6d4d3131d
created: 2026-10-19 11:08
branch: next
depends_on: []
---

# Matomo dans le client Databox (Next.js) + tracking de toutes les interactions

## Contexte

L'ancien client databox (Vite/MUI, supprimé au commit `b83d4183d`) envoyait des données à Matomo
via `@alchemy/phrasea-framework` (`initApp` → `createInstance`, `AnalyticsProvider`,
`MatomoRouteWrapper`, `useTracking`, `useAppInit`). Le nouveau client `databox/client`
(Next.js App Router, React 19) expose déjà la config Matomo au navigateur
(`config.analytics.matomo`, `src/lib/config/server.ts`) et affiche les métriques d'un asset
(`AssetMetrics.tsx` → `GET /assets/{id}/metrics`), mais **n'envoie plus rien** : les métriques
restent vides. Objectif : rétablir à l'identique le tracking de l'ancien client (page views,
user id, contenus, vidéos) **et** ajouter un `trackEvent` pour toutes les interactions utilisateur.

### Ce que faisait l'ancien client (à reproduire)

- `trackPageView()` à chaque changement de route + `enableLinkTracking()`.
- `setUserId(user.id)` après login, `setUserId(undefined)` au logout.
- **Content tracking** (aucun `trackEvent` n'existait) :
  - `trackContentImpression(contentName = asset.name, contentPiece = asset.resolvedTrackingId)`
    à l'ouverture d'un asset (une fois par `asset.id`), **sauf audio/vidéo** ;
  - `trackContentInteraction('click', asset.name, asset.resolvedTrackingId)` au clic sur
    l'image / la page PDF.
- **MediaAnalytics** (vidéo) : attributs `data-matomo-resource=<resolvedTrackingId>` et
  `data-matomo-title=<asset name>` sur le `<video>`, puis
  `_paq.push(['MediaAnalytics::scanForMedia', container])`. L'audio n'était pas tracké.
- Init : `setSiteId(parseInt(siteId))`, `setSecureCookie(true)`, tracker
  `${baseUrl}/matomo.php`, script `${baseUrl}/matomo.js`.

### Contrat de compatibilité côté API (ne pas casser)

`databox/api/src/Service/Matomo/MatomoManager.php` lit Matomo par `filter_pattern=<trackingId>` :
`Contents.getContentPieces` (label = **content piece**), ou
`MediaAnalytics.getGrouped{Video,Audio}Resources` (label = `data-matomo-resource`) si
`MATOMO_MEDIA_PLUGIN_ENABLED`. `ShareRenditionProvider.php` fait aussi côté serveur
`doTrackContentImpression(name, resolvedTrackingId)`. Expose envoie le même tracking id.
`expose/api` (`SyncMatomoPhraseanetCommand`) parse les ressources vidéo `^\w+_(\d+)_(\d+)$`.
⇒ **Content piece et media resource = `asset.resolvedTrackingId`, tel quel.** Les `trackEvent`
ne sont lus par aucun code serveur : leur nommage est libre.

## Décisions prises avec l'utilisateur

1. **Granularité « intention + résultat »** : un événement au déclenchement de chaque action
   d'asset (menu contextuel, barre de sélection, barre du viewer) **et** un événement de succès
   au submit des dialogues / opérations (download/export, share, upload, move/copy, delete,
   restore, edit, story, collection, basket, workflows, intégrations, discussion, follow,
   saved search), plus la recherche et le drag & drop. **Pas** d'événement à chaque
   `openModal` ni aux changements d'onglet du side panel.
2. **Page de partage publique `/s/[id]/[token]` trackée aussi** (visiteurs anonymes) :
   page view, impression de contenu, média, téléchargement.
3. **Cookies configurables par env** : nouvelle variable `MATOMO_DISABLE_COOKIES` (défaut
   `false`) ; si vraie → `_paq.push(['disableCookies'])`. `setSecureCookie` et `setUserId`
   conservés dans tous les cas. Pas de bannière de consentement.

## Approche technique

**Pas de dépendance npm** : un petit module maison autour de `window._paq` plutôt que
`@jonkoops/matomo-tracker-react` (l'ancien client devait le mettre en `ssr.noExternal` pour des
problèmes d'interop CJS, sa lib `MatomoRouteWrapper` dépend de react-router, et
`@alchemy/phrasea-framework` tire MUI + React 18 — ne pas l'importer). Le snippet Matomo
standard suffit, et la navigation douce Next exige de toute façon `setCustomUrl` /
`setReferrerUrl` / `setDocumentTitle` manuels avant chaque `trackPageView`.

## Fichiers concernés

Chemins depuis la racine du dépôt.

**Nouveaux**
- `databox/client/src/lib/analytics/matomo.ts` — wrapper `_paq`, sans React :
  `initMatomo(config)` (idempotent ; `setTrackerUrl`, `setSiteId`, `setSecureCookie`,
  `disableCookies` si demandé, `enableLinkTracking`, injection unique de `matomo.js` ; gérer
  `baseUrl` avec ou sans `/` final), `trackPageView(url, title, referrer?)`,
  `trackEvent(category, action, name?, value?)`, `trackSiteSearch(keyword, category?, count?)`,
  `trackContentImpression(name, piece)`, `trackContentInteraction(interaction, name, piece)`,
  `setUserId(id | undefined)` (`resetUserId` si undefined), `scanForMedia(el)`
  (`MediaAnalytics::setPingInterval` 10 + `scanForMedia`). Tout est **no-op** côté serveur
  (`typeof window === 'undefined'`) ou tant que `initMatomo` n'a pas été appelé (config absente).
- `databox/client/src/lib/analytics/events.ts` — catalogue typé des événements (constantes de
  catégories/actions, voir plus bas) + helper `track(category, action, opts?)` qui délègue à
  `trackEvent`. Interdiction d'y mettre des données personnelles ou des titres d'assets : `name`
  = surface / type / clé d'attribut, `value` = nombre d'éléments.
- `databox/client/src/lib/analytics/MatomoTracker.tsx` — composant client sans rendu :
  initialise Matomo depuis `useConfig().analytics.matomo` ; page view à chaque changement de
  `usePathname()` + `useSearchParams().toString()` (même pattern que `RouteHistoryProvider` dans
  `src/components/modals/RouteDialog.tsx:72`), avec referrer = URL précédente et
  `document.title` lu dans un `requestAnimationFrame`/`setTimeout(0)` pour laisser Next le mettre
  à jour ; `setUserId(user?.id)` sur changement de `useAuth().user` (utilisateur effectif, donc
  impersoné le cas échéant).
- `databox/client/src/lib/analytics/useContentTracking.ts` — hook
  `useContentImpression(asset)` (une fois par `asset.id` ; ignoré pour la vidéo seulement quand
  `mediaPluginEnabled`, sinon impression pour tous les types — audio compris, ce qui alimente
  `Contents.getContentPieces` puisque l'audio n'a pas de MediaAnalytics) et
  `contentClick(asset)`.
- Tests : `matomo.test.ts`, `MatomoTracker.test.tsx` (voir Vérification).
- `doc/tech/Databox/analytics.md` — config (variables d'env), contrat tracking id, catalogue
  des événements.

**Modifiés**
- `databox/client/src/lib/config/types.ts` + `src/lib/config/server.ts` — ajouter
  `disableCookies: boolean` (`bool(env.MATOMO_DISABLE_COOKIES)`).
- `databox/client/src/app/providers.tsx` — monter `<MatomoTracker />` à l'intérieur de
  `AuthProvider` (et du `Suspense` existant, requis par `useSearchParams`). `providers.tsx`
  enveloppe aussi `/s/[id]/[token]` : la page de partage est donc couverte (décision 2).
- `docker-compose.yml` — ajouter `MATOMO_DISABLE_COOKIES` à l'environnement du service
  `databox-client` (celui qui liste déjà `MATOMO_URL` / `MATOMO_SITE_ID` /
  `MATOMO_MEDIA_PLUGIN_ENABLED` ; vérifier lequel des deux blocs, l'autre est probablement
  dashboard — ne l'ajouter qu'à databox-client).
- `.env` — `MATOMO_DISABLE_COOKIES=0` à côté de `MATOMO_MEDIA_PLUGIN_ENABLED`.
- `databox/client/README.md` — table des env (ligne Matomo) et retirer de la section « reste à
  faire » la mention « Matomo / Sentry wiring (env variables are exposed, no SDK yet) » →
  laisser seulement Sentry.
- Players `databox/client/src/features/assets/player/` :
  - `FilePlayer` : nouvelle prop optionnelle `tracking?: {id: string; title?: string}`
    transmise aux players ;
  - `VideoPlayer` interne : poser `data-matomo-resource` / `data-matomo-title` sur le `<video>`
    **puis** appeler `scanForMedia(container)` dans un effet (seulement si
    `mediaPluginEnabled`) ;
  - `ZoomableImage` et `PdfPlayer` : `onClick`/`onPointerUp` sans drag → `contentClick` (PDF en
    iframe : poser le handler sur le conteneur ; si le clic dans l'iframe est inobservable,
    l'accepter et le noter dans la doc).
  - Ne pas tracker `AssetThumb.tsx` (vignettes vidéo).
- Appelants de `FilePlayer` qui passent `tracking={{id: asset.resolvedTrackingId, title:
  asset.name}}` et appellent `useContentImpression(asset)` :
  `src/features/assets/view/AssetViewRoute.tsx` (viewer),
  `src/features/share/SharePage.tsx`, `src/features/share/ShareAssetView.tsx` (share public).
  `AssetPreview.tsx` (survol) : `tracking` pour la vidéo seulement, **pas** d'impression (trop
  bruyant — l'ancien client ne le faisait pas non plus au survol). Si `resolvedTrackingId` est
  absent du type renvoyé par l'API de partage, retomber sur `trackingId ?? id` (même ordre que
  `Asset::getResolvedTrackingId()`).
- Points d'accroche `trackEvent` (catalogue ci-dessous), fichiers sous
  `databox/client/src/` :
  - `features/assets/actions/useAssetActions.tsx` — envelopper **chaque** `run` dans le
    `useMemo` : `track('Asset action', action.id, {name: surface, value: assets.length})`.
    `surface` = `menu` | `toolbar` | `viewer` : réutiliser l'option `context` si elle porte déjà
    cette info, sinon ajouter une option `surface` passée par `AssetContextMenu.tsx`,
    `list/toolbar/SelectionActions.tsx`, `view/AssetViewActions.tsx` (attention à l'override de
    `edit.run` ligne ~48 de `AssetViewActions.tsx` : l'override doit lui aussi tracker).
  - Résultats (au succès, après l'appel API, jamais en cas d'erreur) :
    `features/assets/actions/ExportDialog.tsx` (`submit`), `features/share/ShareDialog.tsx`
    (`onSuccess` create/update/delete), `features/share/ShareDownloadDialog.tsx`,
    `features/upload/UploadDialog.tsx` (`submit` : upload / post / import d'URLs),
    `features/assets/actions/CopyMoveDialog.tsx`, `DeleteAssetsDialog.tsx`,
    `RestoreAssetsDialog.tsx`, `CreateStoryDialog.tsx`, `SaveAsDialog.tsx`,
    `ReplaceSourceDialog.tsx`, `features/assets/manage/tabs/AssetEditTab.tsx`,
    `features/assets/manage/tabs/AssetOperationsTab.tsx`,
    `features/attributes/batch/AttributeBatchEditorRoute.tsx`,
    `features/collections/CreateCollectionDialog.tsx`, `CollectionDialogs.tsx`,
    `collections/manage/tabs/CollectionOperationsTab.tsx`,
    `features/baskets/basketStore.ts` (`addToCurrent`, `removeItems`),
    `features/baskets/BasketFormDialog.tsx`, `features/assets/view/StoryCarousel.tsx`
    (réordonnancement, retrait), `features/stories/pinnedStoriesStore.ts` (`toggle`),
    `features/assets/manage/tabs/AssetWorkflowTab.tsx`,
    `features/workflows/WorkflowRunsPanel.tsx`, `features/workflows/WorkflowViewRoute.tsx`,
    `features/baskets/manage/BasketIntegrations.tsx`, `integrations/tui/PhotoEditorDialog.tsx`,
    `features/discussion/Discussion.tsx` (`send`), `components/FollowButton.tsx`,
    `features/saved-searches/SaveSearchDialog.tsx`, `usePinAsSavedSearch.ts`.
  - Recherche : `features/search/SearchProvider.tsx` — un `track('Search', <méthode>)` dans
    chaque méthode publique (`setQuery`, `setSortBy`, `setGeolocation`, `upsertCondition`,
    `removeCondition`, `resetWithCondition`, `selectWorkspace`, `selectCollection`,
    `loadSavedSearch`, `reset`) — `name` = clé de l'attribut/facette ou du tri, **jamais** le
    texte libre ni l'AQL complet. `features/search/ResultProvider.tsx` — `trackSiteSearch(q,
    category, total)` une fois par `checksum` quand la première page est arrivée (`q` = texte
    libre, `''` si seulement des filtres ; `category` = `'filters'` si conditions actives).
  - Drag & drop : `features/dnd/executeDrop.ts` — `track('DnD', \`${payload}_to_${target}\`,
    {name: op, value: count})` après succès.

### Catalogue (à documenter dans `doc/tech/Databox/analytics.md`)

| Catégorie | Actions |
|---|---|
| `Asset action` | ids de `useAssetActions` : `open`, `info`, `pin-story`, `basket`, `download`, `save-as`, `alt-{i}`→`alt`, `edit`, `share`, `move`, `copy`, `create-story`, `replace`, `restore`, `delete` (name = surface, value = nb d'assets) |
| `Asset` | `download`, `export`, `move`, `copy`, `delete`, `restore`, `edit`, `batch_edit`, `save_as`, `replace_source`, `upload`, `import_urls` |
| `Share` | `create`, `update`, `delete`, `download` |
| `Collection` | `create`, `delete`, `restore`, `move` |
| `Basket` | `create`, `add`, `remove` |
| `Story` | `create`, `reorder`, `remove_item`, `pin`, `unpin` |
| `Workflow` | `trigger`, `cancel`, `rerun_job` |
| `Integration` | `run` (name = type/action d'intégration) |
| `Discussion` | `send` |
| `Follow` | `follow`, `unfollow` |
| `Saved search` | `save`, `pin`, `load` |
| `Search` | méthodes de `SearchProvider` (+ `trackSiteSearch`) |
| `DnD` | `<payload>_to_<target>` |

L'agent peut affiner les noms d'actions si le code révèle d'autres opérations, en gardant
snake_case et en mettant la doc à jour.

## Étapes

1. Lire `lib/js/phrasea-framework/src/{init.ts,hooks/useTracking.ts,hooks/useAppInit.ts,
   Route/MatomoRouteWrapper.tsx,FilePlayer/Players/VideoPlayer.tsx}` et
   `git show b83d4183d^:databox/client/src/components/Media/Asset/Players/VideoPlayer.tsx`
   comme référence (lecture seule — ne pas modifier `lib/js`).
2. Config : `types.ts`, `server.ts`, `.env`, `docker-compose.yml`.
3. `matomo.ts` + `events.ts` + tests unitaires.
4. `MatomoTracker.tsx` dans `providers.tsx` + test.
5. Content & media : `useContentTracking.ts`, `FilePlayer`/players, appelants (viewer, share,
   preview).
6. `trackEvent` : `useAssetActions` (+ surfaces), puis dialogues/stores, recherche, DnD.
   Étendre `useAssetActions.test.tsx` et `executeDrop.test.ts` (mock `@/lib/analytics/events`).
7. Doc `doc/tech/Databox/analytics.md` + README.
8. Lint / format / tests / tsc.

## Vérification

Commandes (conteneur `dev`, piège TTY : passer par `--entrypoint sh`, chemin
`/var/workspace/<nom du worktree>`) :

```bash
dc run --rm --entrypoint sh dev -c "su app -c 'cd /var/workspace/<worktree> && pnpm --filter databox-client test'"
dc run --rm --entrypoint sh dev -c "su app -c 'cd /var/workspace/<worktree> && pnpm --filter databox-client lint'"
dc run --rm --entrypoint sh dev -c "su app -c 'cd /var/workspace/<worktree> && pnpm --filter databox-client exec tsc --noEmit'"
```

`tsc` a ~15 erreurs préexistantes (doubles `@types/react`) : ne vérifier qu'aucune nouvelle
n'apparaît dans les fichiers touchés.

Tests unitaires attendus :
- `matomo.test.ts` : no-op avant init et sans `window` ; après `initMatomo`, contenu exact de
  `window._paq` (`setTrackerUrl`, `setSiteId` numérique, `setSecureCookie`, `disableCookies`
  seulement si demandé) ; script injecté une seule fois ; `baseUrl` avec/sans `/` final ;
  ordre des arguments content (`trackContentImpression`, name, piece) et
  (`trackContentInteraction`, 'click', name, piece).
- `MatomoTracker.test.tsx` : page view à chaque changement pathname/search (mock
  `next/navigation`), `setUserId` au login, reset au logout, rien si `analytics.matomo` absent.
- `useAssetActions.test.tsx` : `run()` d'une action émet `Asset action` / id / surface / count.
- `executeDrop.test.ts` : événement DnD émis au succès, pas à l'échec.

**Exige l'environnement partagé (après intégration)** : dans `.env.local`, renseigner
`MATOMO_SITE_ID` (site créé dans l'instance Matomo locale `https://matomo.${PHRASEA_DOMAIN}`),
recréer le conteneur (`dc up -d databox-client`), puis dans le navigateur vérifier les requêtes
`matomo.php` : page view par navigation (y compris ouverture d'un asset en dialogue de route),
`uid` après login, `c_n`/`c_p` = nom / resolvedTrackingId à l'ouverture d'une image, `e_c`/`e_a`
sur une action, `search=` sur une recherche, MediaAnalytics sur une vidéo (si plugin), et sur
`/s/<id>/<token>` en navigation privée. Avec `MATOMO_DISABLE_COOKIES=1`, aucun cookie `_pk_*`.
Enfin `GET /assets/{id}/metrics` doit remonter `nb_impressions` après archivage Matomo.

## Hors périmètre

- Sentry (config exposée mais non câblée) ; MediaAnalytics pour l'audio (wavesurfer n'expose pas
  de `<audio>` dans le DOM) — l'audio est couvert par l'impression de contenu ; bannière de
  consentement ; modification de `lib/js/phrasea-framework`, des clients expose/uploader/
  dashboard ou de l'API ; tracking des erreurs/toasts ; custom dimensions Matomo.

## Interactions avec la file

- `001-fixtures-databox-3-workspaces` (fixtures `databox/api`, `lib/php/core-bundle`, docs
  `doc/tech/Databox/map.md`, `fixtures.md`, `doc/tech/01_setup.md`) : aucun recouvrement.
- `001-readme-open-source` (`README.md` racine, fichiers communauté, `.github/`) : aucun
  recouvrement (ce plan touche `databox/client/README.md`, pas le README racine).
- ⚠️ Le worktree principal (branche `next`) porte des modifications **non commitées** dans
  `databox/client/src/features/assets/view/StoryCarousel.tsx`,
  `databox/client/src/features/dnd/executeDrop.ts` et des fichiers non suivis
  `src/features/stories/refreshStoryItems{,.test}.ts`. Si l'exécution se fait dans un worktree
  séparé, ces changements n'y seront pas : intégrer en résolvant les conflits sur ces deux
  fichiers sans perdre le travail en cours (jamais de `git checkout --`).

`depends_on: []`

---
title: 'Analytics (Matomo)'
---

# Analytics (Matomo)

The databox client (`databox/client`) sends its audience to Matomo: page views,
the user id, the display of assets (content tracking), video playback
(MediaAnalytics) and an event for every user interaction. The API reads part
of it back to show the metrics of an asset (`GET /assets/{id}/metrics`, the
"Metrics" block of the asset viewer).

Nothing is sent when Matomo is not configured.

## Configuration

Environment of the `databox-client` container, read at request time
(`src/lib/config/server.ts`):

| Variable | Description |
|---|---|
| `MATOMO_URL` | Base URL of the Matomo instance (with or without a trailing `/`): the tracker is `${MATOMO_URL}/matomo.php`, the script `${MATOMO_URL}/matomo.js` |
| `MATOMO_SITE_ID` | Id of the site in Matomo. Tracking is off while it is empty |
| `MATOMO_MEDIA_PLUGIN_ENABLED` | The [MediaAnalytics](https://matomo.org/analytics-for-media/) plugin is installed: videos are tracked by it (and the API reads the media reports) |
| `MATOMO_DISABLE_COOKIES` | `1`: no Matomo cookie (`_pk_*`) is set (`disableCookies`). Default `0` |
| `MATOMO_COOKIE_CONSENT` | `1` (default): a banner asks the visitor before Matomo sets its cookies (see below). `0`: the cookies are set without asking. Ignored with `MATOMO_DISABLE_COOKIES=1` |

`setSecureCookie` is always on. The user id (`setUserId`) is the Keycloak id
of the effective user (the impersonated one when an admin switched user); it
is reset on logout.

The four Next.js clients (databox, expose, uploader, dashboard) send their
page views with the same tracker and read the same `MATOMO_*` variables
(`MATOMO_MEDIA_PLUGIN_ENABLED` only in databox and expose).

## Cookie consent

Unless `MATOMO_COOKIE_CONSENT=0` or `MATOMO_DISABLE_COOKIES=1`, the clients
ask the visitor whether Matomo may set its cookies. Only the cookies are
subject to consent, never the tracking itself: the metrics of the assets stay
complete.

- Matomo runs with `requireCookieConsent`: until the visitor accepts, and
  after a refusal, the hits are sent without any cookie.
- A banner at the bottom of the page offers "Accept" and "Refuse", with the
  same weight. It cannot be closed without choosing, and does not block the
  page.
- "Accept" calls `setCookieConsentGiven` (cookies `_pk_*` from then on);
  "Refuse" calls `forgetCookieConsentGiven` (Matomo deletes its cookies).
- The choice is stored by the client, not by Matomo (whose
  `mtm_cookie_consent` cookie does not remember a refusal): local storage key
  `phrasea.analytics.cookie-consent`, `{"granted": true|false, "at": "<ISO
  date>"}`, per origin, so per client. It is applied on every page load and
  forgotten after 13 months (CNIL recommendation): the banner comes back.
  When the storage is not available (private browsing, blocked), the banner
  shows on every visit and the tracking stays without cookies.
- In a frame (the embed pages of expose on a third party site), the banner is
  never shown: Matomo tracks without cookies, unless the visitor already
  accepted them on this origin.
- The "Cookies" item of the settings menu of the top bar reopens the banner to
  change the choice. The pages without a top bar (embed, public share) only
  have the banner.

## Code

The tracker shared by the clients lives in `lib/js/core/src/analytics/`:

- `matomo.ts` — thin wrapper around `window._paq`, without React. Every call is
  a no-op on the server and until `initMatomo()` has run.
- `MatomoTracker.tsx` — initializes Matomo, sets the user id and sends a page
  view on each route change (URL, referrer and title are given explicitly
  since Next navigates without reloading), and renders the cookie consent
  banner. The OAuth callback (`/auth/*`) is not tracked.
- `cookieConsent.ts` — the stored choice of the visitor and the store read by
  the banner; `CookieConsentBanner.tsx` and `CookieConsentMenuItem.tsx` (the
  settings menu item).

The databox specific parts live in `databox/client/src/lib/analytics/`:

- `MatomoTracker.tsx` — the shared tracker with the session of the client,
  mounted in `src/app/providers.tsx` (so on every page, the public share page
  included). The access token of a public share URL is replaced with
  `_token_`.
- `useContentTracking.ts` — content impression of the asset displayed and
  content interaction on a click on its media.
- `events.ts` — the catalog of events below and the `track()` helper.

## Tracking id contract

The API (`MatomoManager`) reads the metrics of an asset with
`filter_pattern=<trackingId>`, from `Contents.getContentPieces` (label = the
content piece) or, with the MediaAnalytics plugin,
`MediaAnalytics.getGrouped{Video,Audio}Resources` (label = the media resource).
Both are therefore the asset's `resolvedTrackingId` **as is** (falling back to
`trackingId`, then the asset id, like `Asset::getResolvedTrackingId()`).
The share rendition endpoint (`ShareRenditionProvider`) and Expose send the
same id.

| What | When | Matomo call |
|---|---|---|
| Impression | An asset is displayed in the viewer or on a public share (once per asset); not on hover previews. A video is left to MediaAnalytics when the plugin is enabled | `trackContentImpression(<asset name>, <resolvedTrackingId>)` |
| Interaction | Click on the image (not ending a drag nor on the zoom toolbar), first click in a PDF | `trackContentInteraction('click', <asset name>, <resolvedTrackingId>)` |
| Media | Video players of the viewer, the share and the hover preview, when the plugin is enabled | `data-matomo-resource=<resolvedTrackingId>`, `data-matomo-title=<asset name>`, then `MediaAnalytics::scanForMedia` |

The PDF viewer is an iframe: clicks inside it never reach the page. Only the
first one is noticed (the focus moving into the iframe). Audio is not tracked
by MediaAnalytics (the waveform player has no `<audio>` element in the page):
its display counts as a content impression.

## Events

`trackEvent(category, action, name, value)`. No event is read by the API: the
names are free, but they never carry personal data, a title or a searched
text. `name` is a surface, a type or an attribute key, `value` a number of
items. Actions are sent when they start (`Asset action`) and when they succeed
(the other categories, after the API call; never on an error).

| Category | Actions | `name` / `value` |
|---|---|---|
| `Asset action` | ids of the asset actions: `open`, `info`, `pin-story`, `basket`, `download`, `save-as`, `alt` (alternate URLs), `edit`, `share`, `move`, `copy`, `create-story`, `replace`, `restore`, `delete` | surface: `menu` (context and item menus), `toolbar` (selection bar), `viewer`; number of assets |
| `Asset` | `create` (without file), `upload`, `import_urls`, `download` (export of one asset), `export`, `move`, `copy`, `delete`, `restore`, `edit`, `batch_edit`, `save_as`, `replace_source`, `remove_shortcut` | `copy`: `reference` / `duplicate`; `delete`: `trash` / `permanent` / `collections`; `save_as`: the mode; `replace_source`: `upload` / `url`; `upload`, `import_urls`: `story` when grouped in a story; number of assets |
| `Share` | `create`, `update`, `delete`, `download` (public share) | number of assets / files |
| `Collection` | `create`, `delete`, `restore`, `move` | `create`: `root` / `child` |
| `Basket` | `create`, `update`, `delete`, `archive`, `unarchive`, `add`, `remove` | number of assets / items |
| `Story` | `create`, `reorder`, `remove_item`, `set_cover`, `pin`, `unpin` | `create`: `upload` when created by the upload dialog; number of assets |
| `Workflow` | `trigger`, `cancel`, `rerun_job` | |
| `Integration` | `run` | `<integration type>:<action>` |
| `Discussion` | `send`, `edit`, `delete` | `send`: number of attachments |
| `Follow` | `follow`, `unfollow` | entity, or topic key |
| `Saved search` | `save`, `pin`, `load` | `save`: `create` / `update`; `pin`: attribute key |
| `Search` | `setQuery`, `setSortBy`, `setGeolocation`, `upsertCondition`, `removeCondition`, `resetWithCondition`, `selectWorkspace`, `selectCollection`, `loadSavedSearch`, `reset` | attribute key of the condition, sorted attributes, `clear`… never the text nor the AQL |
| `DnD` | `<payload>_to_<target>` (`assets_to_collection`, `assets_to_story`, `assets_to_basket`, `assets_to_workspace`, `collection_to_collection`, `collection_to_workspace`) | operation (`add`, `move`, `copy`, `move-collection`); number of items |

Besides, each search is reported once, with its first page of results, to the
site search of Matomo: `trackSiteSearch(<text searched or ''>, 'filters' when
conditions apply, <total>)`.

## Checking it

In `.env.local`, set `MATOMO_SITE_ID` (a site of the local instance
`https://matomo.${PHRASEA_DOMAIN}`), recreate the client (`dc up -d
databox-client`), then watch the `matomo.php` requests in the browser:
`action_name`/`url` on each navigation, `uid` once signed in, `c_n`/`c_p` when
an asset opens, `e_c`/`e_a` on an action, `search=` on a search. No `_pk_id`
cookie is set before "Accept" on the cookie banner; "Refuse" (from the
"Cookies" item of the settings menu) deletes it.

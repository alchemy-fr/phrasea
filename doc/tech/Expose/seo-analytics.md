---
title: SEO and analytics
---

# SEO and analytics

## Indexing

`EXPOSE_CLIENT_ALLOW_INDEXING` (`ALLOW_INDEXING` of the client container; `true` in the `.env`,
and when unset) says whether the search engines may index the client.

**`robots.txt`** (`expose/client/src/app/robots.ts`):

| `ALLOW_INDEXING` | Allowed                | Disallowed                                                                                    |
| ---------------- | ---------------------- | --------------------------------------------------------------------------------------------- |
| `true`           | `/`, `/api/og/`        | `/auth/`, `/api/`, `/embed/`, `/publications` and `/publications/`, `/profiles` and `/profiles/` |
| `false`          | `/s/`, `/api/og/`      | `/`                                                                                           |

The rules on `/publications` and `/profiles` end with `$` or `/`, so that the publications whose
slug starts with these words stay indexable. With indexing off, the share pages and the preview
images stay open to the crawlers: the link preview robots follow `robots.txt`, and the search
engines are kept out of these pages by their `noindex` meta tag.

**Robots meta tag**: every page carries `noindex, nofollow` when indexing is off. Whatever the
setting, these pages are never indexed: `/embed/…`, `/publications…`, `/profiles…`, `/auth/…` and
the share links `/s/…` (their URL carries the token).

A publication that is not publicly listed, or that is protected, is still indexable when its URL
is known and indexing is on.

## Link previews

The pages of a publication, of one of its assets and of a share link carry Open Graph and Twitter
metadata (`src/lib/seo/publicationMetadata.ts`): title, description (the HTML turned into plain
text) and image, the URL of the page and the site name "Expose".

- The Next.js server reads the publication **anonymously** (with the token for a share link),
  within 3 seconds. Only a publication an anonymous visitor may see gets them: one without
  security method, or a publicly listed one (title, description and cover of its reduced
  response). Otherwise, or when the API does not answer, the page gets the metadata of the app.
- The image is the thumbnail (else the preview) of the cover, or of the asset: raster images
  only, no SVG. It is served by the client at a stable URL,
  `/api/og/publication/{publication}` or `/api/og/publication/{publication}/asset/{assetId}`,
  since the URLs given by the API are signed for a while only.
- The page of an asset is titled `asset title · publication title`.

## Home page

`/` lists the publicly listed publications (and, for a signed-in user, the ones they can edit).
With `EXPOSE_DISABLE_INDEX_PAGE=1`, `/` answers 404, the logo of the top bar no longer links to it
and the breadcrumb of the publications has no "Home": the publications are only reached by their
own URL.

## Matomo

The client sends its audience to Matomo, configured by the **environment of the client
container**, read at request time: `MATOMO_URL`, `MATOMO_SITE_ID`,
`MATOMO_MEDIA_PLUGIN_ENABLED`, `MATOMO_DISABLE_COOKIES`, `MATOMO_COOKIE_CONSENT`. Nothing is sent
while `MATOMO_URL` or `MATOMO_SITE_ID` is empty. The variables, the cookie consent and the shared
tracker are described in [Analytics (Matomo)](../Databox/analytics.md).

The Global Config entries `expose.analytics.matomo.baseUrl` and `expose.analytics.matomo.siteId`
(declared by `expose/api/src/Config/Schema/ExposeConfigSchema.php`, and listed in the
[Instance Configuration](../Configuration/02_instance.mdx)) are read by nothing: setting them
has no effect.

What the Expose client tracks:

| What        | When                                                                                                    |
| ----------- | ------------------------------------------------------------------------------------------------------- |
| Page view   | every navigation, with the user id once signed in; not the sign-in pages (`/auth/…`)                    |
| Impression  | an asset displayed by a player, once per asset (`trackContentImpression`)                               |
| Interaction | a click on the media of the player (`trackContentInteraction`)                                          |
| Media       | the videos, by MediaAnalytics, when `MATOMO_MEDIA_PLUGIN_ENABLED` is on (instead of the impression)     |
| Event       | `Download`, action `direct` or `email`, named after the file or `title (archive)`                       |

The content piece (and the media resource) of an asset is its `trackingId`, else the id of the
Databox asset it comes from (`assetId`), else its Expose id: the metrics of an asset in Databox
include its views in Expose.

**Cookies**: unless `MATOMO_COOKIE_CONSENT=0` or `MATOMO_DISABLE_COOKIES=1`, a banner asks the
visitor before Matomo sets its cookies, and the "Cookies" item of the settings menu reopens it.
An embedded page never shows the banner.

## View logs

Besides Matomo, the client reports the views to the API, which pushes them to the Report API
(`REPORT_API_URL`), next to the [download logs](./downloads.md#logs):

| Request                          | Action             | Sent                                                                     |
| -------------------------------- | ------------------ | ------------------------------------------------------------------------ |
| `POST /logs/publication-view/{id}` | `publication_view` | once per visit, when the content of the publication is shown           |
| `POST /logs/asset-view/{id}`     | `asset_view`       | for each asset displayed for a second (viewer, gallery, embed), with the Databox `assetId` as `trackingId` |

They require the right to read the publication (its details, for an asset) and are limited to 60
per minute by client IP (`429` beyond).

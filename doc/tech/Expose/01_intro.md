---
title: Intro
---

# Expose

Expose publishes assets on the Internet: web galleries, download pages and embeddable players,
open to anyone or protected by a password or an account. The publications are filled from
Databox (a basket or a saved search automation); Expose shows them and lets their editors
change their settings.

For the end users, see the [Expose user guide](../../user/Expose/expose-documentation.md).

## Components

| Component          | Role                                                                                                                                                  |
| ------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| `expose-api-php`   | Symfony API (API Platform 5): publications, profiles, assets, downloads, share links. EasyAdmin back office under `/admin`, MCP server under `/mcp`. |
| `expose-api-nginx` | Front of the API, with a proxy cache of the public reads (see [Access](./access.md#http-cache)).                                                      |
| `expose-worker`    | Messenger consumer of the `p1` queue: archives, download emails, file checks, deletion of the stored files.                                          |
| `expose-scheduler` | Single replica consuming the `main` schedule (`src/Schedule/MainSchedule.php`): nightly purge of the abandoned multipart uploads.                     |
| `expose-client`    | Next.js client (`expose/client`). The server renders the page metadata only; the page itself is loaded by the browser, with TanStack Query.           |

The files (originals, renditions, archives) are stored in the S3 bucket of Expose and served
through signed URLs, optionally through CloudFront.

## Concepts

- **Publication**: a set of assets with a title, a description, a slug and settings (visibility,
  layout, downloads, security, terms). See [Publications](./publications.md).
- **Sub-publication**: a publication may have a parent. A sub-publication is hidden with its
  ancestors and inherits the security method of the closest ancestor defining one.
- **Profile**: a set of settings shared by the publications using it; a setting left unset on
  the publication is the one of its profile.
- **Asset**: a file of a publication (the original), with its title, description, position and
  sub-definitions. It keeps the id of the Databox asset it comes from (`assetId`).
- **Sub-definition**: a rendition of an asset. `thumbnail`, `preview` and `poster` are used by the
  client to display it; every sub-definition can be downloaded.
- **Share link**: a token opening one publication to whoever holds it. See
  [Share links](./share-links.md).

## Public URLs

| URL                                       | Page                                                                                 |
| ----------------------------------------- | ------------------------------------------------------------------------------------ |
| `/`                                       | The publications listed publicly (404 with `EXPOSE_DISABLE_INDEX_PAGE`)              |
| `/{slug or id}`                           | A publication, by slug or by id                                                      |
| `/{publication}/{asset}`                  | The publication with one of its assets opened (id or slug of the asset)              |
| `/s/{token}`                              | Permalink of a share link                                                            |
| `/embed/{assetId}`                        | Player of one asset, for iframes (Expose asset id, see [Embedding](./iframe.md))     |

The first segments `auth`, `embed`, `profiles`, `publications`, `api` and `s` are reserved by the
client (`expose/client/src/lib/routes.ts`): a publication whose slug is one of them cannot be
opened by its slug. The API does not refuse these slugs. The other routes of the client are
listed in `expose/client/README.md` (section "Routes").

## Loading a publication page

1. The Next.js server reads the publication from the API anonymously
   (`GET /publications/{slug or id}`), only to build the Open Graph metadata of the page (see
   [SEO and analytics](./seo-analytics.md)).
2. The browser loads the client, then asks the API for the publication with what the visitor
   gave: the passwords in `X-Passwords`, the share link tokens in `X-Share-Tokens`, the access
   token of a signed-in user in `Authorization: Bearer`.
3. Without access to the details, the API answers a reduced publication (`authorized: false`,
   `authorizationError`, `securityContainerId`, `securityMethod`): the client shows the password
   form or the sign-in screen, then asks again. See [Access](./access.md).
4. Once authorized, the client shows the terms of use if any, then the layout of the publication.
   Opening an asset only changes the URL: the layout stays in place.

An unknown publication answers 404. A publication that is not visible (disabled, not started,
expired, or below a hidden ancestor) is refused to whoever is not its editor nor holds a share
link: 401 for an anonymous visitor, 403 for a signed-in user.

## Documentation

- [Setup](./02_setup.md): services, environment variables, back office.
- [Publications](./publications.md): settings, profiles and inheritance, assets, publishing from
  Databox.
- [Access](./access.md): permissions, passwords, authentication, terms, CORS, HTTP cache.
- [Downloads](./downloads.md): download modes, signed URLs, archive, downloads by email.
- [Embedding (iframe)](./iframe.md).
- [SEO and analytics](./seo-analytics.md): indexing, link previews, Matomo, view logs.
- [Share links](./share-links.md).
- [MCP server](./mcp.md).

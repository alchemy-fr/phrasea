---
title: Publications
---

# Publications

Entities: `expose/api/src/Entity` (`Publication`, `PublicationConfig`, `TermsConfig`,
`PublicationProfile`, `Asset`, `SubDefinition`).

## Publications and sub-publications

A publication has a `title` (required), a `description` (HTML), `translations`, a `slug`, a
`date` (shown under the title, read-only through the API), a `cover`, a `profile`, a `parent` and
its `config` (the settings below).

A publication with a `parent` is a sub-publication. The tree has no depth limit, but a
circular parenthood is refused, and the public list only goes through 5 levels of ancestors.
A sub-publication:

- is hidden whenever one of its ancestors is hidden (see [Visibility](#visibility-and-dates));
- is protected by the closest ancestor defining a security method, unless it defines one itself
  (see [Access](./access.md#security-methods));
- is not opened by the share links of its ancestors.

The client shows the parent, the publication and its children in a drawer, and the root and the
parent in a breadcrumb. A child is only listed to the readers allowed to read it.

Deleting a publication deletes its assets and its sub-publications.

## Profiles and inheritance

A profile (`/publication-profiles`) has a `name` and a `config` of the same shape as the one of a
publication. Every setting of `PublicationConfig` is nullable: `null` on the publication means
"the value of the profile". Without a profile, or when the profile leaves it unset too, the
default below applies.

| Setting                         | Meaning                                                                                  | Default          | Inheritance                                                                                    |
| ------------------------------- | ---------------------------------------------------------------------------------------- | ---------------- | ---------------------------------------------------------------------------------------------- |
| `enabled`                       | The publication can be seen                                                              | `false`          | publication, else profile                                                                      |
| `publiclyListed`                | Listed on the home page of the client                                                    | `false`          | publication, else profile                                                                      |
| `beginsAt`, `expiresAt`         | Visible from / until                                                                     | no limit         | publication, else profile, each date on its own                                                |
| `layout`                        | `grid`, `gallery`, `download`, `mapbox`                                                  | `grid`           | publication, else profile                                                                      |
| `theme`                         | `light`, `dark`                                                                          | none             | publication, else profile (not applied by the client)                                          |
| `css`                           | Stylesheet of the publication                                                            | none             | both: the CSS of the publication, then the one of the profile                                  |
| `downloadEnabled`               | The files can be downloaded                                                              | `false`          | publication, else profile                                                                      |
| `downloadViaEmail`              | The download links are sent by email                                                     | `false`          | publication, else profile                                                                      |
| `includeDownloadTermsInArchive` | The file at the URL of the download terms is added to the archive                        | `false`          | publication, else profile                                                                      |
| `terms`                         | Terms of use: `enabled`, `text` (HTML), `url`                                            | disabled         | the whole terms of the side whose `enabled` is set (`true` or `false`), the publication first |
| `downloadTerms`                 | Download terms: `enabled`, `text`, `url`                                                 | disabled         | same as `terms`                                                                                |
| `securityMethod`                | `password`, `authentication`, or none                                                    | none             | publication, else profile; then the closest ancestor (see [Access](./access.md))               |
| `securityOptions.password`      | Password of the `password` method, write-only (stored hashed, read as `hasPassword`)     | none             | the options of the publication when it has any, else the ones of the profile                   |
| `layoutOptions`                 | `logoUrl` (logo above the title), `displayMap`, `displayMapPins`                         | none             | merged key by key, the publication winning                                                     |
| `mapOptions`                    | `lat`, `lng`, `zoom`, `mapLayout`                                                        | none             | merged key by key, the publication winning                                                     |

In the client, a publication with a profile shows each inheritable setting with the value of
the profile ("From the profile:") until its "Override settings" switch is turned on. The "Public"
access overrides a protected profile (an empty `securityMethod`); such a publication is its own
security container, so it does not inherit the protection of its ancestors either.

The profile of a publication can only be changed by its operators, to a profile they can read. A
profile used by publications cannot be deleted from the client.

`displayMap`, `displayMapPins` and `mapOptions` are not used by the client.

## Layouts and themes

The values offered by the back office are set in `expose/api/config/services.yaml`
(`LayoutChoiceType`, `ThemeChoiceType`); the API itself only limits their length.

| Layout     | Client                                                                                                   |
| ---------- | -------------------------------------------------------------------------------------------------------- |
| `grid`     | Justified mosaic of thumbnails; a click opens the asset in a full screen viewer                          |
| `gallery`  | One asset at a time in a large player, previous / next buttons, strip of thumbnails                      |
| `download` | A list of files: each asset with a button for its original and one for each sub-definition              |
| `mapbox`   | **Not available**: displayed as `grid`. The edit form of the client shows it disabled, marked "Soon"    |

The `theme` of a publication (`light`, `dark`) is stored and returned by the API, but the client
does not apply it: the visitor picks the theme in the settings menu (light, dark or system, the
presets and the organisation theme of the stack, see `expose/client/README.md`).

## Visibility and dates

A publication is **visible** when it is enabled, `beginsAt` is past (or unset), `expiresAt` is
not reached (or unset), and all its ancestors are visible (`Publication::isVisible()`).

- A visible publication can be read by anyone, subject to its security method.
- A hidden one is only read by its editors and by the holders of one of its share links.
- The home page of the client lists the root publications that are publicly listed and visible,
  plus, for a signed-in user, the ones they own or can edit.

The client marks a disabled publication for its editors ("This publication is currently
disabled. Only administrators can see it.").

## Slugs

The slug replaces the id in the URL of the publication: `/my-gallery` instead of
`/0d3b…`. It is optional, unique, at most 100 characters, made of letters, digits, `_` and `-`
(the check is case-insensitive: capital letters are accepted, and the URLs are then case
sensitive). `GET /publications/slug-availability/{slug}` tells whether a slug is free.

The client cannot open a publication whose slug is one of its reserved segments (`auth`, `embed`,
`profiles`, `publications`, `api`, `s`); the API does not refuse them.

An asset may have a slug too (at most 255 characters), to open it at `/{publication}/{asset}`.
The uniqueness of the asset slugs within a publication is not checked: the first match is used.

## Cover

The `cover` is an asset of the publication (any other asset is refused). Without a cover, the
first asset of the publication is used. The client uses its thumbnail in the cards of the home
page and in the link previews. Editors pick it from the edit form ("Set cover image").

## Translations

`translations` holds the translated fields of a publication or an asset, by field then by
locale:

```json
{
  "title": {"fr": "Ma galerie", "de": "Meine Galerie"},
  "description": {"fr": "<p>…</p>"}
}
```

The client shows the translation closest to the language of the interface (then to the
languages of the browser), the field itself otherwise. Databox sends the title and the
description of each asset in the locales of its workspace. The translations of a publication are
edited in the back office or through the API only.

## CSS

The `css` of the publication and of its profile are concatenated and served by
`GET /publications/{id}/style.{hash}.css`, the hash changing with the content (`cssLink` in the
publication). The client loads it once the visitor is authorized. For a publication hidden from
anonymous visitors, `cssLink` is a signed URL (`?jwt=`), since a stylesheet link sends no
credentials. The CSS is edited in the back office or through the API only.

## Assets and sub-definitions

An asset holds:

- the original file (`originalName`, `mimeType`, `size`) and its `title`, `description` (HTML),
  `translations`, `position`, `lat` / `lng` / `altitude`, `slug`;
- `assetId`: the id of the Databox asset it comes from, and `trackingId`, used by the analytics
  (see [SEO and analytics](./seo-analytics.md));
- `clientAnnotations`: free text for the publisher (Databox stores there, as JSON, what
  identifies the published asset), returned to the editors only, in the assets of the
  publication;
- its sub-definitions (`subDefinitions`), each with a `name`, a type and a file.

The client displays the `preview` sub-definition (else the original, when the reader may
download it), uses `thumbnail` (else `poster`, else `preview`) for the thumbnails and `poster` as
the poster of a video. The other names are free; every sub-definition is offered for download by
the `download` layout.

Assets and sub-definitions are created with a file in one of three modes (`UploadRequestResolver`):

| Mode        | Request                                                                                   |
| ----------- | ----------------------------------------------------------------------------------------- |
| `multipart` | JSON `multipart: {uploadId, parts}`: a multipart upload completed on S3 beforehand         |
| `file`      | `multipart/form-data` with a `file` field                                                 |
| `upload`    | JSON `upload: {name, type, size}`: the response carries `uploadURL`, a signed S3 `PUT` URL |

The type and extension must be allowed by `EXPOSE_ALLOWED_FILE_TYPES`, and the content is
checked (see [File types](../Storage/03_file_types.md)).

### Subtitles (WebVTT)

`webVTT` is a list of `{id, label, locale, content, kind}` (`id` of 10 to 36 characters, `label`
of 2 to 100, `kind` `subtitles` by default), written on the asset through the API or the back
office; it is never returned. Every reader gets `webVTTLinks` instead: the URL of each
track, `GET /publications/{assetId}/vtt/{vttId}.vtt` (the first segment is the id of the asset),
signed with `?jwt=` unless the publication is visible and public. The video player of the client
offers the tracks, the one matching the language of the interface first. Databox does not send
any subtitles.

## Endpoints

| Method                 | Path                                                         | Notes                                                                                                         |
| ---------------------- | ------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------- |
| `GET`                  | `/publications`                                              | Root publications only, unless `flatten=true` or `parentId`. Filters: `profileId`, `mine`, `editable`, `expired`, `empty`, `disabled`, `title`, `description`, `query`; `order[title]`, `order[createdAt]` |
| `GET`                  | `/publications/{id or slug}`                                 | Reduced answer without access to the details, see [Access](./access.md#reduced-response)                     |
| `POST`                 | `/publications`                                              | Right to create publications                                                                                  |
| `PATCH`                | `/publications/{id}`                                         | `application/merge-patch+json`; editors                                                                       |
| `DELETE`               | `/publications/{id}`                                         | With its assets and sub-publications                                                                          |
| `POST`                 | `/publications/{id}/sort-assets`                             | `{"order": [asset ids]}`, the assets in their new order                                                       |
| `GET`                  | `/publications/slug-availability/{slug}`                     | `true` when free; signed-in users                                                                             |
| `GET`, `POST`          | `/publications/{id}/assets`                                  | Assets of a publication; creation in one of the modes above                                                   |
| `POST`                 | `/assets`                                                    | Same, with the publication in the body                                                                        |
| `GET`, `PATCH`, `DELETE` | `/assets/{id}`                                             | The publication of the asset decides what is returned                                                         |
| `GET`                  | `/publications/{publicationSlug}/assets/{assetSlug}`         | An asset by slugs                                                                                             |
| `DELETE`               | `/assets/delete-by-asset-id/{assetId}`                       | Every Expose asset of a Databox asset that the user may delete                                                |
| `GET`, `POST`          | `/assets/{id}/sub-definitions`, `/sub-definitions`           | Sub-definitions                                                                                               |
| `GET`, `POST`, `PATCH`, `DELETE` | `/publication-profiles`, `/publication-profiles/{id}` | Profiles                                                                                                      |

The download, archive and log endpoints are described in [Downloads](./downloads.md) and
[SEO and analytics](./seo-analytics.md), the share links in [Share links](./share-links.md).

## Publishing from Databox

Databox publishes through its Expose integration (`phrasea.expose`,
`databox/api/src/Integration/Phrasea/Expose/`), with the Expose access of the user, who
authorizes it once (OAuth). Its configuration and its Keycloak client are described in
[Databox integrations](../Databox/integration/01_integrations.md#expose-integration-phraseaexpose).

### Basket synchronization

1. From the "Integrations" tab of a basket, the user creates a publication (title, slug,
   description, parent and profile ids, enabled, "publish the whole files"): action `sync`.
2. Databox creates the publication in Expose, keeps its id, and synchronizes the basket in the
   background (`ExposeSynchronizer`), again on every change of the basket.
3. Each asset is published with its source file, then its `preview` and `thumbnail` renditions
   as sub-definitions. Its title and an HTML description built from its attributes are sent,
   with their translations. A file is sent again only when it changed.
4. The assets that left the basket are deleted from the publication. The assets published by
   other means (another basket, an automation) are left untouched.

Not published, and listed as such on the basket: the assets the user cannot read, deleted ones,
the ones pending analysis or in quarantine, the ones without a source file, and the ones Expose
refuses (type, size…).

`force-sync` runs the synchronization again; `stop` ends it, and deletes the publication from
Expose when asked to.

With `ignoreRoi` ("publish the whole files"), set when the publication is created, the files are
not cut to the [regions of interest](../Databox/roi.md#whole-files) and extracts of the assets.

### Automation `expose_publish`

The saved search automations of Databox have an `expose_publish` action ("Publish to Expose",
`databox/api/src/Automation/Action/ExposePublishAction.php`), configured with:

- `integrationId`: the Expose integration;
- `publicationId`: an existing publication, or one created from the form; the owner of the saved
  search must be an editor of it, the assets being published with their Expose access;
- `ignoreRoi`: publish the whole files;
- `removeMissing` ("Mirror the results"): at the end of a run, remove from the publication the
  assets this automation published that are no longer in the results. Only with the scope "all",
  and not when the run stopped at its limit of assets or failed some assets.

Each run only sends what changed since the previous one, and only touches the assets published
by its own automation. The assets the owner cannot read and the ones without a file are skipped.

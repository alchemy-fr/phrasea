---
title: Downloads
---

# Downloads

The download settings of a publication (`downloadEnabled`, `downloadViaEmail`, `downloadTerms`,
`includeDownloadTermsInArchive`, see [Publications](./publications.md#profiles-and-inheritance))
are enforced by the API: `src/Security/PublicationDownloadPolicy.php` and
`src/Security/DownloadMode.php`.

## Download modes

The mode is resolved for each reader and publication, in this order:

| Mode     | When                                                                        | What the reader gets                                                                                              |
| -------- | --------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `Editor` | the reader may edit the publication                                         | every file, whatever the settings; with `downloadViaEmail`, the client still asks for an email address           |
| `None`   | downloads disabled, or the reader cannot read the details of the publication | no download URL, no URL of the original files                                                                   |
| `Email`  | `downloadViaEmail`                                                          | a link sent by email, after the download terms if any                                                             |
| `Terms`  | download terms enabled                                                      | a short-lived URL given in exchange of the acceptance of the terms                                                |
| `Direct` | otherwise                                                                   | signed download URLs in the publication and its assets                                                            |

The URL of the original file of an asset (`url`), and of its sub-definitions, is only given in
the modes `Direct` and `Editor`. In the other modes, the client displays the `preview`
sub-definition; an asset without one has nothing to display.

Each asset, sub-definition and the publication itself (`archiveDownloadUrl`) carry a
`downloadUrl` pointing to the right endpoint: the download itself, or the email request when the
publication is downloaded via email.

## Endpoints

| Method | Path                                                     | Body                                     |
| ------ | -------------------------------------------------------- | ---------------------------------------- |
| `GET`  | `/publications/{publicationId}/assets/{assetId}/download` | `?jwt=` required, except for editors     |
| `GET`  | `/publications/{publicationId}/subdef/{subDefId}/download` | same                                    |
| `GET`  | `/publications/{id}/archive`                             | same, see [Archive](#archive)            |
| `POST` | `/publications/{id}/download-url`                        | `assetId`, `subDefId` or `"archive": true`, and `acceptTerms` |
| `POST` | `/publications/{publicationId}/assets/{assetId}/download-request` | `email`, `acceptTerms`          |
| `POST` | `/publications/{publicationId}/subdef/{subDefId}/download-request` | `email`, `acceptTerms`         |
| `POST` | `/publications/{id}/archive/download-request`            | `email`, `acceptTerms`                   |

The download endpoints redirect to the file, served by the storage (S3 or CloudFront) with a
signed URL as an attachment.

## Signed URLs

The API signs its own URLs with a JWT in the `?jwt=` query parameter (`UriJwtManager` of
`auth-bundle`, HMAC with `APP_SECRET`). The token covers the whole URL, and gives access to it
without any other credential: no password, no share link, no access token. It is used for:

- the download URLs, the archive and the email requests;
- the stylesheet of a publication hidden from anonymous visitors (see
  [Publications](./publications.md#css));
- the WebVTT files of a protected or hidden publication.

The download URLs carry a `dl` claim saying how they were given:

| Claim    | Given                                                          | Lifetime                   |
| -------- | -------------------------------------------------------------- | -------------------------- |
| `direct` | in the publication, in the mode `Direct`                       | `URI_JWT_TTL` (24 hours)   |
| `direct` | by `POST /publications/{id}/download-url`, in the mode `Terms` | 300 seconds                |
| `email`  | in the links sent by email                                     | 3 days                     |

A direct download requires a `dl` claim: `email` only in the mode `Email`, `direct` or `email`
otherwise. A URL signed without claim (the ones of the mode `Terms`, before acceptance) does not
download anything.

A signed URL stays valid until it expires, whatever happens to the publication meanwhile
(settings changed, share link revoked). The file it redirects to is signed by the storage for
`EXPOSE_REQUEST_SIGNATURE_TTL` (S3) or `EXPOSE_CLOUD_FRONT_TTL` (CloudFront).

## Archive

The archive is a ZIP of the original files of the publication, built on demand by the workers
(`src/Archive/PublicationArchiver.php`) and kept in the bucket under
`archives/{publicationId}/{hash}/{title}.zip`. The hash covers the title, the files and the
inclusion of the download terms: any change makes the next request build a new archive, and the
previous one is deleted.

1. `GET /publications/{id}/archive` answers `202 {"status": "preparing"}` with `Retry-After: 5`
   while the archive is not up to date, and asks the workers to build it (once for every reader
   asking meanwhile).
2. Once it is ready, the same request redirects to the file, or answers
   `200 {"status": "ready", "url": "…"}` to a client asking for JSON.

The client offers "Download Archive" when downloads are enabled and the publication has more than
one asset. It asks again from every 2 seconds to every 15 seconds, for 3 minutes at most, then
offers to receive the link by email.

Two files with the same name are renamed `name (2).ext`. With `includeDownloadTermsInArchive`, the
file at the URL of the download terms is fetched (public addresses only, 10 seconds at most) and
added to the archive; when it cannot be fetched, the archive is built without it.

## Downloads by email

With `downloadViaEmail`, the download buttons ask for an email address. The API checks the
address, then the worker sends the link (topics `download:link` and `download:archive_link` of
the notifier, templates in `expose/api/templates/notifications/download/`, in the language of the
request):

- the link of a file is valid 3 days;
- for the archive, the worker builds it first, then sends a link valid 3 days.

The requests are limited to **10 per hour by client IP** and **5 per hour by recipient**
(`429` beyond). No email is sent when `NOTIFICATIONS_ENABLED` is off.

## Download terms

With `downloadTerms.enabled`, the reader accepts the download terms (their text, or a link to
their URL) before the first download. The client keeps the acceptance in the
`terms_pd_{publicationId}` cookie for a year; the API requires `"acceptTerms": true` in
`POST …/download-url` and in the email requests, and answers `400` without it.

## Logs

The downloads are pushed to the Report API (`src/Report/ExposeLogActionInterface.php`), with the
publication and asset titles:

| Action                         | When                                                       |
| ------------------------------ | ---------------------------------------------------------- |
| `asset_download`               | download of an asset or of a sub-definition                |
| `pub_archive_download`         | download of the archive, once it is ready                  |
| `asset_download_request`       | a link to a file asked by email (with the recipient)       |
| `pub_archive_download_request` | a link to the archive asked by email (with the recipient)  |
| `download_terms_accepted`      | a download URL given after the acceptance of the terms     |

See also [SEO and analytics](./seo-analytics.md#view-logs) for the view logs.

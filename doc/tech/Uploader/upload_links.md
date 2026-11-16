# Upload Links

An upload link lets someone without an account (a photographer, a partner…) send files to one target. It is a secret URL, `https://<uploader client>/l/<token>`, handed out by the user who created it: whoever holds it can upload, within the limits of the link.

## Who manages them

Creating links needs the `upload_link` permission: an ACE on the whole `upload_link` object type, granted in the admin (Permissions > Upload link permissions), or `ROLE_ADMIN`. `GET /me` tells the clients with `permissions.upload_link`.

The links are managed in the Uploader client (Admin > Upload links): a user lists, edits, disables and deletes the links they created; an admin manages every link. Creating a link on a target also needs the right to upload to it (`allowedGroups`). A creator who lost the `upload_link` permission can still disable their links (`PATCH` with `{"enabled": false}` only), nothing else.

API: `GET|POST /upload-links` (filter `target`), `GET|PATCH|DELETE /upload-links/{id}`, `POST /upload-links/{id}/regenerate-token`. Written properties: `target` (on creation only), `name`, `enabled`, `expiresAt`, `maxAssets`, `maxTotalSize` (bytes). The link answers its `token` (stored as is, so that its creator can copy the link again), its counters (`usedAssets`, `usedSize`), `lastCommitAt` and a computed `status`: `active`, `disabled`, `expired` or `exhausted`.

`POST /upload-links/{id}/regenerate-token` (same right as editing the link) gives it a new random token: the URL handed out so far stops working at once, what was sent with it stays. The client offers it as "New URL".

## Bound to its creator

A link acts on behalf of its creator, and only while they could still create it. When the creator creates, edits or regenerates the link, it records their groups and admin role (`creatorGroups`, `creatorRoles`, not exposed). Each request made with the link then checks, without calling Keycloak:

1. the creator still has the `upload_link` permission: the ACEs of their id and of the recorded groups, read now (or the recorded admin role);
2. the target still lets one of the recorded groups upload (`allowedGroups` empty: everybody).

Otherwise the request is answered with a 403 `{"code": "upload_link_revoked"}`. Removing the ACE of the creator (or of their group), or restricting the target to other groups, stops their links at once; a change of the creator's groups in Keycloak is only seen once they save the link again.

## Limits

- `expiresAt` is required: past it, the link no longer authenticates. An enabled link cannot expire later than `UPLOADER_UPLOAD_LINK_MAX_TTL` days from now (90 by default, `UPLOAD_LINK_MAX_TTL` in the API container, given to the clients by `GET /me` as `upload_link_max_ttl`); a disabled link is not checked, so that it can always be disabled. The client proposes a week by default.

The other limits are optional (null: no limit, only the global limits of the uploader):

- `maxAssets` and `maxTotalSize` count every file sent through the link, over all its commits. A file is counted when it is uploaded (`POST /assets`), in one SQL statement that refuses it when it would go over a limit: two uploads at the same time cannot go over together. A refused file is deleted from the storage and answered with a 400 of type `/errors/upload_link_quota_exceeded`. Removing a file that was not committed gives its share back; a committed file stays counted.
- `enabled: false` revokes the link until it is enabled again; deleting it revokes it for good (the commits and files keep their data).

The global limits per commit (`max_upload_commit_size`, `max_upload_file_count`) still apply. The client shows the lowest of both ("files left on this link").

## Authentication

The calls of the link page send `Authorization: UploadLink <token>`. An unknown, disabled or expired link is answered with a 401 holding a `code`: `upload_link_invalid`, `upload_link_disabled` or `upload_link_expired`, which the page turns into a message, like the 403 `upload_link_revoked` (see [Bound to its creator](#bound-to-its-creator)).

Along with the token, the page sends `X-Upload-Session`: a UUID drawn by the browser tab and kept in its `sessionStorage`. A malformed value is answered with a 400.

A link only opens the upload flow of its target (`UploadLinkRequestListener`): `GET /upload-links/current`, the target, its form and the options of its fields, `POST /form/validate`, the multipart uploads (`/uploads…`), `POST /assets`, `DELETE /assets/{id}` and `POST /commit`. Any other endpoint answers 403 (`/me`, the target list, `/downloads`, the target data, the Databox proxy, the management of the links…). The multipart uploads it starts belong to `upload-link:<id>:<session>` (`upload-link:<id>` without the header): another holder of the link cannot read, cancel or complete them. The files and the commits stay bound to the link, whichever session sent them: a commit can gather files sent from several sessions.

`GET /upload-links/current` gives the page the name of the link, its target, its limits and counters, never the token nor the creator.

The token never leaves the page for a third party: the link page is neither indexed nor sent as a referrer (`noindex`, `no-referrer`), and the URLs given to Matomo (page views) and Sentry (events, breadcrumbs) go through `src/lib/privacy/redactUrl.ts`, which turns `/l/<token>` into `/l/[token]` (`createUrlRedactor()` of `@alchemy/next-core/privacy/redactUrl`); the `Authorization` header is dropped from the Sentry events.

## Attribution

What is sent through a link belongs to its creator: the files and the commit get the `userId` of the creator (the publisher Databox receives) and keep a reference to the link. Nobody is behind the link to be notified: the `__notify` field is hidden on the link page and the commit is never flagged for a notification. The report logs record the commits with the username `upload-link:<name or id>`.

The entity and tag fields of a form read their values from Databox with the token of a user: they cannot be filled through a link.

The `data` of a file sent through a link is ignored: it cannot aim at an existing asset of the creator (`targetAsset`/`targetRendition`, which replace a rendition in Databox). Other uploads keep only these two keys. The asset read by Databox carries `fromUploadLink`, and Databox refuses a substitution coming from a link.

## Reusing the previous information

The browser remembers the data entered in the form of the last commit made with the link: what was entered, without the `__*` keys, in its `localStorage` under a key per link (`upl.link_form.<link id>`). A link is shared: the API keeps nothing of it, so that its other holders never see what was entered. On the form step of the next upload, "Reuse my previous information" fills the form with it (the fields removed from the form since are left out) and "Forget my previous information" erases it.

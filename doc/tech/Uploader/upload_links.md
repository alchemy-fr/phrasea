# Upload Links

An upload link lets someone without an account (a photographer, a partner…) send files to one target. It is a secret URL, `https://<uploader client>/l/<token>`, handed out by the user who created it: whoever holds it can upload, within the limits of the link.

## Who manages them

Creating links needs the `upload_link` permission: an ACE on the whole `upload_link` object type, granted in the admin (Permissions > Upload link permissions), or `ROLE_ADMIN`. `GET /me` tells the clients with `permissions.upload_link`.

The links are managed in the Uploader client (Admin > Upload links): a user lists, edits, disables and deletes the links they created; an admin manages every link. Creating a link on a target also needs the right to upload to it (`allowedGroups`).

API: `GET|POST /upload-links` (filter `target`), `GET|PATCH|DELETE /upload-links/{id}`. Written properties: `target` (on creation only), `name`, `enabled`, `expiresAt`, `maxAssets`, `maxTotalSize` (bytes). The link answers its `token` (stored as is, so that its creator can copy the link again), its counters (`usedAssets`, `usedSize`), `lastCommitAt` and a computed `status`: `active`, `disabled`, `expired` or `exhausted`.

## Limits

Every limit is optional (null: no limit, only the global limits of the uploader):

- `expiresAt`: past it, the link no longer authenticates. The client proposes a week by default.
- `maxAssets` and `maxTotalSize` count every file sent through the link, over all its commits. A file is counted when it is uploaded (`POST /assets`), in one SQL statement that refuses it when it would go over a limit: two uploads at the same time cannot go over together. A refused file is deleted from the storage and answered with a 400 of type `/errors/upload_link_quota_exceeded`. Removing a file that was not committed gives its share back; a committed file stays counted.
- `enabled: false` revokes the link until it is enabled again; deleting it revokes it for good (the commits and files keep their data).

The global limits per commit (`max_upload_commit_size`, `max_upload_file_count`) still apply. The client shows the lowest of both ("files left on this link").

## Authentication

The calls of the link page send `Authorization: UploadLink <token>`. An unknown, disabled or expired link is answered with a 401 holding a `code`: `upload_link_invalid`, `upload_link_disabled` or `upload_link_expired`, which the page turns into a message.

A link only opens the upload flow of its target (`UploadLinkRequestListener`): `GET /upload-links/current`, the target, its form and the options of its fields, `POST /form/validate`, the multipart uploads (`/uploads…`), `POST /assets`, `DELETE /assets/{id}` and `POST /commit`. Any other endpoint answers 403 (`/me`, the target list, `/downloads`, the target data, the Databox proxy, the management of the links…). The multipart uploads it starts belong to `upload-link:<id>`.

`GET /upload-links/current` gives the page the name of the link, its target, its limits and counters and `lastFormData`, never the token nor the creator.

## Attribution

What is sent through a link belongs to its creator: the files and the commit get the `userId` of the creator (the publisher Databox receives) and keep a reference to the link. Nobody is behind the link to be notified: the `__notify` field is hidden on the link page and the commit is never flagged for a notification. The report logs record the commits with the username `upload-link:<name or id>`.

The entity and tag fields of a form read their values from Databox with the token of a user: they cannot be filled through a link.

## Reusing the previous information

The link remembers the data entered in the form of its last commit (`lastFormData`: what was entered, before the target data is merged and without the `__*` keys). On the form step of the next upload, "Reuse my previous information" fills the form with it; the fields removed from the form since are left out.

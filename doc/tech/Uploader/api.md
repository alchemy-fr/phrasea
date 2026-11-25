---
title: API reference
---

# Uploader API

The Uploader API is an API Platform application, at `UPLOADER_API_URL`. Its OpenAPI documentation is served at the root of the API. This page lists the endpoints, who may call them and their notable answers. The sequence of an upload is described in [Request Flow](./request_flow.md).

## Authentication

Every endpoint needs a credential, except the health check and the logo of the emails. Four schemes:

- **JWT**: `Authorization: Bearer <access token>`, a Keycloak access token of a user, or of an OAuth client (service account). The roles and groups of the token decide the rights. The admin (`/admin`) logs the user in through Keycloak and needs `ROLE_ADMIN`.
- **`AssetToken`**: `Authorization: AssetToken <token>`, the `token` of a commit, sent to the destination in the notification of the commit. It reads and acknowledges that commit and its files, nothing else, and cannot start a multipart upload (`403` on `/uploads`).
- **`UploadLink`**: `Authorization: UploadLink <token>`, the token of an upload link, along with `X-Upload-Session` (a UUID drawn by the browser tab). It opens the upload flow of the target of the link only. See [Upload Links](./upload_links.md#authentication).
- **MCP**: `/mcp` takes a user access token, see [MCP server](./mcp.md).

An invalid credential answers `401`.

### Scope

`commit:list` is a Keycloak client scope, created by the configurator. It lets a token list and read every commit (`GET /commits`, `GET /commits/{id}`): it is meant for a destination in pull mode, through a Keycloak client of its own. Admins do not need it.

### Permissions

- **Admin** (`ROLE_ADMIN`): every right. It manages the targets.
- **Global ACEs**, granted in the admin (Permissions menu) to users or groups, on every object of the type: `form_schema` (edit the forms), `target_params` (edit the target data), `upload_link` (create upload links).
- **`allowedGroups`** of a target: the IDs of the Keycloak groups allowed to use it; empty allows every user. Uploading also needs the target to be enabled.

`GET /me` gives the user and these rights:

```json
{
    "user_id": "…",
    "email": "…",
    "username": "…",
    "permissions": {
        "form_schema": true,
        "target_data": false,
        "upload_link": true,
        "target": false
    },
    "upload_link_max_ttl": 90
}
```

`permissions.target` is `ROLE_ADMIN`; `upload_link_max_ttl` is in days.

### Rate limits

| What | Limit | Beyond |
|---|---|---|
| `POST /uploads`, `POST /uploads/{id}/parts` | `MULTIPART_UPLOAD_RATE_LIMIT` per minute and per user (60) | `429` with `Retry-After` |
| Multipart uploads in progress | `MULTIPART_UPLOAD_MAX_PENDING` per user (20) | `429` without `Retry-After` |
| `POST /downloads` | `DOWNLOAD_RATE_LIMIT` per hour and per user (30) | `429` with `Retry-After` |

The holders of an upload link share the limits of its creator.

## Uploads

The multipart uploads come from the storage bundle, shared with Databox and Expose. The file is sent straight to S3 with presigned URLs; the API never receives its content. The protocol and its limits: see [S3 Storage](../Storage/01_s3.md#multipart-upload-settings).

| Endpoint | Access | Notes |
|---|---|---|
| `POST /uploads` | any user, upload link | creates the upload: `chunkSize` and the URLs of the first parts; `400` above `uploader.max_upload_file_size` |
| `POST /uploads/{id}/parts`, body `{"from": 1}` | owner of the upload | fresh part URLs, to resume an upload |
| `GET /uploads/{id}`, `DELETE /uploads/{id}` | owner of the upload | read, cancel |
| `GET /uploads` | admin | |

## Assets

An asset is one uploaded file, created once its upload is complete, then grouped with others in a commit.

| Endpoint | Access | Notes |
|---|---|---|
| `POST /assets` | right to upload to the target, upload link | see below |
| `GET /assets/{id}` | `AssetToken` of its commit, admin | the file, with a signed `url` |
| `DELETE /assets/{id}` | its sender, while it is not committed | gives its share back to an upload link |
| `POST /assets/{id}/ack` | `AssetToken` of its commit, admin | acknowledges the file; answers `true` |
| `GET /assets` | admin | filter `acknowledged` |

`POST /assets` takes the target (`targetId`, or `targetSlug`) and, either:

- in JSON, `multipart`: the `uploadId` and the `parts` (`ETag`, `PartNumber`) of a multipart upload, which the API completes;
- in `multipart/form-data`, `file`: the file itself, within `UPLOAD_MAX_FILE_SIZE`.

An optional `data` object goes with the file. Only two keys are kept, read by Databox: `targetAsset` and `targetRendition` (the file replaces that rendition of that asset). A file sent through an upload link keeps no `data`.

The file is checked against `ALLOWED_FILE_TYPES` and its content (see [File types](./configuration.md#file-types)): a refused file answers `400` and is not kept. Its name is cut to 255 bytes, its extension kept. A target that does not exist answers `400`, a target the user may not upload to `403`. Through an upload link, a file over its limits answers `400` of type `/errors/upload_link_quota_exceeded`.

`GET /assets/{id}` answers `originalName`, `mimeType`, `size`, `url`, `data`, `formData` and `formLocale` (of the commit), `acknowledged`, `fromUploadLink` and `createdAt`. `url` is a signed URL of the storage, valid `S3_REQUEST_SIGNATURE_TTL` seconds. A file that is not committed has no token: only its deletion is allowed.

## Commits

A commit groups the files of one upload with the data of the form. It is sent once all its files are uploaded.

| Endpoint | Access | Notes |
|---|---|---|
| `POST /commit` | right to upload to the target, upload link | stores the commit and notifies the target; answers `true` |
| `GET /commits` | `commit:list` scope, admin | filters `target` (ID or IRI, repeatable) and `acknowledged`; unacknowledged first, oldest first |
| `GET /commits/{id}` | `AssetToken` of the commit, `commit:list` scope, admin | `assets`, `formData`, `formLocale`, `userId`, `token`, `totalSize`, `acknowledged`… |
| `POST /commits/{id}/ack` | `AssetToken` of the commit, admin | acknowledges the commit and all its files; answers `true` |

`POST /commit` takes `target` (IRI) or `targetSlug`, `files` (the IDs of the assets) and `formData`. It is synchronous (`App\Storage\CommitManager::store`): when it answers, the commit is stored and its notification queued.

- The form data is checked against the form of the target (see [Form Configuration](./form_config.md)): `400` with the errors. Only the keys of the form are kept, then the data of the target is merged over them.
- `400` beyond the limits of the stack configuration (`max_upload_file_count`, `max_upload_commit_size`) or 10 000 files (`Commit::MAX_FILES`), or for a file that does not exist.
- `403` for a target the user may not upload to, or for a file they did not send (a user commits their own files, a link the files sent through it).
- Sending again the same files, all of them and them only, to the same target, answers `200` without storing nor logging anything again: the client retries a commit whose answer was lost. The target is notified again only if its first notification could not be queued.
- `403` when some of the files already belong to another commit; `409` when another request committed them at the same time.

The acknowledgements are handled by the worker: the last acknowledged file acknowledges its commit. Acknowledging a commit notifies its sender when they asked for it, and triggers the cleanup (see [Retention and cleanup](./configuration.md#retention-and-cleanup)).

## Imports from a URL

| Endpoint | Access | Notes |
|---|---|---|
| `POST /downloads` | right to upload to the target | not through an upload link (`403`) |

The body holds `url`, `target` (IRI) or `targetSlug`, `formData` and an optional `data` (as for `POST /assets`). The form data is checked first: when it is invalid, the answer is `200` with `{"errors": …}`, and nothing is imported. Otherwise the import is queued and the answer is `true`; beyond `DOWNLOAD_RATE_LIMIT`, `429` with `Retry-After`.

The worker (`App\Consumer\Handler\DownloadHandler`) then fetches the file:

- through `NoPrivateNetworkHttpClient`: an address of the private network is refused, redirections included;
- in 600 seconds at most (`app.download.max_duration`), however slow the server;
- within `uploader.max_upload_file_size` (else `S3_MAX_OBJECT_SIZE`), checked on the announced size and while it is received;
- named after `Content-Disposition`, else the last segment of the URL path, cut to 255 bytes; an extension is added from the type when the name has none;
- checked as any other file (see [File types](./configuration.md#file-types)).

The file then becomes a commit of one file, notified to the target like the others. A refused import is not retried; the user is not told, it is logged.

## Targets

| Endpoint | Access | Notes |
|---|---|---|
| `GET /targets` | any user | the enabled, visible targets the user may use (`allowedGroups`), not paginated; filter `query` (on the name) |
| `GET /targets/{id}` | users of the target | `name`, `slug`, `description`, `hasForm`, `hasData` |
| `GET /targets/{id}/settings` | admin | also `targetUrl`, `authorizationScheme`, `hasAuthorizationKey`, `defaultDestination`, `allowedGroups` |
| `POST /targets`, `PATCH /targets/{id}`, `DELETE /targets/{id}` | admin | |
| `GET /targets/{id}/form`, `PUT /targets/{id}/form` | users of the target; form editors to write | see [Form Configuration](./form_config.md#endpoints) |
| `GET /targets/{id}/data`, `PUT /targets/{id}/data` | target data editors | see [Form Configuration](./form_config.md#endpoints) |
| `GET /targets/{id}/form-fields/{key}/options` | users of the target, upload link | values of an entity field of the form, read from Databox |

A disabled or hidden target is neither listed nor readable by its ID through the API, admins included (`404`): it is managed in the admin. A hidden target still accepts uploads by its slug (`targetSlug`).

The authorization key is written only: it is never read back (`hasAuthorizationKey`). `targetUrl` must use `https`, except for a host of the stack without dot (`http://databox-api/…`) and in the dev and test environments (`App\Validation\SecureTargetUrl`). When the URL moves to another origin (scheme, host or port), the key is removed, unless a new key is given along with it.

## Form validation

| Endpoint | Access | Notes |
|---|---|---|
| `POST /form/validate`, body `{"target": "/targets/…", "data": {…}}` | users of the target, upload link | `{"errors": …}`, empty when the data is valid |

## Upload links

| Endpoint | Access |
|---|---|
| `GET /upload-links`, `POST /upload-links` | `upload_link` permission |
| `GET`, `PATCH`, `DELETE /upload-links/{id}`, `POST /upload-links/{id}/regenerate-token` | creator of the link, admin |
| `GET /upload-links/current` | the upload link itself |

See [Upload Links](./upload_links.md).

## Databox

The form builder reads Databox through the API, with the access token of the user (`/databox/workspaces`, `/databox/workspaces/{id}/fields`, `/databox/entity-lists/{id}/values`, `/databox/targets/{id}/destination`): form editors only. See [Form Configuration](./form_config.md#databox-access).

## Other endpoints

- `GET /me`: see [Permissions](#permissions).
- `/mcp`: the MCP server, see [MCP server](./mcp.md).
- `/admin`: the admin, see [Administration](./admin.md).
- `GET /_healthcheck`: public.

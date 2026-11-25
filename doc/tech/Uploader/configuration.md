---
title: Configuration
---

# Uploader Configuration

The Uploader reads its settings from three places: the environment of its containers, the stack configuration (edited in the admin of each API, shared by the clients), and the targets themselves (see [Setup](./02_setup.md)).

## Environment variables of the API

The API, the worker (`uploader-worker`) and the scheduler (`uploader-scheduler`) share one environment. In Docker Compose, most variables get another name in the `.env` of the stack: the table gives both. The defaults are the ones of the `.env` of the stack, or of the application when Compose passes nothing.

| In the container | In `.env` / Compose | Default | Description |
|---|---|---|---|
| `UPLOADER_API_URL` | `UPLOADER_API_URL` | | Public URL of the API: `base_url` of the notifications, pull mode URL of the targets, links of the emails |
| `UPLOADER_CLIENT_URL` | `UPLOADER_CLIENT_URL` | | Public URL of the client |
| `DATABOX_API_URL` | `DATABOX_API_URL` | | Databox API, read by the form builder and the entity fields of the forms; its origin is reachable on the private network |
| `TARGET_PRIVATE_ORIGINS` | `UPLOADER_TARGET_PRIVATE_ORIGINS` | empty | Other origins of targets reachable on the private network, comma separated (see [Targets on the private network](./02_setup.md#targets-on-the-private-network)) |
| `ALLOWED_FILE_TYPES` | `UPLOADER_ALLOWED_FILE_TYPES` | images, documents, audio and video | Allowed MIME types and extensions (see [File types](#file-types)) |
| `DELETE_ASSET_GRACEFUL_TIME` | `UPLOADER_DELETE_ASSET_GRACEFUL_TIME` | `30` (`0` without Compose) | Seconds to keep the files of an acknowledged commit (see [Retention and cleanup](#retention-and-cleanup)) |
| `ASSET_DAYS_RETENTION` | not passed by Compose | `31` | Days to keep any uploaded file |
| `UPLOAD_LINK_MAX_TTL` | `UPLOADER_UPLOAD_LINK_MAX_TTL` | `90` | Longest lifetime of an upload link, in days (see [Upload Links](./upload_links.md#limits)) |
| `DOWNLOAD_RATE_LIMIT` | `UPLOADER_DOWNLOAD_RATE_LIMIT` | `30` | Imports from a URL (`POST /downloads`) accepted per hour and per user |
| `MULTIPART_UPLOAD_RATE_LIMIT` | `MULTIPART_UPLOAD_RATE_LIMIT` | `60` | Multipart upload requests per minute and per user (shared with Databox and Expose) |
| `MULTIPART_UPLOAD_MAX_PENDING` | `MULTIPART_UPLOAD_MAX_PENDING` | `20` | Multipart uploads in progress per user |
| `S3_MAX_OBJECT_SIZE`, `S3_MULTIPART_*` | same | | Storage limits, see [S3 Storage](../Storage/01_s3.md#multipart-upload-settings) |
| `S3_BUCKET_NAME` | `UPLOADER_S3_BUCKET_NAME` | `uploader-deposit` | Bucket of the uploaded files |
| `S3_ACCESS_KEY`, `S3_SECRET_KEY` | `UPLOADER_S3_ACCESS_KEY`, `UPLOADER_S3_SECRET_KEY` | the global `S3_*` keys | Storage credentials of the Uploader |
| `S3_REQUEST_SIGNATURE_TTL` | `UPLOADER_REQUEST_SIGNATURE_TTL` | `600` | Lifetime of the signed file URLs (`url` of `GET /assets/{id}`), in seconds |
| `UPLOAD_MAX_FILE_SIZE` | `UPLOAD_MAX_FILE_SIZE` | `4G` | Largest request body accepted by nginx and PHP: a file sent in the request (`POST /assets` with `file`), not the multipart uploads |
| `ADMIN_CLIENT_ID`, `ADMIN_CLIENT_SECRET` | `UPLOADER_ADMIN_CLIENT_ID`, `UPLOADER_ADMIN_CLIENT_SECRET` | `uploader-admin` | Keycloak client of the admin and of the API |
| `APP_SECRET` | `UPLOADER_APP_SECRET` | | Signs the temporary URLs of the API (32 characters at least) |
| `RABBITMQ_VHOST` | `UPLOADER_RABBITMQ_VHOST` | `uploader` | RabbitMQ virtual host of the queues |
| `DB_NAME`, `POSTGRES_USER`, `POSTGRES_PASSWORD` | `UPLOADER_DB_NAME`, `UPLOADER_POSTGRES_USER`, `UPLOADER_POSTGRES_PASSWORD` | `uploader`, the global user | Database |
| `NOTIFICATIONS_ENABLED` | `NOTIFICATIONS_ENABLED` | `1` | Sends the notifications of the senders (see [Notifications](#notifications)) |
| `MAILER_DSN`, `MAIL_FROM`, `MAIL_FROM_DISPLAY_NAME` | same | | Email transport and sender |

`ASSET_CONSUMER_COMMIT_URI` and `ASSET_CONSUMER_ACCESS_TOKEN` are deprecated: only an old migration (`Version20220802161826`) reads them, to create the first target. The URL and the key of each target are now set on the target.

The variables shared by every application (Keycloak, RabbitMQ, Sentry, Matomo…) are listed in [Environment Variables](../Configuration/01_env_var.mdx).

## Stack configuration

The stack configuration is edited in the admin and read by the API and the client at runtime. The keys of the Uploader:

- `uploader.max_upload_file_size`: largest file, in bytes, within `S3_MAX_OBJECT_SIZE`. Checked when a multipart upload is created and completed, and on an import from a URL.
- `uploader.max_upload_commit_size`: largest total size of a commit, in bytes.
- `uploader.max_upload_file_count`: largest number of files of a commit. With `1`, the client switches to a single file mode.
- `uploader.client.logo` (`src`, `margin`): logo of the top bar of the client, else the global `logo`. Read by the client only: it is not part of the schema of the stack configuration.

`POST /commit` checks the commit limits (`App\Validation\CommitValidator`), whatever the client displays. Besides, a commit never holds more than 10 000 files (`Commit::MAX_FILES`).

The generated reference of these keys is in [Instance Configuration](../Configuration/02_instance.mdx).

## File types

`ALLOWED_FILE_TYPES` lists MIME type masks, each with its extensions: `image/*(.jpg,.png),application/pdf(.pdf)`. Every file is checked against it on upload, then its content is inspected (libmagic): a file whose content does not match its type is refused, as are HLS playlists and ffconcat scripts. The type of the content is the one stored. The rules are shared by every application: see [Allowed file types and file serving](../Storage/03_file_types.md).

The client reads the same variable to filter the file picker and show the accepted types.

## Retention and cleanup

- When a commit is acknowledged and `DELETE_ASSET_GRACEFUL_TIME` is `0` or less, its files are deleted from the storage at once.
- Otherwise, each acknowledgement deletes the commits acknowledged more than `DELETE_ASSET_GRACEFUL_TIME` seconds ago, with their files (`DeleteExpiredAssets`).
- Every day, `app:asset:clean-expired` deletes the files uploaded more than `ASSET_DAYS_RETENTION` days ago, committed or not, acknowledged or not. Its `-r` option overrides the retention, in days, for one run.
- Every day, `alchemy:storage:prune-multipart-uploads` aborts the multipart uploads left unfinished (see [Abandoned uploads](../Storage/01_s3.md#abandoned-uploads)).

## Scheduled tasks

The daily tasks are planned by Symfony Scheduler (`App\Schedule\MainSchedule`) and run by the `uploader-scheduler` container, which must keep a single replica. See [Periodic tasks](../01_setup.md#periodic-tasks).

## Notifications

When the sender checks "Notify me when done" (the `__notify` field of the form), the Uploader notifies them once the destination acknowledges the commit: the `commit:acknowledged` topic, by email and in-app, with the number of files.

- Topic: `config/packages/alchemy_notifier.yaml`; templates: `templates/notifications/commit/acknowledged/`.
- The notifications are sent only when `NOTIFICATIONS_ENABLED` is on, and the emails need `MAILER_DSN`.
- The in-app notifications are recorded (admin > Notifications > In-app Notifications), but no client shows them: the API exposes no in-app notification endpoint, and the Uploader client has no notification bell.
- Nobody is notified for a commit sent through an upload link: the field is hidden there.

## Client

The client reads its own environment (Keycloak client `UPLOADER_CLIENT_ID`, Matomo, Sentry, Content Security Policy, `DEV_MODE`…) and the same stack configuration: see `uploader/client/README.md`.

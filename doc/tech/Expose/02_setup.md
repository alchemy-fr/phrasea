---
title: Setup
---

# Expose setup

## Services

The Compose profiles `expose` and `expose-scheduler` (both in the default `COMPOSE_PROFILES` of
the `.env`) start:

| Service            | Replicas                                    | Notes                                                                                     |
| ------------------ | ------------------------------------------- | ----------------------------------------------------------------------------------------- |
| `expose-api-php`   | 1                                           | PHP-FPM of the API                                                                        |
| `expose-api-nginx` | 1                                           | `api-expose.${PHRASEA_DOMAIN}`, proxy cache in the `expose_nginx_cache` volume            |
| `expose-worker`    | `EXPOSE_WORKER_REPLICAS` (1)                | Queue `p1`                                                                                |
| `expose-scheduler` | 1, never more                               | Profile `expose-scheduler`; a schedule must be consumed by a single process               |
| `expose-client`    | 1                                           | `expose.${PHRASEA_DOMAIN}`, Next.js on port 3000                                          |

The worker and the scheduler run the image of the API with another entry point, so they read the
same variables.

## API variables

Set in the `.env` of Docker Compose (overridden in `.env.local`), then passed to
`expose-api-php` under the name of the second column. The variables common to every API
(PostgreSQL and RabbitMQ hosts, Keycloak URLs, mailer, Sentry, Report API…) are described in
[Environment Variables](../Configuration/01_env_var.mdx).

| `.env`                                                          | In the container                         | Description                                                                                                                                                 |
| --------------------------------------------------------------- | ---------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `EXPOSE_API_URL`                                                | same                                     | Public URL of the API, used in the URLs it generates (downloads, CSS, WebVTT)                                                                               |
| `EXPOSE_CLIENT_URL`                                             | same                                     | Public URL of the client: the only origin allowed by CORS, and the origin the proxy cache keeps apart                                                       |
| `EXPOSE_ADMIN_CLIENT_ID`, `EXPOSE_ADMIN_CLIENT_SECRET`          | `ADMIN_CLIENT_ID`, `ADMIN_CLIENT_SECRET` | Keycloak client of the API itself: back office (`/admin`), API documentation                                                                                |
| `EXPOSE_APP_SECRET`                                             | `APP_SECRET`                             | Symfony secret, also the key signing the `?jwt=` URLs                                                                                                       |
| `EXPOSE_DB_NAME`, `EXPOSE_POSTGRES_USER`, `EXPOSE_POSTGRES_PASSWORD` | `DB_NAME`, `POSTGRES_USER`, `POSTGRES_PASSWORD` | Database; user and password fall back to `POSTGRES_USER` and `POSTGRES_PASSWORD`                                                              |
| `EXPOSE_RABBITMQ_VHOST`                                         | `RABBITMQ_VHOST`                         | RabbitMQ virtual host (`expose`)                                                                                                                            |
| `EXPOSE_S3_BUCKET_NAME`                                         | `S3_BUCKET_NAME`                         | Bucket of the files (`expose`)                                                                                                                              |
| `EXPOSE_S3_ACCESS_KEY`, `EXPOSE_S3_SECRET_KEY`                  | `S3_ACCESS_KEY`, `S3_SECRET_KEY`         | MinIO user of Expose when set, the root keys otherwise                                                                                                      |
| `EXPOSE_REQUEST_SIGNATURE_TTL`                                  | `S3_REQUEST_SIGNATURE_TTL`               | Validity of the signed S3 URLs of the files, in seconds (3600)                                                                                              |
| `EXPOSE_CLOUD_FRONT_URL`, `EXPOSE_CLOUD_FRONT_REGION`, `EXPOSE_CLOUD_FRONT_PRIVATE_KEY`, `EXPOSE_CLOUD_FRONT_KEY_PAIR_ID`, `EXPOSE_CLOUD_FRONT_TTL` | `CLOUD_FRONT_*` | Files served through CloudFront, see [CloudFront](../Storage/02_cloudfront.md) |
| `EXPOSE_MAX_UPLOAD_FILE_SIZE`                                   | `MAX_UPLOAD_FILE_SIZE`                   | Largest file accepted, in bytes (100 GiB), within `S3_MAX_OBJECT_SIZE`                                                                                      |
| `EXPOSE_ALLOWED_FILE_TYPES`                                     | `ALLOWED_FILE_TYPES`                     | Types and extensions accepted, see [File types](../Storage/03_file_types.md); it must cover what Databox publishes, renditions included                     |
| `URI_JWT_TTL`                                                   | same                                     | Validity of the `?jwt=` signed URLs, in seconds (86400). Not passed by `docker-compose.yml`: add it to the environment of the service to change it          |
| `NOTIFICATIONS_ENABLED`                                         | same                                     | `0` disables every email, the download links by email included                                                                                              |
| `TRUSTED_PROXIES`                                               | same                                     | Proxies whose `X-Forwarded-For` is trusted: the rate limits are counted by client IP                                                                        |

The S3 settings shared by every application (`S3_ENDPOINT`, `S3_REGION`, multipart limits…)
are described in [S3 Storage](../Storage/01_s3.md).

`MAPBOX_TOKEN`, `EXPOSE_CLIENT_LOGO_URL` and `EXPOSE_CLIENT_LOGO_ALT` are passed to
`expose-api-php` but read by nothing.

## Client variables

The client reads its configuration from the container environment at request time. The main
ones:

| `.env`                           | In the container          | Description                                                                                             |
| -------------------------------- | ------------------------- | ------------------------------------------------------------------------------------------------------- |
| `EXPOSE_CLIENT_ID`               | `CLIENT_ID`               | Keycloak client of the client (`expose-app`)                                                            |
| `EXPOSE_CLIENT_ALLOW_INDEXING`   | `ALLOW_INDEXING`          | Whether the search engines may index the client (`true`), see [SEO and analytics](./seo-analytics.md) |
| `EXPOSE_DISABLE_INDEX_PAGE`      | `DISABLE_INDEX_PAGE`      | `1`: no public list of the publications at `/`                                                          |
| `EXPOSE_ALLOWED_FRAME_ANCESTORS` | `ALLOWED_FRAME_ANCESTORS` | Sites allowed to embed the pages, see [Embedding](./iframe.md)                                          |

The other variables (Keycloak, Matomo, Sentry, CSP, stack configuration) are listed in
`expose/client/README.md`, section "Runtime configuration".

## Keycloak clients

The configurator provisions the Keycloak clients of Expose: `EXPOSE_CLIENT_ID` for the client,
`EXPOSE_ADMIN_CLIENT_ID` for the API, `DATABOX_EXPOSE_INTEGRATION_CLIENT_ID` for the Expose
integration of Databox. The audience of their tokens is described in
[Access tokens and audiences](../Authentication/clients.md).

## Back office

The EasyAdmin back office of the API is at `/admin` of `EXPOSE_API_URL`
(`src/Controller/Admin/DashboardController.php`):

| Menu          | Entries                                                                                                      |
| ------------- | ------------------------------------------------------------------------------------------------------------ |
| Permissions   | Publication permissions, Profile permissions (rights on every object of the type), All permissions (advanced) |
| Publications  | Publications, Profiles, Assets, Sub-definitions, Multipart Uploads                                           |
| Notifications | Broadcasts, Subscribers, In-app Notifications, Pending Digests, Preferences                                  |
| Settings      | Env Vars, Global Config, Realtime                                                                            |
| Dev           | Messenger Failed Events, Queues, PHP Info (technical role only)                                              |

The back office edits what the client does not: the description, translations, CSS, logo
(`layoutOptions.logoUrl`), date and the assets of a publication, and its theme, which the client
does not apply (see [Publications](./publications.md#layouts-and-themes)).

**Global Config** edits the configuration shared by the stack (logo, theme…): see
[Instance Configuration](../Configuration/02_instance.mdx).

## Storage

- [S3 Storage](../Storage/01_s3.md): buckets, multipart uploads, CORS.
- [CloudFront](../Storage/02_cloudfront.md).
- [Allowed file types and file serving](../Storage/03_file_types.md).

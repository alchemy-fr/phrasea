---
title: 'Databox configuration'
---

# Databox configuration

The Databox containers are configured by environment variables of the stack, declared with their
default value in the root `.env` and mapped to the container variables by `docker-compose.yml`.
This page lists the variables of Databox by domain, with their default and their effect. The
generated reference of every variable of the stack is [Environment Variables](../Configuration/01_env_var.mdx).

## How the variables reach the containers

- The root `.env` holds the defaults (`DATABOX_*` for Databox). Override them in a `.env.local`
  at the root of the repository, read through the `dc` function described in the
  [Setup Guide](../01_setup.md) (`.env.local`, `.env`, then `.env.local` again, so that a
  `PHRASEA_DOMAIN` set in `.env.local` is known when the derived URLs of `.env` are expanded).
- `docker-compose.yml` renames most of them for the containers: `databox-api-php` receives
  `MAX_UPLOAD_FILE_SIZE=${DATABOX_MAX_UPLOAD_FILE_SIZE}`, `APP_SECRET=${DATABOX_APP_SECRET}`…
  `databox-worker` extends `databox-api-php` (same variables, plus `SECRETS_SECRET_KEY`,
  `WORKER_PRIORITIES` and the ImageMagick policy), and `databox-scheduler` extends
  `databox-worker`. The Next.js client `databox-client` receives its own subset.
- The API reads the container variables with `%env(…)%` (`databox/api/config`) or
  `#[Autowire(env: …)]`; `databox/api/.env` only holds fallbacks for a process started
  without the stack. The client reads them server side (`databox/client/src/lib/config/server.ts`).
- Some variables are also read by the `configurator` (`bin/setup.sh`, `bin/migrate.sh`), which
  creates the Keycloak clients, the database, the RabbitMQ vhost and the bucket they name.

A changed variable applies once the containers are recreated:

```bash
dc up -d databox-api-php databox-worker databox-scheduler databox-client
```

Run `bin/setup.sh` (or `bin/migrate.sh`) again when the variable is also read by the configurator
(column "Configurator" below).

## URLs, OAuth clients and API secret

| Variable                               | Default                                                    | Container variable                                         | Effect                                                                                                                                                                                                                                        | Configurator |
| -------------------------------------- | ---------------------------------------------------------- | ---------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------ |
| `DATABOX_API_URL`                      | `https://api-databox.${PHRASEA_DOMAIN}${HTTPS_PORT_PREFIX}` | `DATABOX_API_URL` (API, client, dashboard, Uploader API)    | Public URL of the API: `alchemy_core.app_url`, URLs in the notifications, base of the absolute API URLs; the client calls it and allows it in its CSP. Root and redirect URI of the `DATABOX_EXPOSE_INTEGRATION_CLIENT_ID` client.           | yes          |
| `DATABOX_CLIENT_URL`                   | `https://databox.${PHRASEA_DOMAIN}${HTTPS_PORT_PREFIX}`     | `DATABOX_CLIENT_URL` (API, client, dashboard)               | Public URL of the client: CORS origin of the API (`nelmio_cors.yaml`, with `DASHBOARD_CLIENT_URL`), links to the client in the e-mails and notifications, allowed origin of Sockudo. Root URL of the `DATABOX_CLIENT_ID` Keycloak client.    | yes          |
| `DATABOX_CLIENT_ID`                    | `databox-app`                                              | `CLIENT_ID` (client)                                        | Public OAuth client of the Databox client (sign-in, `databox-app` when empty in the client). The configurator creates it and gives its tokens the `databox` audience.                                                                         | yes          |
| `DATABOX_ADMIN_CLIENT_ID`              | `databox-admin`                                            | `ADMIN_CLIENT_ID` (API)                                     | Confidential client of the API (`alchemy_auth`: admin sign-in, service account, Swagger OAuth). The configurator gives its service account the `databox-admin` realm role and `view-users`.                                                  | yes          |
| `DATABOX_ADMIN_CLIENT_SECRET`          | `__CHANGE_ME_cli3nt_s3cr3t`                                | `ADMIN_CLIENT_SECRET` (API)                                 | Secret of the admin client. Change it.                                                                                                                                                                                                        | yes          |
| `DATABOX_APP_SECRET`                   | `__CHANGE_ME_342fe5ffc14c3b86802ed21c7623eeeb`             | `APP_SECRET` (API)                                          | Symfony `framework.secret` of the API; `.env` states that it signs the temporary URLs of the API and needs at least 32 characters. Change it.                                                                                                 | no           |
| `DATABOX_EXPOSE_INTEGRATION_CLIENT_ID` | `expose-integration`                                       | `PWI_DATABOX_EXPOSE_INTEGRATION_CLIENT_ID` (API)            | Public client (standard flow) authorizing the users of the `phrasea.expose` integration on Expose: default `clientId` of that integration (`${DATABOX_EXPOSE_INTEGRATION_CLIENT_ID}`, see [Secrets](./secrets.md)). Empty: not created.     | yes          |
| `DATABOX_CLIENT_ALLOW_INDEXING`        | `false`                                                    | `ALLOW_INDEXING` (client)                                   | Whether the search engines may index the client: `robots.txt` (`src/app/robots.ts`) and robots meta tag (`src/app/layout.tsx`). Not a boolean or unset: `false`. The link previews of the shares work either way.                          | no           |

## Database

| Variable                    | Default   | Container variable          | Effect                                                                                                                                                                                                                       |
| --------------------------- | --------- | --------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DATABOX_DB_NAME`           | `databox` | `DB_NAME` (API)             | PostgreSQL database of Databox (`DATABASE_URL` built in `config/packages/doctrine.yaml`); the configurator creates it.                                                                                                         |
| `DATABOX_POSTGRES_USER`     | empty     | `POSTGRES_USER` (API)       | Role of Databox instead of the superuser `POSTGRES_USER` (fallback when empty). Set it with its password: the configurator creates the role and gives it the database (see [Database roles](../01_setup.md#database-roles)). |
| `DATABOX_POSTGRES_PASSWORD` | empty     | `POSTGRES_PASSWORD` (API)   | Password of that role (fallback: `POSTGRES_PASSWORD`).                                                                                                                                                                       |

`POSTGRES_HOST` and `POSTGRES_PORT` are shared by the stack.

## Messaging

| Variable                 | Default   | Container variable     | Effect                                                                                                                                                              |
| ------------------------ | --------- | ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DATABOX_RABBITMQ_VHOST` | `databox` | `RABBITMQ_VHOST` (API) | RabbitMQ vhost of the Messenger transports of Databox (DSN built by `messenger-bundle`) and of the RabbitMQ screen of the admin; the configurator creates it. |

With `APP_ENV=dev`, `DEV_MESSENGER_TRANSPORT_DSN` (`sync://` by default) handles the messages in
the dispatching process: the workers then stay idle.

## Storage

| Variable                        | Default   | Container variable                                               | Effect                                                                                                                                                                                                                                        |
| ------------------------------- | --------- | ---------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DATABOX_S3_BUCKET_NAME`        | `databox` | `S3_BUCKET_NAME` (API)                                           | Bucket of the Databox files, created by the configurator (S3) and `minio-setup` (MinIO). It must differ from `CONFIGURATOR_S3_BUCKET_NAME`, which is made public.                                                                            |
| `DATABOX_S3_ACCESS_KEY`         | empty     | `S3_ACCESS_KEY`, `CONFIGURATOR_S3_ACCESS_KEY` (API)              | MinIO user of the API, its workers and scheduler instead of the root `S3_ACCESS_KEY` (fallback when empty): its bucket and `config.json` in the configurator bucket. Set both keys: `minio-setup` creates the user (see [MinIO users](../01_setup.md#minio-users)). |
| `DATABOX_S3_SECRET_KEY`         | empty     | `S3_SECRET_KEY`, `CONFIGURATOR_S3_SECRET_KEY` (API)              | Secret key of that user (at least 8 characters).                                                                                                                                                                                              |
| `DATABOX_REQUEST_SIGNATURE_TTL` | `3600`    | `S3_REQUEST_SIGNATURE_TTL` (API, client, `databox-indexer`)      | Validity in seconds of the presigned S3 URLs (`Alchemy\StorageBundle\Storage\UrlSigner`).                                                                                                                                                    |

The endpoint, region, path prefix and multipart settings (`S3_*`, `MULTIPART_UPLOAD_*`) are shared
by the stack (see [S3](../Storage/01_s3.md)).

## Upload

| Variable                       | Default                    | Container variable                     | Effect                                                                                                                                                                                                                                                                                       |
| ------------------------------ | -------------------------- | -------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DATABOX_MAX_UPLOAD_FILE_SIZE` | `107374182400` (100 GiB)   | `MAX_UPLOAD_FILE_SIZE` (API, client)   | Largest file uploaded to Databox, in bytes, within `S3_MAX_OBJECT_SIZE`: `alchemy_storage.upload.max_file_size`, checked when the multipart upload is created and when it is completed (null or below 1: `S3_MAX_OBJECT_SIZE` only). The client refuses a larger file before uploading it. |
| `DATABOX_ALLOWED_FILE_TYPES`   | see below                  | `ALLOWED_FILE_TYPES` (API, client)     | Allowed MIME types, each with its allowed extensions: `alchemy_storage.upload.allowed_types`, the file picker of the client, and the content check of the ingestion workflow (`CheckFileContentAction`: a refused file is quarantined, see [Quarantine](./quarantine.md)).                  |

The default of `DATABOX_ALLOWED_FILE_TYPES`, in the `mime/type(.ext1,.ext2),…` format described
in [File types](../Storage/03_file_types.md):

```dotenv
DATABOX_ALLOWED_FILE_TYPES='image/*(.jpg,.jpeg,.bmp,.tif,.gif,.png,.heic,.webp,.svg,.psd,.psb),application/*(.pdf,.doc,.docx,.xls,.xlsx,.odt,.psd,.psb),video/*(.mpg,.mpeg,.mov,.avi,.mp2,.mp4,.m4v,.mkv,.hevc,.wmv),audio/*(.aac,.aiff,.wav,.mp3,.mp2,.m4a,.wma),application/x-indesign(.indd)'
```

`UPLOAD_MAX_FILE_SIZE` (`4G`, shared) bounds a request body in PHP and nginx, and
`PREFERRED_FILE_TYPES` (shared) sets the type guessed for some extensions.

## CloudFront

Without `DATABOX_CLOUD_FRONT_URL`, the files are served by presigned S3 URLs. Setup of the
distribution: [CloudFront](../Storage/02_cloudfront.md).

| Variable                          | Default     | Container variable                   | Effect                                                                                                        |
| --------------------------------- | ----------- | ------------------------------------ | ------------------------------------------------------------------------------------------------------------- |
| `DATABOX_CLOUD_FRONT_URL`         | empty       | `CLOUD_FRONT_URL` (API, client)      | URL of the distribution serving the Databox files (`CloudFrontUrlGenerator`); allowed in the CSP of the client. |
| `DATABOX_CLOUD_FRONT_REGION`      | `eu-west-3` | `CLOUD_FRONT_REGION` (API)           | Region of the CloudFront client.                                                                              |
| `DATABOX_CLOUD_FRONT_PRIVATE_KEY` | empty       | `CLOUD_FRONT_PRIVATE_KEY` (API)      | Private key signing the CloudFront URLs.                                                                      |
| `DATABOX_CLOUD_FRONT_KEY_PAIR_ID` | empty       | `CLOUD_FRONT_KEY_PAIR_ID` (API)      | ID of its key pair.                                                                                           |
| `DATABOX_CLOUD_FRONT_TTL`         | `3600`      | `CLOUD_FRONT_TTL` (API)              | Validity of the signed URLs, in seconds. A signed URL stays valid for its whole TTL: keep it short.           |

## Workers

| Variable                      | Default | Used by                                         | Effect                                                                                                                         |
| ----------------------------- | ------- | ----------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| `DATABOX_WORKER_REPLICAS`     | `1`     | `deploy.replicas` of `databox-worker`           | Number of worker containers. `databox-scheduler` always keeps one replica: a schedule is consumed by a single process.        |
| `DATABOX_WORKER_CPU_LIMIT`    | `2`     | `deploy.resources.limits.cpus` of the worker    | CPU limit of each worker container (and of the scheduler, which extends it).                                                   |
| `DATABOX_WORKER_MEMORY_LIMIT` | `1G`    | `deploy.resources.limits.memory` of the worker  | Memory limit of each worker container, shared by all its processes (each one has its own `PHP_MEMORY_LIMIT`, `1G` in the image). |

`WORKER_PRIORITIES` (the consumer programs and their transports) is set in `docker-compose.yml`
itself, not in `.env`: `p1 p1,p2,bulk p1,p2,heavy,heavy_low,bulk,bulk_heavy,bulk_heavy_low task`
for `databox-worker`, `scheduler_main` for `databox-scheduler`. A `.env.local` entry does not change
it: override the service (or the Helm values). Its format and the queues are described in
[Workers](./workers.md). The worker image also sets `WORKER_TIME_LIMIT=3600` and `WORKER_LIMIT=1000`
(`databox/api/Dockerfile`). `WORKER_VERBOSITY` (shared) sets the verbosity of `messenger:consume`.

The ImageMagick policy of the workers is written at start-up by
`databox/api/docker/entrypoint.d/01-imagick.sh`, from variables of `.env` passed to
`databox-worker` only:

| Variable                            | Default    | Policy resource                                      |
| ----------------------------------- | ---------- | ---------------------------------------------------- |
| `IMAGEMAGICK_POLICY_MEMORY`         | `768MiB`   | `memory`                                             |
| `IMAGEMAGICK_POLICY_MAP`            | `2GiB`     | `map`                                                |
| `IMAGEMAGICK_POLICY_AREA`           | `2GiB`     | `area`                                               |
| `IMAGEMAGICK_POLICY_DISK`           | `40GiB`    | `disk`                                               |
| `IMAGEMAGICK_POLICY_WIDTH`          | `48KP`     | `width`                                              |
| `IMAGEMAGICK_POLICY_HEIGHT`         | `48KP`     | `height` (the width when empty)                      |
| `IMAGEMAGICK_POLICY_TIME`           | `120`      | `time`: seconds a command may last                   |
| `IMAGEMAGICK_POLICY_LIST_LENGTH`    | `128`      | `list-length`: images read from one file             |
| `IMAGEMAGICK_POLICY_TEMPORARY_PATH` | `/tmp`     | `temporary-path` (created when missing)              |
| `IMAGEMAGICK_POLICY_VERSION`        | `7`        | the policy file edited, `/etc/ImageMagick-7/policy.xml` |

The script also denies every delegate, module and coder outside its allow list. `IMAGICK_VERSION`
is passed to the worker as well; the PHP `imagick` extension itself is installed at build time,
with the `IMAGICK_VERSION` of `databox/api/Dockerfile`.

## Audit log

| Variable                           | Default | Container variable                | Effect                                                                                                                                                   |
| ---------------------------------- | ------- | --------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DATABOX_AUDIT_LOG_RETENTION_DAYS` | `365`   | `AUDIT_LOG_RETENTION_DAYS` (API)  | Days the audit log entries are kept: the daily `app:audit-log:purge` deletes the older ones; `0` keeps them forever. See [Audit log](./audit-log.md). |

## Secrets

| Variable                     | Default                    | Container variable                                 | Effect                                                                                                                                        |
| ---------------------------- | -------------------------- | -------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| `DATABOX_SECRETS_PUBLIC_KEY` | a public, shared key pair  | `SECRETS_PUBLIC_KEY` (API, workers)                | Public key encrypting the workspace secrets and the secret options of the automations.                                                        |
| `DATABOX_SECRETS_SECRET_KEY` | the matching secret key    | `SECRETS_SECRET_KEY` (`databox-worker`, `databox-scheduler`) | Secret key decrypting them, given to the workers only (and to `databox-api-php` by `docker-compose.dev.yml`).                               |

The default pair of `.env` is public (`@change-me`): generate one with
`app:secrets:generate-key-pair`. See [Secrets](./secrets.md).

## Ticketing

The floating **Report an issue** button of the client, which creates Jira issues. These variables are
passed under their own name to `databox-client` only, and read server side: the credentials never
reach the browser. See [Ticketing](./ticketing.md).

| Variable                                | Default   | Effect                                                                                                  |
| --------------------------------------- | --------- | ------------------------------------------------------------------------------------------------------- |
| `DATABOX_TICKETING_ENABLED`             | `false`   | Shows the floating button to the signed-in users.                                                       |
| `DATABOX_TICKETING_JIRA_URL`            | empty     | Jira instance, e.g. `https://acme.atlassian.net` (required).                                            |
| `DATABOX_TICKETING_JIRA_USER`           | empty     | Jira Cloud: account e-mail, used with the API token in Basic auth. Empty: the token is sent as Bearer (Server / Data Center personal access token). |
| `DATABOX_TICKETING_JIRA_API_TOKEN`      | empty     | API token or personal access token (required).                                                          |
| `DATABOX_TICKETING_JIRA_PROJECT_KEY`    | empty     | Target project (required).                                                                              |
| `DATABOX_TICKETING_JIRA_ISSUE_TYPE`     | `Task`    | Issue type of the tickets (`Task` when empty).                                                          |
| `DATABOX_TICKETING_JIRA_BUG_ISSUE_TYPE` | `Bug`     | Issue type of the bug reports (the issue type when empty).                                              |
| `DATABOX_TICKETING_JIRA_LABELS`         | `databox` | Labels added to every issue, comma separated.                                                           |
| `DATABOX_TICKETING_JIRA_API_VERSION`    | `3`       | REST API version: `3` for Jira Cloud, `2` for Jira Server / Data Center (any other value: `3`).          |

## Owner property

| Variable                                              | Default | Container variable                                   | Effect                                                                                                                                                                                       |
| ----------------------------------------------------- | ------- | ---------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DATABOX_API_ASSET_OWNER_PROPERTY_REQUIRED_ROLE`      | empty   | `API_ASSET_OWNER_PROPERTY_REQUIRED_ROLE` (API)       | When set, only the users having this role get the `owner` of the assets in the API output (`AssetOutputMapper`) and the owner facet of the search (`@owner` built-in attribute, `OwnerBuiltInAttribute`). Empty: everyone. |
| `DATABOX_API_COLLECTION_OWNER_PROPERTY_REQUIRED_ROLE` | empty   | `API_COLLECTION_OWNER_PROPERTY_REQUIRED_ROLE` (API)  | Same for the `owner` of the collections (`CollectionOutputMapper`).                                                                                                                          |

The role is a realm role of the identity provider, mapped by `Alchemy\AuthBundle\Security\RoleMapper`
(`admin`, `tech`, `databox-admin`…). See [Access control](./access-control.md).

## Integrations: instance variables

The integration configurations resolve `${NAME}` from the secrets and variables of their workspace,
then from a `PWI_NAME` environment variable of the API container (see [Secrets](./secrets.md)).
`docker-compose.yml` defines these ones from variables of `.env`:

| Container variable                         | Source                          | Default reference of                                  |
| ------------------------------------------ | ------------------------------- | ----------------------------------------------------- |
| `PWI_PHRASEANET_URL`                       | `PHRASEANET_URL`                | `baseUrl` of `phraseanet.renditions`                  |
| `PWI_PHRASEANET_API_TOKEN`                 | `PHRASEANET_APP_OAUTH_TOKEN`    | `token` of `phraseanet.renditions`                    |
| `PWI_EXPOSE_API_URL`                       | `EXPOSE_API_URL`                | `baseUrl` of `phrasea.expose`                         |
| `PWI_EXPOSE_CLIENT_URL`                    | `EXPOSE_CLIENT_URL`             | `clientUrl` of `phrasea.expose`                       |
| `PWI_UPLOADER_API_URL`                     | `UPLOADER_API_URL`              | `baseUrl` of `phrasea.uploader`                       |
| `PWI_DATABOX_EXPOSE_INTEGRATION_CLIENT_ID` | `DATABOX_EXPOSE_INTEGRATION_CLIENT_ID` | `clientId` of `phrasea.expose`                 |

The API also receives `SIMILARITY_EMBEDDER_URL` and `FACE_RECOGNITION_URL`, set in
`docker-compose.yml` to the internal services (`http://similarity-embedder:8000`,
`http://face-recognition:8000`). See [Integrations](./integration/01_integrations.md).

## API settings without a `.env` entry

These variables have their default in `databox/api/config/services.yaml` and are not passed by
`docker-compose.yml`: add them to the environment of `databox-api-php` (and `databox-worker`)
through a Compose override, or in the Helm values, to change them.

| Variable                                  | Default            | Effect                                                                                                                                        |
| ----------------------------------------- | ------------------ | --------------------------------------------------------------------------------------------------------------------------------------------- |
| `EXPORT_DOWNLOAD_CONCURRENCY`             | `8`                | Files an export worker downloads at once (`AssetExportProcessHandler`); `1` downloads them one by one. See [Workers](./workers.md).            |
| `ASSET_IMPORT_JSON_MAX_SIZE`              | `52428800` (50 MB) | Above it, an asset import must use the NDJSON format, read line by line (`ImportEntryReader`). See [Import](./import.md).                      |
| `ATTRIBUTE_DEFINITIONS_MAX_PER_WORKSPACE` | `500`              | Attribute definitions a workspace may have: each one adds fields to the asset index mapping, shared by every workspace.                       |

## Shared variables

Databox also reads variables shared with the other applications, documented in the generated
reference: Keycloak (`KEYCLOAK_URL`, `KEYCLOAK_INTERNAL_URL`, `KEYCLOAK_REALM_NAME`),
Elasticsearch (`ELASTICSEARCH_URL`, `ELASTICSEARCH_USERNAME`, `ELASTICSEARCH_PASSWORD`,
`ELASTICSEARCH_INDEX_PREFIX`), RabbitMQ, mail (`MAILER_DSN`, `MAIL_FROM`…), Sockudo (see
[Realtime](./realtime.md)), Matomo (see [Analytics](./analytics.md)), `MAPBOX_TOKEN` (map layout of
the results, ignored while it is `define-me`), `NOTIFICATIONS_ENABLED`, `SCHEDULER_OFFSET` (see
[Periodic tasks](../01_setup.md#periodic-tasks)), Sentry and New Relic.

## Related pages

- [Environment Variables](../Configuration/01_env_var.mdx): generated reference of every variable
- [Setup Guide](../01_setup.md): `.env.local`, database roles, MinIO users, periodic tasks
- [Workers](./workers.md)
- [Secrets](./secrets.md)
- [Ticketing](./ticketing.md)
- [Access control](./access-control.md)
- [Audit log](./audit-log.md)

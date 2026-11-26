# UPGRADE FROM 4.x to 5.0

## Upgrade checklist

Follow these steps in order. Each one is detailed in the sections below.

1. Back up the three PostgreSQL databases (databox, expose, uploader): several
   migrations are irreversible.
2. Stop the APIs, the workers and the cron jobs of databox, expose and uploader.
3. Update your `.env` and images: `ELASTICSEARCH_IMAGE` (9.1.4, stored in the new
   `elasticsearch9` volume), `KEYCLOAK_IMAGE`, `REPORT_API_IMAGE`. Remove
   `elastichq` from any custom `COMPOSE_PROFILES`, and decide whether to keep the
   new `similarity` and `face-recognition` profiles. Sockudo replaces Soketi:
   rename the `SOKETI_*` variables and the `soketi` profile (see
   [Sockudo replaces Soketi](#sockudo-replaces-soketi)). Rewrite
   `*_ALLOWED_FILE_TYPES` as type/extension couples, without `*/*`, and pass
   `ALLOWED_FILE_TYPES` to the Expose API (see
   [Allowed file types and file serving](#allowed-file-types-and-file-serving)).
   Give the custom Keycloak clients calling the APIs the client scope of each
   API they call, once `bin/migrate.sh` has created them (see
   [Access tokens: audiences](#access-tokens-audiences)).
   Set `ELASTIC_PASSWORD` and `ELASTICSEARCH_PASSWORD`: the bundled
   Elasticsearch now requires authentication (see
   [Elasticsearch authentication](#elasticsearch-authentication)).
   With Docker Compose, run `dc down` (volumes kept) before starting the new
   stack: the containers move to new networks (see
   [Docker networks split](#docker-networks-split)).
   Give the configurator a bucket of its own and review `*_CLOUD_FRONT_TTL` (see
   [Static bucket and signed URLs](#static-bucket-and-signed-urls)).
4. Start Elasticsearch 9 and create the user of the applications:

    ```bash
    dc up -d elasticsearch
    dc run --rm elasticsearch-setup
    ```

5. Run the migrations, and nothing else (no `doctrine:migrations:version --add`):

    ```bash
    bin/migrate.sh
    ```

6. Recompute the collection access table, in `databox-api-php` (the migrations
   purge its stale "everyone" rows: until then, the collections shared with
   everyone are missing from the root collection tree of the users who do not
   reach them otherwise):

    ```bash
    bin/console app:collection:recompute-access
    ```

7. Rebuild every Elasticsearch index, in `databox-api-php`, then delete the
   former `tag` index (the tags became entity lists, see
   [Tags replaced by entity lists](#tags-replaced-by-entity-lists)):

    ```bash
    bin/console fos:elastica:populate
    ```

8. Deploy the new `databox-scheduler`, `expose-scheduler` and `uploader-scheduler`
   services, with a single replica each (add `expose-scheduler` to any custom
   `COMPOSE_PROFILES`), and remove the host crons (see
   [Scheduler containers replace the cron jobs](#scheduler-containers-replace-the-cron-jobs)).
   Remove the Zippy containers, volume and settings (see
   [Zippy service removed](#zippy-service-removed)).
   If your databox workers set `WORKER_PRIORITIES`, make them consume the new
   `heavy`, `heavy_low`, `bulk`, `bulk_heavy` and `bulk_heavy_low` transports
   (see [Worker queues of databox](#worker-queues-of-databox)).
9. Audit what this release may break: Twig templates in attribute definitions
   and rendition options (now sandboxed), API clients (`hydra:` prefix, `PUT`
   replaced by `PATCH`), shared rendition links, the Databox theme, and the
   image formats ImageMagick still reads (SVG and EPS get no thumbnail any
   more, see [ImageMagick restricted to an allow list of formats](#imagemagick-restricted-to-an-allow-list-of-formats)),
   and the rendition definitions asking ffmpeg for a `timeout` above 3600
   seconds (see [Resource limits of the databox workers](#resource-limits-of-the-databox-workers)).

## Stack and deployment

### Elasticsearch 9

Elasticsearch moves from 7.17 to 9.1.4.

- The compose volume is renamed from `elasticsearch` to `elasticsearch9`. The new
  volume starts empty; the old one is left orphaned and must be removed by hand.
- Heap and limits are now driven by `ELASTICSEARCH_JAVA_OPTS` (default
  `-Xms1g -Xmx1g`, formerly a hard-coded 512m), `ELASTICSEARCH_CPU_LIMIT` (default
  `2`) and `ELASTICSEARCH_MEMORY_LIMIT` (default `2G`), applied as the service's
  `deploy.resources.limits`.
- The `asset` index now holds a `dense_vector` field (`embedding`, 512 dims,
  indexed, cosine) and `fileFamily`: Elasticsearch older than 8.12 cannot create
  it. Upgrade any external cluster.
- The bundled `elasticsearch` service requires authentication (see
  [Elasticsearch authentication](#elasticsearch-authentication)).

See [Elasticsearch 9 and index rebuild](#elasticsearch-9-and-index-rebuild).

### Elasticsearch authentication

The asset rights are applied inside Elasticsearch (the `users` and `groups` of
each document): anyone reaching the cluster could read every workspace, or
rewrite those fields. The bundled `elasticsearch` service now runs with its
security enabled (`xpack.security.enabled=true`, without TLS: it is only
reachable from the internal `data` network, see
[Docker networks split](#docker-networks-split)).

- `ELASTIC_PASSWORD` (new, `@change-me`) is the password of the `elastic`
  superuser. Only the new `elasticsearch-setup` service uses it.
- `elasticsearch-setup` (profile `elasticsearch`, same image) creates or updates
  at every `dc up` the user of the applications, `ELASTICSEARCH_USERNAME` (now
  `databox` by default) with `ELASTICSEARCH_PASSWORD` (`@change-me`), and its
  role of the same name: every privilege on the indices starting with
  `ELASTICSEARCH_INDEX_PREFIX`, `monitor` on the cluster. With an empty prefix
  (the default), the role covers every index of the cluster.
  `bin/setup.sh`, `bin/migrate.sh` and `bin/test.sh standard` run it in the
  foreground before using Elasticsearch.
- `dockerize` waits for the TCP port of Elasticsearch (an HTTP wait now gets a
  401).
- With the development port (`DEV_ELASTICSEARCH_PORT`), pass the credentials:
  `curl -u "databox:${ELASTICSEARCH_PASSWORD}" localhost:9200/_cat/indices`.

To upgrade a running stack, the indices of the `elasticsearch9` volume are kept
(enabling the security of an existing node is supported):

1. Set `ELASTIC_PASSWORD` and `ELASTICSEARCH_PASSWORD` in `.env.local` (outside a
   dev stack, `bin/setup.sh` and `bin/migrate.sh` refuse their `__CHANGE_ME_`
   defaults). Keep `ELASTICSEARCH_USERNAME` to its new default `databox`, or set
   your own (not `elastic`).
2. Recreate Elasticsearch and the services using it, then create the user:

    ```bash
    dc up -d
    dc run --rm elasticsearch-setup
    ```

    (`bin/migrate.sh` does both.) Until `elasticsearch-setup` has run, the API
    and the workers get a 401 from Elasticsearch.

The `elastic` password is only read from `ELASTIC_PASSWORD` as long as it has not
been changed through the API (`_security/user/elastic/_password`); then set
`ELASTIC_PASSWORD` to the new one.

With an external cluster (no `elasticsearch` profile), nothing is created:
provide `ELASTICSEARCH_USERNAME` and `ELASTICSEARCH_PASSWORD` yourself, and empty
both for a cluster without authentication (`ELASTICSEARCH_USERNAME` now defaults
to `databox`).

### Keycloak image

`infra/docker/keycloak/` is gone (Dockerfile, the group-uuid and jq-idp mapper
SPIs, the `phrasea` theme). The image is now maintained in
`alchemy-fr/phrasea-keycloak` and published as
`public.ecr.aws/b2s9z7l1/ps-keycloak`. The `KEYCLOAK_IMAGE` variable (defaults
to the `:latest` tag, not pinned) replaces
`${REGISTRY_NAMESPACE}keycloak:${DOCKER_TAG}`, which the CI no longer builds.
Pin a tag if you need reproducible deployments.

### Report API image

The `report/` sources are removed. `REPORT_API_IMAGE`
(`public.ecr.aws/b2s9z7l1/ps-report-api:1.0.0`) replaces
`${REGISTRY_NAMESPACE}report-api`.

### Similarity and face recognition services

Two services are added, `similarity-embedder` and `face-recognition`,
with the `similarity` and `face-recognition` profiles enabled in the default
`COMPOSE_PROFILES`. Their images (`SIMILARITY_EMBEDDER_IMAGE`,
`FACE_RECOGNITION_IMAGE`) live on an Alchemy ECR registry: if you cannot pull
them, drop the two profiles or override the variables. They cost CPU and memory
once started.

### ElasticHQ removed

The `elastichq` service, its profile, `ELASTICHQ_URL` and its dashboard link are
removed.

### Report ELK stack removed

`docker-compose.report-elk.yml` (`report-elasticsearch`, `report-logstash`,
`report-kibana`), `infra/docker/report-elk`, the `report-logstash` image and
`ELK_VERSION` are removed. The report API still writes its `logs` table in the
report database; read it there, or feed it to your own tooling. Drop
`docker-compose.report-elk.yml` from any custom `COMPOSE_FILE`, stop and remove
the three containers (`docker rm -f`), then the orphaned `report_elasticsearch`
volume, and the `report-kibana.<domain>` DNS entry.

### Databox client is a Next.js server

The Databox client is no longer static files served by nginx.

- The final image stage is `runner` (formerly `client-nginx`), a `node` server
  listening on port **3000** (formerly 80). It has no `HEALTHCHECK`. The Traefik
  router is renamed from `databox-client-dev` to `databox-client`.
- There is no more `config.js` / `window.config` / `__TPL_CONFIG__` injection.
  Configuration is read on each request from `process.env` and from the
  configurator's `config.json` (`CONFIGURATOR_S3_ENDPOINT`,
  `CONFIGURATOR_S3_BUCKET_NAME`), refreshed every `STACK_CONFIG_REFRESH_INTERVAL`
  seconds (default 60). The container must therefore reach that bucket at
  runtime.
- The headers formerly set by nginx (`Content-Security-Policy: frame-ancestors`,
  HSTS, `X-Robots-Tag`, `X-Content-Type-Options`) and `robots.txt` are no longer
  emitted: set them on your reverse proxy. `phrasea-manifest.json` is still
  served, by a route of the Next.js server.
- Sentry (`CLIENT_SENTRY_DSN`) only reports the browser errors: no tracing, no
  replay, no source maps uploaded.
- Theme: the global `theme` entry (MUI theme options) no longer applies to
  Databox, which reads `databox.theme` (JSON, edited from the admin "Customize
  theme" screen or `PUT /client-theme`). The global `logo` is still honoured.
- Deep links: modal links `?_m=/assets/{id}/{rendition}` become plain paths
  `/assets/{id}/{rendition}`. Old `?_m=` links are not translated and open the
  home page.
- The OAuth callback moves from `/auth/check` to `/auth/callback`. Only Keycloak
  redirect URIs configured by hand without a `/*` wildcard need updating.
- Server-side rendering settings are removed (`SSR_PORT`, `SSR_API_BASE_URL`),
  and the client no longer reads the `S3_MULTIPART_*` variables.

### Expose, Uploader and Dashboard clients are Next.js servers

The three clients follow the Databox client (see above): same images
(`expose-client`, `uploader-client`, `dashboard`), now `node` servers.

- They listen on port **3000** (formerly 80), with no `HEALTHCHECK`. The
  Traefik routers `expose-client-dev` and `uploader-client-dev` are renamed
  `expose-client` and `uploader-client`.
- No more `config.js` / `window.config` injection: the configuration is read on
  each request from `process.env` and from the configurator's `config.json`
  (`CONFIGURATOR_S3_*`), which the containers must reach at runtime.
- The headers formerly set by nginx (HSTS, `X-Robots-Tag`,
  `X-Content-Type-Options`) are no longer emitted: set them on your reverse
  proxy. Expose and the Dashboard still send their
  `Content-Security-Policy: frame-ancestors` (Expose:
  `EXPOSE_ALLOWED_FRAME_ANCESTORS`).
- The OAuth callback moves from `/auth/check` to `/auth/callback`. Only Keycloak
  redirect URIs configured by hand without a `/*` wildcard need updating.
- Sentry (`CLIENT_SENTRY_DSN`) only reports the browser errors, as in Databox.
- Theme: the global `theme` entry (MUI theme options) no longer applies; they
  offer the organisation theme `databox.theme`. The global `logo` (and
  `uploader.client.logo`) is still honoured.
- Dashboard: `/menu.html` is gone. `/git-log.html` redirects to `/git-log`. The dashboard no longer reads
  `REPORT_API_URL`, `SAML_URL` nor `SAML2_URL`.
- The `nginx-client-base` image is no longer built nor used.

### Scheduler containers replace the cron jobs

The periodic tasks of databox, expose and uploader are now planned by Symfony
Scheduler and consumed by three new services, built from the worker images (no
new image), each in the profile of the same name:

| Service              | `WORKER_PRIORITIES` | Replicas |
| -------------------- | ------------------- | -------- |
| `databox-scheduler`  | `scheduler_main`    | 1        |
| `expose-scheduler`   | `scheduler_main`    | 1        |
| `uploader-scheduler` | `scheduler_main`    | 1        |

Run them with **exactly one replica** and never scale them: scale the
`*-worker` services instead.

- Without `databox-scheduler`, the scheduled automations (saved searches) never
  run. Never add `scheduler_main` to the `WORKER_PRIORITIES` of the
  `*-worker` services: a schedule must be consumed by a single process.
- Remove the host crons: `bin/ops/cron-script-hourly.sh`,
  `bin/ops/cron-script-daily.sh` and `databox/api/bin/cron-script-{hourly,daily}.sh`
  are deleted (the daily one only served Zippy, see
  [Zippy service removed](#zippy-service-removed)). The `main` schedule of databox
  triggers the due automations every minute, runs `alchemy:notifier:digest:flush`,
  `app:integration:renew-tokens` and `app:file:unblock-analyses` hourly, and
  `app:trash:empty` daily.
- The uploader now removes its expired assets daily
  (`app:asset:clean-expired`, retention `ASSET_DAYS_RETENTION`, 31 days by
  default), which was not scheduled before.
- The three APIs now prune their abandoned multipart uploads daily
  (`alchemy:storage:prune-multipart-uploads`, see
  [Abandoned multipart uploads](#abandoned-multipart-uploads)), which was not
  scheduled before.

The commands are kept for manual runs; `bin/console app:automation:run-due`
triggers the due automations once.

### Zippy service removed

The external Zippy service (`alchemyfr/zippy-api`, `alchemyfr/zippy-worker`) is
no longer part of the stack: Expose and Databox build their ZIP archives
themselves (PHP `zip` extension, already in the images).

- Remove the `zippy-api` and `zippy-worker` containers (`dc up -d --remove-orphans`),
  the `zippy` volume, the `zippy` profile from any custom `COMPOSE_PROFILES`, the
  `ZIPPY_URL`, `ZIPPY_CLIENT_ID` and `ZIPPY_CLIENT_SECRET` variables, the
  `zippy.<domain>` DNS entry and the daily host cron `bin/ops/cron-script-daily.sh`
  (deleted).
- Expose now builds the archive of a publication itself and stores it in its
  bucket, under `archives/`. It is rebuilt when the assets, the title or the
  download terms change, and deleted with the publication. Archives built by
  Zippy are lost: they are rebuilt on the first download. In direct mode the
  archive is built during the download request, so very large publications may
  hit the PHP-FPM/nginx timeouts; in "download via email" mode the worker builds
  it before sending the email.

See [Expose API](#expose-api) for the renamed properties and routes.

### Sockudo replaces Soketi

Soketi is no longer maintained: the WebSocket server is now
[Sockudo](https://github.com/sockudo/sockudo)
(`SOCKUDO_IMAGE=ghcr.io/sockudo/sockudo:5.2.0`), which speaks the same Pusher
protocol on the same port (6001). The PHP and JS clients are unchanged.

Everything named after Soketi is renamed, with no fallback on the old names:

- compose service and profile `soketi` → `sockudo`: update `COMPOSE_PROFILES`
  in `.env.local` (or wherever you override it);
- public hosts `soketi.${PHRASEA_DOMAIN}` → `ws.${PHRASEA_DOMAIN}` and
  `soketi-usage.${PHRASEA_DOMAIN}` → `ws-usage.${PHRASEA_DOMAIN}`: update
  your DNS (or `/etc/hosts`, see `infra/dev/hosts.txt`) and TLS certificates;
- environment variables, to rename in `.env.local` and in any deployment
  configuration:

| Before             | After               |
| ------------------ | ------------------- |
| `SOKETI_HOST`      | `SOCKUDO_HOST`      |
| `SOKETI_PORT`      | `SOCKUDO_PORT`      |
| `SOKETI_SCHEME`    | `SOCKUDO_SCHEME`    |
| `SOKETI_APP_ID`    | `SOCKUDO_APP_ID`    |
| `SOKETI_KEY`       | `SOCKUDO_KEY`       |
| `SOKETI_SECRET`    | `SOCKUDO_SECRET`    |
| `SOKETI_IMAGE`     | `SOCKUDO_IMAGE`     |
| `SOKETI_USAGE_URL` | `SOCKUDO_USAGE_URL` |

`SOCKUDO_APP_ID`, `SOCKUDO_KEY` and `SOCKUDO_SECRET` must all be set (never
empty): otherwise Sockudo keeps the demo app of its bundled example
configuration.

`/usage` (memory usage, linked from the dashboard) is now served on the app
port 6001, no longer on 9601: the `ws-usage` host only exposes `/usage`,
and the `sockudo` host hides `/usage`, `/stats` and `/operator`.

Remove the old container once the new one is up:

```bash
dc up -d --remove-orphans
```

`databox-api-php` pushes to `sockudo:6001` over plain http on the internal
network (`SOCKUDO_HOST`, `SOCKUDO_PORT`, `SOCKUDO_SCHEME`). If you use an
external server without the `sockudo` profile, override these three variables
for `databox-api-php`.

### Building from sources

Only relevant if you build the images yourself rather than pulling the
published ones.

- Rebuild `php-fpm-base` first: it now provides `composer-strip-path-packages`.
- `*/api/docker/caching/` and `bin/optimize-composer-docker-cache` are removed.

### ImageMagick restricted to an allow list of formats

The databox and expose API and worker images now start with a deny-by-default
ImageMagick policy (`databox/api/docker/entrypoint.d/01-imagick.sh`): a crafted
upload could otherwise read local files (container environment included) or
reach the network through ImageMagick coders and Ghostscript. Only JPEG, PNG,
GIF, WEBP, TIFF, HEIC/HEIF/AVIF, PSD, BMP, ICO and PDF are read; external
delegates and the `@file` syntax are refused.

- SVG files are still stored and downloadable, but get no bitmap rendition.
- EPS and PostScript files, and Illustrator files saved without PDF
  compatibility, no longer get a thumbnail. Other formats outside the list
  (JPEG 2000, JPEG XL, TGA...) get no rendition either.
- Existing renditions are kept; rebuilding the renditions of such assets fails.

See [Formats read by ImageMagick](doc/tech/Databox/Renditions.mdx#formats-read-by-imagemagick)
to re-enable a format.

### Resource limits of the databox workers

Uploaded files, the URLs they come from and the rendition definitions are
untrusted: the workers now bound what they spend on them.

- ImageMagick: new `IMAGEMAGICK_POLICY_TIME` (120 seconds),
  `IMAGEMAGICK_POLICY_LIST_LENGTH` (128 images per file: animation frames,
  document pages) and `IMAGEMAGICK_POLICY_HEIGHT` (the width when unset)
  variables. A longer animation or a slower conversion now fails: raise them if
  needed.
- Downloads from a URL (files to ingest, transcripts, remote files of the
  renditions) last 900 seconds at most and are refused above `S3_MAX_OBJECT_SIZE`.
- The `timeout` of the ffmpeg modules and of `document_to_pdf` is 3600 seconds
  at most (also the default of the ffmpeg modules; `document_to_pdf` keeps its
  300 seconds default); a definition asking for more is refused. The maximum
  is set by `DATABOX_MAX_RENDITION_BUILD_TIME` (the
  `alchemy_rendition_factory.max_timeout` configuration). The maximum of
  `document_to_pdf` goes from 1800 to 3600 seconds by default.
- `video_summary` extracts 100 clips at most (`max_clips` option, up to 1000),
  and refuses a video announcing more than 24 hours.

### Worker queues of databox

Databox has five new Messenger transports, so that long jobs no longer delay the
fast messages (see [Databox workers](doc/tech/Databox/workers.md)):

- `heavy`: the renditions built first, metadata reading, file analysis,
  downloads, exports, bulk deletions…;
- `heavy_low`: the heavy workflow jobs that are not urgent, the other
  renditions (animated thumbnails, custom renditions…) and the AI integrations;
- `bulk`, `bulk_heavy` and `bulk_heavy_low`: the light, heavy and non urgent
  heavy jobs of the workflows started by a bulk processing (workspace
  ingestion, asset import, automation).

`ESPopulate`, `IndexAllAssets`, `IndexAllCollections` and `DeleteWorkspace` move
to `task`. `IngestWorkspaceAsset`, which starts the workflow of one asset of a
workspace ingestion, moves from `task` to `bulk`: the workflows of every asset
are started by all the worker processes before the non urgent jobs.

`WORKER_PRIORITIES` accepts several receivers per process, in priority order,
separated by `,`, and a number of processes with a `:N` suffix. The databox
worker image now defaults to
`p1 p1,p2,bulk p1,p2,heavy,heavy_low,bulk,bulk_heavy,bulk_heavy_low task`:
each process takes the most urgent message waiting among its receivers, so the
bulk processings use every idle process while the interactive messages keep the
priority. A deployment that sets `WORKER_PRIORITIES` itself (Helm values…)
**must consume `heavy`, `heavy_low`, `bulk`, `bulk_heavy` and
`bulk_heavy_low`**, in the same deployment or in
a dedicated one: otherwise their messages pile up in RabbitMQ and the assets are
never processed. Each process has its own `PHP_MEMORY_LIMIT`: give
`DATABOX_WORKER_MEMORY_LIMIT` room for the new one.

### Indexer

The example `config/config.json` now sets `createNewWorkspace` to `false`: the
workspace is no longer flushed by default. Pass `-n`
(`--create-new-workspace`) to `index` for the former behaviour.

### Access tokens: audiences

The APIs used to accept any token signed by the realm. They now only accept
the access tokens (`typ: Bearer`, so no more ID tokens) whose audience (`aud`
claim) includes the name of the application (`databox`, `expose`,
`uploader`), and check `nbf` and `iat` besides `exp` (30 seconds of clock skew
tolerated). The tokens of the admin client of the API itself
(`*_ADMIN_CLIENT_ID`) are accepted without audience. See
[Access tokens and audiences](doc/tech/Authentication/clients.md).

- Keycloak gives the audiences: `bin/migrate.sh` (Keycloak synchronization)
  creates a client scope per API (`databox-api`, `expose-api`, `uploader-api`,
  with an audience mapper giving the name of the application as "Included
  Custom Audience") and adds them to the default scopes of the clients
  of the stack: Databox, Expose, Uploader (Uploader and Databox) and Dashboard
  clients, Databox indexer, `DATABOX_EXPOSE_INTEGRATION_CLIENT_ID`, `postman`
  when it exists, and every client created from Databox for its integrations
  (Keycloak attribute `phrasea.created_by=databox`). No variable to set.
- Until it has run, and for the tokens issued before, the APIs answer
  `401 Invalid token.`: users log in again (the Expose integrations of
  Databox are authorized again by their users).
- Deployments with custom clients (scripts, MCP clients, integrations whose
  Keycloak client was created by hand…) must give them the scope of each API
  they call, as a default client scope (Clients › `<client>` › Client scopes ›
  Add client scope), or their calls answer `401 Invalid token.` (the API logs
  the refused client and the scope it lacks).

### Docker networks split

The single `internal` network shared by every container is replaced by four
networks, so that a compromised container (a client, a console…) no longer
reaches the databases, Redis, Elasticsearch, RabbitMQ or php-fpm, and the data
services have no Internet access (see
[Networks and trusted proxies](doc/tech/01_setup.md#networks-and-trusted-proxies)):

- `proxy` (bridge, `PS_SUBNET`, gateway `PS_GATEWAY_IP`): Traefik and the
  services it routes from the front;
- `app` (`internal`): the API nginx and their php-fpm;
- `data` (`internal`): databases, queues, storage, internal services, and
  their clients;
- `egress` (bridge): Internet and the host for php, the workers and the
  configurator.

To upgrade a running stack:

- recreate it, since `proxy` takes the `PS_SUBNET` still held by
  `<project>_internal` (`Pool overlaps with other one on this address space`):

    ```bash
    dc down    # the volumes are kept
    dc up -d
    ```

    or `docker network rm <project>_internal` once its containers are stopped;

- services added to the stack by an override file must be given the networks
  they need instead of `internal`, and a routed service that is not on `proxy`
  the label `traefik.docker.network=${COMPOSE_PROJECT_NAME}_data`. A service
  only on `app`/`data` gets no published port, no Internet and no
  `extra_hosts` route to the host;
- `TRAEFIK_PROVIDERS_DOCKER_NETWORK` is now `${COMPOSE_PROJECT_NAME}_proxy`;
- the Expose API nginx no longer reads `PS_SUBNET`: `/purge` only refuses the
  requests relayed by a proxy;
- the default `PS_SUBNET` moves from `172.30.0.0/16` to `10.230.0.0/16`
  (gateway `10.230.0.1`, Traefik `10.230.255.254`), out of the Docker address
  pools. Nothing to do without an override (the `dc down` above applies it).
  A `PS_SUBNET` overridden in `.env.local` needs `PS_GATEWAY_IP` and
  `PS_TRAEFIK_IP` changed along with it, and an explicit `TRUSTED_PROXIES`
  holding `172.30.255.254` must be updated. To keep `172.30.0.0/16`, set
  `PS_SUBNET=172.30.0.0/16`, `PS_GATEWAY_IP=172.30.0.1` and
  `PS_TRAEFIK_IP=172.30.255.254` in `.env.local`;
- the networks other than `proxy` take their subnet from the Docker address
  pools (`172.17.0.0/16` to `172.31.0.0/16`, then `192.168.0.0/16`, by
  default), which may grab the `PS_SUBNET` of a stopped stack: choose every
  `PS_SUBNET` outside of them (e.g. `10.231.0.0/16` for a second stack). To
  keep a `PS_SUBNET` in `172.16.0.0/12`, move the pools out of it
  (`default-address-pools` in `/etc/docker/daemon.json`, e.g.
  `{"base": "10.210.0.0/16", "size": 24}`, with a base that overlaps no
  stack's `PS_SUBNET`).

### Static bucket and signed URLs

- The configurator makes the whole `CONFIGURATOR_S3_BUCKET_NAME` bucket public:
  it now refuses to run (`setup`, `s3` step) when this bucket is also the one of
  an application (`DATABOX_S3_BUCKET_NAME`, `EXPOSE_S3_BUCKET_NAME`,
  `UPLOADER_S3_BUCKET_NAME`, `INDEXER_BUCKET_NAME`), whose files would all be
  readable without signature. Move the configuration to a bucket of its own if
  so (and remove the public policy left on the shared bucket).
- `DATABOX_CLOUD_FRONT_TTL` and `EXPOSE_CLOUD_FRONT_TTL` (and the
  `CLOUD_FRONT_TTL` default of `alchemy/storage-bundle`) go from 86400 to 3600
  seconds, like the S3 signatures: a signed URL stays valid for its whole TTL,
  even once its user has lost access to the file (removed from a workspace,
  share or publication password revoked). Keep a value set in `.env.local` or
  the deployment values only if you accept that delay.

### Helm

This repository no longer has any Helm chart: the unmaintained
`infra/helm/report-elk` chart (archived elastic/helm-charts 7.11.2 charts) is
removed. Apply the same changes in `alchemy-fr/phrasea-helm-charts`: in
particular, replace the databox `CronJob`s with `databox-scheduler`,
`expose-scheduler` and `uploader-scheduler` Deployments (worker image, `WORKER_PRIORITIES` as above,
`replicas: 1`, `Recreate` strategy so that two pods never overlap), and swap
Soketi for Sockudo (image, `SOCKUDO_*` variables, hosts, `/usage` on port 6001,
see [Sockudo replaces Soketi](#sockudo-replaces-soketi)), and enable the security
of the Elasticsearch it deploys, with a dedicated user for the applications (see
[Elasticsearch authentication](#elasticsearch-authentication)).

## Database migrations

Run the migrations with `bin/migrate.sh` only (it runs
`doctrine:migrations:sync-metadata-storage` then `doctrine:migrations:migrate -n`
for each app):

```bash
bin/migrate.sh
```

Note that `bin/migrate.sh` starts the whole stack (`docker compose up -d`)
before migrating: searches will return incomplete results until the indexes
are rebuilt.

Then run `bin/console app:collection:recompute-access` in `databox-api-php`: a
migration deletes every "everyone" row (NULL user) of `collection_access`, which
the former recomputation never purged, and only the recomputation brings back
the valid ones.

A migration repairs the `date` and `date_time` attributes stored as invalid
because their value carried fractional seconds (ISO 8601 extended, e.g.
`2026-04-26T00:00:00.000Z`): they are rewritten in the ATOM format and marked
valid. A deployment already running a 5.0 pre-release must then run
`bin/console fos:elastica:populate --index=asset` in `databox-api-php` to make
them searchable.

A migration converts the display preferences saved by the legacy client, in the
profiles (`profile_data`) and the user preferences (`user_preference`): the
preview size and the file / attributes ratio stored in percent (`60`) become
ratios (`0.6`), and the layouts `g` / `l` become `grid` / `list`. Nothing to do
by hand.

The three Day-0 baselines (databox, expose, uploader) detect the existing schema
and register themselves without running any SQL. Expect the warning
"Base schema already present, nothing to do." for each of them.

Irreversible migrations (their `down()` throws):

- the three Day-0 baselines;
- the removal of the dead FOSOAuth tables (`access_token`, `auth_code`,
  `refresh_token`, `oauth_client`, unused since Keycloak) and of
  `asset_rendition.uri`;
- the DBAL 4 schema realignment: `collection_access.id` and
  `messenger_messages.id` become `GENERATED BY DEFAULT AS IDENTITY` (sequences
  dropped, values kept), column comments are cleared (about 277
  `COMMENT ON COLUMN … IS NULL`), and several indexes are renamed.

Long-running operations and locks:

- `CREATE INDEX ca_path_idx ON collection_access USING GIST (path)` runs without
  `CONCURRENTLY`: writes to `collection_access` are blocked while it builds.
- `workflow_state.number` is backfilled with a full-table `UPDATE`
  (`ROW_NUMBER() OVER (PARTITION BY asset_id, name …)`).

The `list_value_uniq` index is dropped: entities with the same value are now
allowed in an entity list. Rolling back this migration fails if such duplicates
exist.

In Expose, `publication.zippy_id` and `publication.zippy_hash` are dropped
(replaced by the empty `archive_path` and `archive_hash`), and the
`config_include_download_terms_in_zippy` columns of `publication` and
`publication_profile` are renamed to `config_include_download_terms_in_archive`.
Rolling back restores empty Zippy columns.

## Elasticsearch 9 and index rebuild

All the databox indexes (`asset`, `collection`, `asset_data_template`, `basket`,
`attribute_entity`, `message`) must be repopulated on the new cluster. The former
`tag` index is gone (see [Tags replaced by entity lists](#tags-replaced-by-entity-lists)).
`bin/migrate.sh` does not do it. In `databox-api-php`:

```bash
bin/console fos:elastica:populate
```

Do not rely on `app:search:validate` right after the switch: it may report
`[OK]` on an empty index, because its stored index state describes the old
cluster.

The `asset` index also gets a new nested `attributeValues` field (every stored
attribute value of the asset). `GET /attributes?definition=` reads it to apply
the asset rights: until the `asset` index is repopulated, it returns no value.
Any deployment already running a 5.0 pre-release needs the same rebuild:

```bash
bin/console fos:elastica:populate --index=asset
```

## API changes (API Platform 5)

These changes apply to the databox, expose and uploader APIs.

### No more `hydra:` prefix

JSON-LD keys lose their `hydra:` prefix: `hydra:member` → `member`,
`hydra:totalItems` → `totalItems`, `hydra:view` → `view` (with `first`,
`previous`, `next`, `last`), `hydra:search` → `search`, and the collection
`@type` `hydra:Collection` → `Collection`. A 4.x indexer pointed at a 5.0 API
no longer reads anything.

### Errors

Error payloads follow RFC 7807: `hydra:title` / `hydra:description` become
`title`, `detail` and `description`. A denormalization error on a constrained
property, or a uniqueness violation, may now answer **422** with a
`ConstraintViolation` body instead of 400.

### `PUT` replaced by `PATCH`

Item `PUT` operations are removed from every resource. Send `PATCH` with
`Content-Type: application/merge-patch+json` instead, on:

- databox: `/assets`, `/attachments`, `/asset-policies`, `/renditions`,
  `/attributes`, `/attribute-definitions`, `/attribute-entities`,
  `/attribute-filter-rules`, `/attribute-policies`, `/collections`,
  `/entity-lists`, `/rendition-definitions`, `/rendition-policies`, `/shares`,
  `/workspaces`, `/integrations`, `/integration-data`, `/pages`,
  `/profiles`, `/saved-searches`, `/asset-data-templates`,
  `/template-attributes`, `/baskets`, `/messages`;
- expose: `/assets`, `/publications`, `/publication-profiles`,
  `/download-requests`;
- uploader: `/targets` (`/target-params` and `/form-schemas` are removed, see
  [Form and data on the target](#form-and-data-on-the-target)).

RPC-style `PUT` routes are kept: `/assets/{id}/trigger-workflow`,
`/assets/{id}/position`, `/assets/{id}/story-cover`,
`/attribute-entities/{id}/merge`, `/collections/{id}/move/{dest}`,
`/profiles/{id}/items/{itemId}`, `/preferences`, `/client-theme`,
`/notification-preferences`.

As a consequence, upgrade Databox, Expose and Uploader **together**: Databox 4.5
publishing to Expose 5.0 fails, and so does the reverse. Third-party
publishers, such as Phraseanet's Expose integration, must send `PATCH`.

### Undeclared query parameters are ignored

Filters are now declared per operation. A query parameter that is not declared
no longer reaches the search, and typed values may be rejected. In Expose,
`order[updatedAt]` is no longer accepted on `GET /publications` (`title` and
`createdAt` remain).

### Security is checked before loading data

Access rules are evaluated before the data provider runs: an anonymous user now
gets **401** where it used to get 403 (for instance on `GET /baskets`).

### Databox workflows

`GET /workflows` no longer returns the raw entity but a summary: `id`, `name`,
`status`, `number`, `eventName`, `startedAt`, `endedAt`, `duration` (string),
`assetId`, `assetName`. `GET /workflows/{id}` keeps the full dump (plus
`number`, `asset{id,name}` and `skipReason`) and requires READ.

## Databox API behaviour

### Shared renditions

- The URL of a shared rendition moves from
  `GET /s/{id}/r/{rendition}?asset={asset}` to
  `GET /s/{id}/a/{asset}/r/{rendition}`. Old URLs answer 404.
- Only renditions of a public rendition policy are served; trashed or
  quarantined assets are not delivered; the source file URL is no longer
  exposed.
- On a share, `ownerId` and `token` are no longer writable.

### Saved searches in asset search

The `savedSearch` option of `GET /assets` and `GET /assets/geo-clusters`:

- requires READ on the saved search (403, or 401 when anonymous);
- has its precedence reversed: a non-empty `query` in the request replaces the
  stored one, and `conditions` are combined with AND;
- only reads `query`, `conditions` and `sortBy` from the stored `data`.

### AQL

- `MATCHES` / `DOES NOT MATCH` become a full-text search (`match` query, AND
  operator) on the text sub-field instead of a `term` query: existing saved
  searches, filter rules and URLs may return different results.
- After `IS`, `EMPTY`, `MISSING`, `ANY`, `NONE` and `NOT` are reserved words.
- An invalid boolean value answers 400.

See `doc/tech/Databox/aql.md`.

### Upsert by `key`

`POST /assets` and `POST /collections` with the `key` of an existing object
require EDIT on that object, or answer 403.

### Sorting

- `order[@id]` is no longer accepted (`@id` is not sortable: Elasticsearch 8+
  refuses to sort on `_id`).
- Sorting on a non-sortable built-in (`@rendition`, `@directCollection`,
  `@similar`, …) or on an attribute definition whose `sortable` is false answers
  **400**.

### Attribute updates

- Updates merge: `annotations`, `originUserId`, `originVendor` and
  `originVendorContext`, when omitted or `null`, keep the stored value.
  `annotations: []` clears them.
- `annotations` must be a list: a single object is rejected.
- Attribute values are validated in their normalized form: an unknown entity
  id or a non-numeric number is now rejected (it used to be stored without
  validation). Violations carry the `value` sub-path.

### Sandboxed Twig

Twig templates in attribute initial/fallback values and in rendition options now
run in a sandbox:

- allowed tags: `apply`, `autoescape`, `for`, `if`, `set`, `verbatim`, `with`;
- forbidden functions: `attribute`, `block`, `constant`, `dump`, `enum`,
  `enum_cases`, `include`, `parent`, `source`, `template_from_string`;
- on entities, only an allow list of accessors can be read: `asset.id`,
  `name`, `ownerId`, `workspaceId`, `locale`, `externalId`, `createdAt`,
  `updatedAt`, `editedAt`, `story`, `source` and the `sourceFile*`,
  `sourceChecksum`, `sourceDocUniqueId` shortcuts; `file.id`, `filename`,
  `originalName`, `extension`, `extensionWithDot`, `type`, `size`, `checksum`,
  `docUniqueId`, `createdAt`, `metadataValues` and `getMetadata()`; `attr.*`
  is unchanged (see `doc/tech/Databox/metadata.md`). The other getters are
  gone, among which `file.path`, `file.pathPublic`, `file.storage`,
  `file.analysis`, `file.alternateUrls`, `file.workspace`, `asset.workspace`,
  `asset.collections`, `asset.referenceCollection`, `asset.storyCollection`,
  `asset.tags`, `asset.attributes`, `asset.renditions`, `asset.extraMetadata`:
  a template still using one of them fails to render with a "not allowed"
  error (`?? default` and `is defined` treat them as undefined);
- `range()` and the `..` operator produce 10,000 elements at most.

The validation constraint also rejects such templates on save: audit your
existing definitions.

### Structural rules

- Soft-deleted workspaces are hidden, even from admins.
- Creating a collection checks ownership of the parent collection (not
  `ownerId`).
- Moving a collection into one of its descendants answers 400.
- `POST /assets/move` to another workspace answers 400 (it used to answer 204).

### Stricter access checks

Shares:

- `GET /shares?assetId=` only lists shares the user can read.
- Assets added to a share must be readable and shareable.

Attributes and entities:

- `GET /attributes?assetId=` is filtered by attribute policy.
- `GET /attributes/{id}` requires READ.
- `POST /attributes` rejects a definition from another workspace.
- Batch attribute actions are limited to the assets and policies the user can
  edit.
- `GET /attribute-entities/{id}` requires READ.
- Only an editor of the entity list can change an entity's `status`.
- `creatorId` of an entity is not writable.
- An entity must belong to the entity list of the definition's workspace.
- Value proposals are reserved to readers of the entity list.
- `/entity-lists` is limited to readable workspaces.

Integrations:

- `configInfo` is only visible to editors.
- `GET /integrations/{id}` requires READ.
- `GET /integrations/{id}/tokens` only returns the caller's tokens.
- Publishing a basket to Expose requires READ on the basket.
- The AWS Transcribe SNS endpoint only accepts
  `https://sns.<region>.amazonaws.com` URLs.
- The Phraseanet `events` webhook (`phraseanet.renditions` integration,
  `enqueue` method) requires the `webhookSecret` of the integration in its
  `token` query parameter, and answers 401 otherwise. New integrations get a
  generated secret; an existing one refuses the Phraseanet events, and its
  renditions stay pending, until a `webhookSecret` is added to its
  configuration (e.g. `openssl rand -hex 32`) and the new Webhook URL shown in
  its information is copied into Phraseanet. The type and size sent by the
  event are ignored: they are taken from the downloaded rendition.

Workflows and files:

- Cancelling or rerunning a workflow requires EDIT on the run.
- Copying a file through `sourceFileId` requires READ on that file.
- `GET /attachments` requires `assetId` and READ on the asset.
- Creating an attachment requires READ on the asset.
- On `POST /assets`, the `relationship` source asset, file and integration
  must be readable and belong to the workspace of the new asset (an
  instance-wide integration is accepted); otherwise 400.
- A rendition definition (`definitionId`, `name`, nested `renditions` of
  `POST /assets`, `targetRendition` of an upload) must belong to the workspace
  of the asset; `POST /renditions` with a definition of another workspace
  answers 400 (it used to answer 422), as for an unknown one.
- The `ownerId` of `POST /assets` and `POST /collections` can only name another
  user for an admin or a token without user (OAuth client): otherwise 403.

Other reads:

- `GET /renditions/{id}` requires READ on the asset.
- `GET /asset-exports/{id}` is restricted to its owner.
- READ on rendition definitions follows the collection ACL.
- Listing asset policies requires EDIT on the workspace.
- Attribute filter rules are listed for editable workspaces only.
- Profiles and profile items require READ.
- Pages require READ, and their `ownerId` is not writable.
- Asset data templates require READ on the workspace, both to read and to
  create them.

Anonymous users:

- Only PUBLIC assets and collections are visible, even in a public workspace.
- `POST /assets/entities` no longer resolves users.
- `/uploads*` requires an authenticated user (in the three APIs).

## Tags replaced by entity lists

Databox tags are removed: entity lists do the same (colours, translations,
facets, AQL, list permissions) and more. A migration converts them.

What the migration does, for each workspace having tags or an attribute
filter rule on `@tag` (nothing is created in the other workspaces):

- creates an attribute policy "Tags" (public and editable: whoever reads the
  asset reads its tags, `EDIT_ATTRIBUTES` edits them, as before), an entity
  list "Tags" (colours and translations enabled, readable by the readers of
  the workspace) and a multi-valued attribute definition of type `entity`
  "Tags", slug `tags`, facet enabled, for the assets and the stories;
- turns each tag into an entity of the list **with the same UUID** (name →
  value, colour → colour, name translations → translations; the tag locale is
  dropped), each asset tag into a value of the attribute, each tag of an asset
  data template into a template attribute value;
- replaces `@tag` in the AQL conditions: with the slug of the workspace
  definition in the attribute filter rules, with `tags` in the saved searches
  and in the default queries of the pinned filters. The tag UUIDs being kept,
  `@tag = "<uuid>"` becomes `tags = "<uuid>"` and still matches;
- replaces each `@tag` profile item (grid, facets, filters, attributes) by one
  item per migrated definition: a profile is not bound to a workspace and
  resolves its items by definition ID;
- converts the automation actions `tags` into `attribute` actions on the
  "Tags" definition of the workspace of each tag: one `add` per added tag, one
  `delete` of the removed tags (`value`: their IDs). Unknown tags are left out;
  an automation left without action is disabled;
- removes the `tag:*` events from the webhook subscriptions;
- drops the `tag`, `asset_tag` and `asset_data_template_tag` tables and the
  `asset.tags_edited_at` column. Its `down()` recreates them empty and removes
  what `up()` created, without converting anything back.

Fallback slug: when a workspace already has an attribute definition named
"Tags" or with the slug `tags`, the migrated one is named "Tags (migrated)",
slug `tagsmigrated` (and the list or the policy "Tags (migrated)" when the name
is taken). The filter rules of that workspace use `tagsmigrated`, but saved
searches, which may cover several workspaces, use `tags`: review the saved
searches and pinned filters on such workspaces.

After the migration, **repopulate** the `asset` and `attribute_entity` indexes,
then delete the former `tag` index (`<prefix>tag_<env>` and its aliases), which
nothing uses any more:

```bash
bin/console fos:elastica:populate --index=asset
bin/console fos:elastica:populate --index=attribute_entity
```

API changes and their replacement:

- `/tags` (and the MCP tool `list_tags`) → the entities of the "Tags" list:
  `/attribute-entities?list=<list id>`;
- `tags` of `POST`/`PATCH /assets`, of the stories and of the asset data
  templates, and `tags` of the asset output → the values of the "Tags"
  attribute (`attributes`, or `POST /assets/{id}/attributes`);
- `tags_must` / `tags_must_not` of `GET /assets` (and `tagsMust` /
  `tagsMustNot` of the MCP tool `search_assets`) → AQL conditions:
  `tags HAS ALL OF ("<id>", …)`, `tags IS NONE OF ("<id>", …)`;
- `withTags` of `POST /assets/copy` → `withAttributes`;
- the `@tag` AQL field and the `tags` facet → the `tags` attribute and its
  facet;
- the attribute batch action on the pseudo definition `tags` → the `add` and
  `delete` actions on the "Tags" definition. The `delete` action now takes an
  optional `value` (a value or a list): only these values are removed (without
  it, every value of the definition still is), and `add` keeps a single copy
  of a value the asset already has;
- the `tags` field of an import entry is refused: use
  `"attributes": {"tags": ["<entity id>"]}`;
- an imported workspace template ignores a former `Tag` section, and refuses an
  attribute filter rule whose condition still uses `@tag`.

The Uploader forms using the built-in `databox_tags` field are converted to an
attribute field by a separate migration of the Uploader, to deploy together
(Databox no longer reads `databox_tags`): see
[Tags field of the forms](#tags-field-of-the-forms). The Phraseanet indexer
(`databox/indexer`) must write the status bits as values of an entity list
instead of tags. The Rust indexer (`alchemy-fr/databox-indexer`), which calls `/tags`, must be
upgraded separately.

## Expose API

- `PUT` is replaced by `PATCH` and the `hydra:` prefix is gone (see
  [API changes](#api-changes-api-platform-5)).
- `order[updatedAt]` is removed from `GET /publications`.
- `/publications/slug-availability/{slug}` only answers `GET`.
- Archives (see [Zippy service removed](#zippy-service-removed)):
  `includeDownloadTermsInZippy` → `includeDownloadTermsInArchive` on publications
  and profiles (`config_include_download_terms_in_zippy` columns renamed, values
  kept); `GET /publications/{id}/download-via-zippy` →
  `GET /publications/{id}/archive`; `POST /publications/{id}/zippy/download-request`
  → `POST /publications/{id}/archive/download-request`. The archive email
  notification topic `download:zippy_link` becomes `download:archive_link` (its
  templates move to `download/archive_link/`). The `zippyId` admin field is
  removed.
- Anonymous `GET /publications/{id}` and `GET /assets/{id}` now send
  `Cache-Control: public, max-age=600` (capped by the expiration) with
  `Vary: Authorization, X-Passwords`. Check the behaviour of any cache or proxy
  in front of Expose.
- Publication and profile passwords are stored hashed (irreversible migration).
  `config.securityOptions.password` is write-only: it is never read back, a
  write without it keeps the current password, and `config.hasPassword` tells
  whether one is set. Leaving the `password` security method drops the password.
- Rate limits, kept in Redis (`REDIS_URL`): 10 wrong passwords per client IP and
  security container in 15 minutes (then `authorizationError: too_many_attempts`,
  even with the right password), 10 download requests by email per client IP and
  5 per recipient per hour, 60 view logs per client IP per minute (`429`). The
  client IP relies on `TRUSTED_PROXIES`.
- `POST …/download-request` answers `400` without a valid `email`;
  `POST /logs/publication-view/{id}` and `/logs/asset-view/{id}` answer `403`
  unless the publication, or the asset, can be read (`X-Passwords` included).
- CORS no longer allows credentials, and `/admin` sends no CORS header.
- In publication archives, the entries are named after the last segment of the
  original file name, `\` counting as a separator, without control characters
  nor trailing dots and spaces (`..\..\evil.exe` becomes `evil.exe`).

## Uploader API

Routes, methods and bodies of `POST /assets`, `/assets/{id}/ack`, `/commit`,
`/commits/{id}/ack` and `/form/validate` are unchanged. Only `PUT` → `PATCH`,
the `hydra:` prefix and filter typing change: `acknowledged` is a strict
boolean. `GET /targets/{id}/form-schema` becomes `GET /targets/{id}/form` (see
below).

### Form and data on the target

A target holds its form and its data itself:

- `/form-schemas` and `/target-params` are removed. The form of a target is
  read and written with `GET|PUT /targets/{id}/form`, its data with
  `GET|PUT /targets/{id}/data`; `GET /targets` and `GET /targets/{id}` tell
  whether a target has them (`hasForm`, `hasData`).
- A target has one form for every language, designed with the form builder
  of the Uploader client: the LiForm JSON schemas are gone, and so is the
  `localeMode`.
- The migration `Version20261111120000` converts the schemas of each target
  into one builder form and moves its target params to its data. What the
  builder cannot express is left out, with a warning naming the target and the
  key. The ACEs set on one schema or one params record are deleted, the global
  ones (`form_schema`, `target_params`) are kept. It cannot be reverted.

See [Migration from LiForm](doc/tech/Uploader/form_config.md#migration-from-liform).

### Upload links

- A link must expire: `expiresAt` is required, at most
  `UPLOADER_UPLOAD_LINK_MAX_TTL` days ahead (90 by default, `UPLOAD_LINK_MAX_TTL`
  in the API container). The migration gives the links without expiration one
  90 days after it runs, whatever the configured maximum.
- A link works only while its creator could still create it: the `upload_link`
  permission and the allowed groups of the target are checked on each request,
  against the groups (and admin role) the creator had when they last saved the
  link. Otherwise the requests made with it answer `403` with the code
  `upload_link_revoked`. The links created before the migration recorded no
  group: the ones whose creator gets the permission or the target through a
  group, or is an admin, stop working until their creator saves them again
  (editing the link or regenerating its token records them).
- `GET /upload-links/current` no longer gives `lastFormData`: what was entered
  in the form of the last commit is kept by the browser that entered it
  (`localStorage`), not by the link, whose other holders could read it. The
  migration drops `upload_link.last_form_data`.

### Hardening

- `POST /form/validate` answers `403` for a target the user may not read, like
  `GET /targets/{id}/form`.
- `POST /commit` stores the commit right away (it was stored by a worker): files
  committed twice at once go with the first commit, the second answers `409`.
- `POST /downloads` is rate limited per user: `UPLOADER_DOWNLOAD_RATE_LIMIT`
  downloads per hour (30 by default, `DOWNLOAD_RATE_LIMIT` in the API
  container), `429` with `Retry-After` beyond. A download lasts 600 seconds at
  most (`app.download.max_duration`), however slow the server is; the name of
  the file is the last segment of its name (`Content-Disposition` or URL),
  without control characters, 255 bytes at most.
- The names of the uploaded files are cut to 255 bytes (their extension kept).
- The target URL (`targetUrl`) must use `https`: the authorization key is sent
  to it. `http` is accepted for a host of the stack, without dot
  (`http://databox-api/…`), and in the dev and test environments. The existing
  targets are not checked until they are edited.
- The notification of a target reaches the private network only for the origin
  (scheme, host and port) of `DATABOX_API_URL` and the ones listed in
  `UPLOADER_TARGET_PRIVATE_ORIGINS` (comma separated, empty by default,
  `TARGET_PRIVATE_ORIGINS` in the API container): any other host resolving to a
  private address is refused, and the commit fails after its retries. A target
  on a host of the stack (`http://databox-api/…`) must use the URL of
  `DATABOX_API_URL` (the target URL shown by the Databox Uploader integration)
  or be listed. A notification lasts 60 seconds at most, and does not follow
  redirections when it targets one of these origins.

### Tags field of the forms

Databox has no tags any more (see
[Tags replaced by entity lists](#tags-replaced-by-entity-lists)): the built-in
form field `databox_tags`, the field type `tag` and
`GET /databox/workspaces/{id}/tags` are removed. The tags are the values of
the `tags` entity attribute, added to a form like any other attribute. A
migration of the Uploader, to run with the one of Databox:

- turns the `databox_tags` field of each form into a field of the `tags`
  attribute of the form's workspace (multiple, labels, default values and
  widget kept: the tag UUIDs are the entity UUIDs). The attribute and list IDs
  are the deterministic ones the Databox migration gives them. A form that
  already has a `tags` field gets the key `tagsmigrated` instead (with a
  warning); a form without workspace loses the field (with a warning);
- renames the `databox_tags` key of the target data and of the commits not
  yet acknowledged to the key of that field, merging their values.

It cannot be reverted. To review afterwards, in the form builder:

- the forms of a workspace where Databox took the fallback slug
  `tagsmigrated` while the form had no `tags` field: their field keeps the key
  `tags`, which now names the other attribute. Replace it by the
  "Tags (migrated)" attribute;
- the forms of a workspace that had no tag: Databox created no "Tags" list,
  the field has no value to offer. Remove it.

## Allowed file types and file serving

See `doc/tech/Storage/03_file_types.md`.

- `DATABOX_ALLOWED_FILE_TYPES` and `UPLOADER_ALLOWED_FILE_TYPES`
  (`alchemy_storage.upload.allowed_types`) keep their `type(.ext1,.ext2),…`
  format, but each entry is now a couple: an upload is accepted when one type
  matches its MIME type **and** lists its extension (before, the type and the
  extension were checked separately, each against all the entries). An entry
  without extensions accepts the extensions registered for the uploaded type.
- A wildcard main type (`*/*`, `*/jpeg`) is refused: uploads fail with a
  configuration error until it is removed. The default value replaces
  `*/*(.indd)` with `application/x-indesign(.indd)`, adds the missing comma
  before `audio/*` and allows `.mp3`, `.mp2` and `.m4a` for `audio/*`. Report
  these changes in your `.env.local` or deployment values if you override them.
- The previous default value of the Helm chart listed extensions under types
  their files are not of, which these entries never accept: `.ai` and `.ait` under `image/*`, `.txt` under `application/*`,
  `.mxf` and `.m4a` under `video/*`, and the InDesign extensions (`.indd`,
  `.indt`, `.idml`, `.idms`, `.icml`) under `image/*` (a `.indd` is
  `application/x-indesign`). Such files are now refused, with a message naming
  the misplaced extension; a warning listing the misplaced extensions whose
  types the MIME database knows is logged when uploads are checked. Take the
  new value of the chart, or list these extensions under their own types
  (`application/*`, `text/*`, `audio/*`). The default `.env` value no longer lists `.mp3`
  and `.m4a` under `video/*` (they stay under `audio/*`).
- The expose `file` and `upload` modes (asset and sub-definition creation) and
  every presigned `PUT` URL are checked too.
- The list now applies to the Databox and Expose APIs, and to every way a file
  enters them (files sent in the request, URLs and imports, uploader, Phraseanet).
  `docker-compose.yml` passes `DATABOX_ALLOWED_FILE_TYPES` to `databox-api-php`
  (and its workers), and the new `EXPOSE_ALLOWED_FILE_TYPES` to
  `expose-api-php`: the Databox types plus the output formats of the renditions.
  Helm: set `ALLOWED_FILE_TYPES` on the Expose API too (the chart only sets it
  on the Databox API).
- The content of the files is checked against their declared type (libmagic, on
  the first 64 KiB): HLS playlists and ffconcat scripts are refused, and so is
  a file whose content is not of the family of its declared type (an HTML page
  declared `image/jpeg`). Legitimate files declared with a wrong type are
  refused too. A refused Databox asset source is quarantined and never
  processed; bypassing the quarantine accepts it. Files stored before the
  upgrade are not checked again until their asset is ingested again.
- Signed URLs force the response `Content-Type` from the type known in the
  database. Only `image/*` (except SVG), `video/*`, `audio/*` and
  `application/pdf` are displayed inline; anything else (SVG, HTML, XML,
  text…) is downloaded as `application/octet-stream`.
- CloudFront: the cache policy must include `response-content-type` and
  `response-content-disposition` in the cache key, and the origin must be
  signed (OAC/OAI); see `doc/tech/Storage/02_cloudfront.md`, which also
  recommends a response headers policy (`nosniff`, sandboxing CSP).
- storage-bundle: `UrlSigner::getSignedUrl()` accepts `type` and `inline`
  options; without them, the URL serves an `application/octet-stream`
  attachment.

## Multipart uploads are planned by the API

`POST /uploads/{id}/part` (the presigned URL of a single part) is removed from
the three APIs: a client of the previous version can no longer send a file in
parts. Use the `urls` returned by `POST /uploads` (keyed by part number), and
renew them with `POST /uploads/{id}/parts` and `{"from": n}`.

### Upload limits

The limits are applied when the upload is created, before anything is opened
on the storage (see "Upload limits" in `doc/tech/Storage/01_s3.md`):

- `POST /uploads` and `POST /uploads/{id}/parts` answer at most 1 000 part URLs:
  a client asks `/parts` with `from` for the following ones.
- A maximum file size per application, within `S3_MAX_OBJECT_SIZE`: new
  `MAX_UPLOAD_FILE_SIZE` variable of Databox and Expose (100 GiB by default, set
  by `DATABOX_MAX_UPLOAD_FILE_SIZE` and `EXPOSE_MAX_UPLOAD_FILE_SIZE` with Docker
  Compose), the stack configuration `uploader.max_upload_file_size` for the
  uploader. Raise them before the upgrade if larger files are sent.
- The default of `S3_MAX_OBJECT_SIZE` is now 5 TiB, the largest object of S3
  (it was 48 TiB, which no storage accepts).
- New `MULTIPART_UPLOAD_RATE_LIMIT` (60 upload requests per minute and per
  user, 429 with `Retry-After` beyond) and `MULTIPART_UPLOAD_MAX_PENDING` (20
  uploads in progress per user, 429 beyond) variables. A script creating many
  uploads at once has to wait for `Retry-After` and to complete or cancel its
  uploads.
- Through an upload link, an exhausted link or a file larger than what remains
  of its total size is refused at creation (400 `upload_link_quota_exceeded`).
  The `AssetToken` of a commit can no longer use `/uploads` (403).
- `POST /uploads` refuses (422) a `filename` longer than 255 characters or a
  `type` longer than 150, before anything is opened on the storage.

### Abandoned multipart uploads

`alchemy:storage:prune-multipart-uploads`, now run daily by the scheduler of
each API, aborts the S3 multipart uploads initiated more than 24 hours ago
(`--min-age` in hours, 3 at least) and deletes the `multipart_upload` rows of
that age never used by a resource. It only lists the uploads under
`S3_PATH_PREFIX`.

With an empty `S3_PATH_PREFIX`, it aborts every multipart upload of the bucket:
do not share the bucket of an application with anything else, or give it a
prefix.

## Story members are no longer assets of the story's collections

The members of a story used to be indexed under the collections of the story
asset: browsing a collection, or filtering on it (`parent`, `parents`,
`collection`, `directCollections`, `@collection`, `@directCollection`), also
returned the members of the stories it contains. They now only belong to the
collections they are directly attached to; use `story` / `@story` to list the
members of a story. The permissions granted on a collection still reach the
members of its stories.

Re-populate the `asset` index: until then the members keep their former
collections, and the new `storyCollectionPaths` field, which propagates the
permissions granted on a collection to the members of its stories, is missing.

```bash
bin/console fos:elastica:populate --index=asset
```

## Notification emails

Every email body is now wrapped in a branded layout (logo, subject, card, a
call-to-action built from the `uri` / `action` blocks, plain-text alternative).
An overridden template (`template_namespace`) that renders a full HTML document
ends up nested in it: keep only the body fragment.

## For developers extending Phrasea

- workflow-bundle: `Alchemy\Workflow\Doctrine\Entity\{WorkflowState,JobState}`
  → `Alchemy\WorkflowBundle\Entity\*` (attribute mapping, YAML mapping removed,
  same tables).
- workflow-bundle: `Alchemy\Workflow\State\Repository\DoctrineStateRepository`
  → `Alchemy\WorkflowBundle\Doctrine\DoctrineStateRepository`.
- workflow-bundle: the `ServiceExecutor` tag is removed.
- Tagged services: `defaultIndexMethod` (`getName`, `getKey`, `getFormat`) is
  replaced by `#[AsTaggedItem(index: …)]` for rendition-factory modules and
  formats, integrations, attribute types, built-in attributes, analyzers and
  databox operation tasks.
- configurator: `ConfiguratorInterface::getPriority()` is removed in favour of
  `#[AsTaggedItem(priority: …)]`.
- `AbstractIntegrationAction::shouldRun(): bool` → `getSkipReason(): ?string`.
- storage-bundle: `MultipartUploadPartAction` and `MultipartUploadCancelAction`
  are removed, and so are `MultipartUploadPartUrlProcessor`,
  `MultipartUploadPartUrlOutput` and `UploadManager::getSignedUrl()`.
- api-test: `CompactResultPrinter` is removed.
- admin-bundle: `ChildPropertyEntityConfigurator` is removed.
- webhook-bundle: `EntitySerializer::convertChangeSetToPhpValue()` is removed.
- Symfony 8: `eraseCredentials()` is gone from `JwtUser` and `JwtOauthClient`;
  voters use the new `voteOnAttribute` signature.
- databox no longer depends on `alchemy/zippy`: an extension that used it must
  require it itself. `Alchemy\CoreBundle\Util\ZipUtil::zipDirectory()` zips a
  directory with `ZipArchive`.
- `@alchemy/api`: `ApiConstant.HydraTitle` / `HydraDescription` →
  `ErrorTitle` / `ErrorDescription` (`title` / `description`).

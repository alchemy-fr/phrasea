# UPGRADE FROM 4.x to 5.0

## Upgrade checklist

Follow these steps in order. Each one is detailed in the sections below.

1. Back up the three PostgreSQL databases (databox, expose, uploader): several
   migrations are irreversible.
2. Stop the APIs, the workers and the cron jobs of databox, expose and uploader.
3. Update your `.env` and images: `ELASTICSEARCH_IMAGE` (9.1.4, stored in the new
   `elasticsearch9` volume), `KEYCLOAK_IMAGE`, `REPORT_API_IMAGE`. Remove
   `elastichq` from any custom `COMPOSE_PROFILES`, and decide whether to keep the
   new `similarity` and `face-recognition` profiles.
4. Start Elasticsearch 9:

   ```bash
   dc up -d elasticsearch
   ```

5. Run the migrations, and nothing else (no `doctrine:migrations:version --add`):

   ```bash
   bin/migrate.sh
   ```

6. Rebuild every Elasticsearch index, in `databox-api-php`:

   ```bash
   bin/console fos:elastica:populate
   ```

7. Make sure the databox worker consumes `scheduler_automation` (see
   `WORKER_PRIORITIES`) and that the hourly cron runs
   `app:file:unblock-analyses`.
8. Optionally, if the integration is enabled and its service deployed:

   ```bash
   bin/console app:similarity:index
   bin/console app:face-recognition:index
   ```

9. Audit what this release may break: Twig templates in attribute definitions
   and rendition options (now sandboxed), API clients (`hydra:` prefix, `PUT`
   replaced by `PATCH`), shared rendition links, and the Databox theme.

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

See [Elasticsearch 9 and index rebuild](#elasticsearch-9-and-index-rebuild).

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

Two optional services are added, `similarity-embedder` and `face-recognition`,
with the `similarity` and `face-recognition` profiles enabled in the default
`COMPOSE_PROFILES`. Their images (`SIMILARITY_EMBEDDER_IMAGE`,
`FACE_RECOGNITION_IMAGE`) live on an Alchemy ECR registry: if you cannot pull
them, drop the two profiles or override the variables. The databox API reaches
them through `SIMILARITY_EMBEDDER_URL` and `FACE_RECOGNITION_URL`. They are only
used when the matching integration is enabled, but they cost CPU and memory
once started.

### ElasticHQ removed

The `elastichq` service, its profile, `ELASTICHQ_URL` and its dashboard link are
removed.

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
  emitted: set them on your reverse proxy. `phrasea-manifest.json` is no longer
  served.
- Sentry is not wired: `CLIENT_SENTRY_DSN` has no effect on Databox.
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

### Databox worker and scheduler

The databox worker now runs with
`WORKER_PRIORITIES="p1 p2 task scheduler_automation"`. `scheduler_automation` is
a Symfony Scheduler transport, not a RabbitMQ queue; it is guarded by a Redis
lock, so several replicas are fine. Without it, scheduled automations never
run. As a fallback, run:

```bash
bin/console app:automation:run-due
```

### Soketi

`databox-api-php` now pushes to `soketi:6001` over plain http on the internal
network (`SOKETI_HOST`, `SOKETI_PORT`, `SOKETI_SCHEME`). If you use an external
Soketi without the `soketi` profile, override these three variables for
`databox-api-php`.

### Hourly cron

`app:file:unblock-analyses` is added to `databox/api/bin/cron-script-hourly.sh`.
If you run the cron outside the image (Kubernetes CronJob, host crontab), add
it:

```bash
bin/console app:file:unblock-analyses
```

### Building from sources

Only relevant if you build the images yourself rather than pulling the
published ones.

- Rebuild `php-fpm-base` first: it now provides `composer-strip-path-packages`.
- `*/api/docker/caching/` and `bin/optimize-composer-docker-cache` are removed.
- The databox API image gains the `ftp` and `gd` extensions.

### Indexer

The example `config/config.json` now sets `createNewWorkspace` to `false`: the
workspace is no longer flushed by default. Pass `-n`
(`--create-new-workspace`) to `index` for the former behaviour.

### Impersonation

Users holding the Keycloak `realm-management` → `impersonation` role can now
act on behalf of another user by sending the `X-Impersonate-User` header. This
role is part of `realm-admin`, granted to the default admin. There is no switch
to disable it: review who holds these roles.

### Helm

This repository has no Helm chart for the stack (`infra/helm/report-elk` is
unchanged). Apply the same changes in `alchemy-fr/phrasea-helm-charts`.

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

Other migrations are additive (`share_rendition_definition`,
`share.renditions_restricted`, `asset_embedding`, `attribute.embedding`,
`attribute_entity.note`, `file.import_pending`, `saved_search_automation`,
`automation_run`, `automation_seen_asset`, `workflow_state.number`,
`*_upload.chunk_size`).

For information, the APIs move from Doctrine DBAL 3 to 4, ORM 2 to 3, and
ramsey/uuid-doctrine 1 to 2. No action is needed.

## Elasticsearch 9 and index rebuild

All the databox indexes (`asset`, `collection`, `asset_data_template`, `basket`,
`attribute_entity`, `tag`, `message`) must be repopulated on the new cluster.
`bin/migrate.sh` does not do it. In `databox-api-php`:

```bash
bin/console fos:elastica:populate
```

Do not rely on `app:search:validate` right after the switch: it may report
`[OK]` on an empty index, because its stored index state describes the old
cluster.

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
  `/tags`, `/workspaces`, `/integrations`, `/integration-data`, `/pages`,
  `/profiles`, `/saved-searches`, `/asset-data-templates`,
  `/template-attributes`, `/baskets`, `/messages`;
- expose: `/assets`, `/publications`, `/publication_profiles`,
  `/download_requests`;
- uploader: `/targets`, `/target-params`, `/form-schemas`.

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
- On a share, `assets` becomes writable while `ownerId` and `token` no longer
  are. A share can be restricted to some rendition definitions
  (`renditionDefinitions`).

### Saved searches in asset search

The `savedSearch` option of `GET /assets` and `GET /assets/geo-clusters`:

- requires READ on the saved search (403, or 401 when anonymous);
- has its precedence reversed: a non-empty `query` in the request replaces the
  stored one, and `conditions` are combined with AND;
- only reads `query`, `conditions` and `sortBy` from the stored `data`;
- ignores an invalid stored sort instead of answering 400.

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
- In a saved search, an invalid sort is silently ignored.
- The client sort editor only offers sortable fields.

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
- on entities, only `get*`, `is*`, `has*` methods and `__toString` can be
  called.

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

Workflows and files:

- Cancelling or rerunning a workflow requires EDIT on the run.
- Copying a file through `sourceFileId` requires READ on that file.
- `GET /attachments` requires `assetId` and READ on the asset.
- Creating an attachment requires READ on the asset.

Other reads:

- `GET /renditions/{id}` requires READ on the asset.
- `GET /asset-exports/{id}` is restricted to its owner.
- READ on rendition definitions follows the collection ACL.
- Listing asset policies requires EDIT on the workspace.
- Attribute filter rules are listed for editable workspaces only.
- `GET /tags/{id}` requires READ on the workspace.
- Profiles and profile items require READ.
- Pages require READ, and their `ownerId` is not writable.
- Asset data templates require READ on the workspace, both to read and to
  create them.

Anonymous users:

- Only PUBLIC assets and collections are visible, even in a public workspace.
- `POST /assets/entities` no longer resolves users.
- `/uploads*` requires an authenticated user (in the three APIs).

## Expose API

- `PUT` is replaced by `PATCH` and the `hydra:` prefix is gone (see
  [API changes](#api-changes-api-platform-5)).
- `order[updatedAt]` is removed from `GET /publications`.
- `/publications/slug-availability/{slug}` only answers `GET`.
- Anonymous `GET /publications/{id}` and `GET /assets/{id}` now send
  `Cache-Control: public, max-age=600` (capped by the expiration) with
  `Vary: Authorization, X-Passwords`. Check the behaviour of any cache or proxy
  in front of Expose.

## Uploader API

Routes, methods and bodies of `POST /assets`, `/assets/{id}/ack`, `/commit`,
`/commits/{id}/ack`, `/form/validate` and `GET /targets/{id}/form-schema` are
unchanged. Only `PUT` → `PATCH`, the `hydra:` prefix and filter typing change:
`acknowledged` is a strict boolean, `target` accepts an id or an IRI.

## Multipart uploads are planned by the API

Clients no longer decide the part size of a multipart upload nor ask the API
for a presigned URL before each part. `POST /uploads` now answers with the part
size (`chunkSize`) and the presigned PUT URL of every part (`urls`, keyed by
part number, valid for 3 hours). `POST /uploads/{id}/parts` with `{"from": n}`
renews the URLs from part `n` (expired URLs, resumed uploads).

`POST /uploads/{id}/part` still works for clients of the previous version but is
deprecated and will be removed.

## Collections and stories are ordered

This change shipped in 4.5.0: it is only relevant when upgrading from 4.4 or
earlier.

Assets now hold a rank inside each collection and story they belong to
(`collection_asset.position`, seeded by the migration from the creation order),
and the `asset` index gets a new nested `collectionPositions` field.

Re-populate the `asset` index right after running the migrations: until then the
index has no mapping for `collectionPositions`, so sorting by `@position` (story
carousel, `GET /collections/{id}/assets`, `GET /assets/{id}/story-assets`) fails
or serves an incomplete order.

```bash
bin/console fos:elastica:populate --index=asset
```

`@position` sorting requires the search to be narrowed down with `collection`
(exact membership) or `story`: `parent`, which spans the sub-collections, is
rejected.

## Notification emails

Every email body is now wrapped in a branded layout (logo, subject, card, a
call-to-action built from the `uri` / `action` blocks, plain-text alternative).
An overridden template (`template_namespace`) that renders a full HTML document
ends up nested in it: keep only the body fragment.

Optional settings `alchemy_notifier.email.app_name` and
`alchemy_notifier.email.public_url` are available, and the logo is served by
the public route `GET /notifications/email-logo`.

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
- Workflow jobs accept a new `continue-on-skip` key; `JobState` gets a
  `skipReason`.
- storage-bundle: `MultipartUploadPartAction` and `MultipartUploadCancelAction`
  are removed.
- api-test: `CompactResultPrinter` is removed.
- admin-bundle: `ChildPropertyEntityConfigurator` is removed.
- webhook-bundle: `EntitySerializer::convertChangeSetToPhpValue()` is removed.
- Symfony 8: `eraseCredentials()` is gone from `JwtUser` and `JwtOauthClient`;
  voters use the new `voteOnAttribute` signature.
- `@alchemy/api`: `ApiConstant.HydraTitle` / `HydraDescription` →
  `ErrorTitle` / `ErrorDescription` (`title` / `description`).

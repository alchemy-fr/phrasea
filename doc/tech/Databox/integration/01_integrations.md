---
title: 'Databox Integrations'
---

# Databox Integrations

## Introduction

In Databox, an integration is a functional bridge either between Phrasea modules or between Phrasea and a third-party service.
Integrations can be triggered by workflow events (for example during asset ingest) or by user actions.
Their goal is to automate technical steps that would otherwise require manual operations between Phrasea and external tools.

## Why integrations are useful

Integrations are used to interconnect Phrasea with external systems in a reliable and repeatable way.
They provide practical value by:

- Automating processing tasks during file ingest.
- Normalizing data exchanged between Phrasea and external services.
- Keeping synchronized states between Phrasea objects and external resources.

Rendition references in integration configurations (`rendition`, `renditions`, `applyToRenditions`, `labels.rendition`...) are written by name in YAML but persisted as rendition definition IDs (see `RenditionConfigNormalizer`): renaming a rendition definition does not break the integrations using it, and the YAML shown back to users still displays names.

## How integrations run

An integration is a service implementing `App\Integration\IntegrationInterface`, registered under its name (`#[AsTaggedItem(index: self::NAME)]`, e.g. `core.rendition`). A **workspace integration** (`App\Entity\Integration\WorkspaceIntegration`) is an instance of it, with its name, configuration, `needs`, `if` condition and `enabled` flag, in a workspace or, for the administrators, instance-wide. The catalog description of each integration is translated in the `integrations` domain of the API (`databox/api/translations/integrations.*.yaml`).

An integration is run in one or more of these ways:

- **Workflow jobs**: an integration implementing `WorkflowIntegrationInterface` adds jobs to the `asset-ingest` and `attributes-update` workflows of its workspace (some only to `asset-ingest`), after the integrations of its `needs` and only when its `if` condition holds; see [Jobs of the integrations](../workflows.md#jobs-of-the-integrations).
- **User actions**: `POST /integrations/{integrationId}/actions/{action}` (`IntegrationActionController`, `IntegrationManager::handleAction()`), for the integrations implementing `UserActionsIntegrationInterface`. It requires the `INTERACT` permission on the workspace integration (`WorkspaceIntegrationVoter`); an action on a file (`fileId`) also requires `EDIT` on the file, and answers 400 for a file that cannot be processed (`FileProcessingGuard`, see [Quarantine](../quarantine.md#file-analysis)).
- **Contexts** (`IntegrationContext`, `getSupportedContexts()`): where the client offers the integration. `asset-view`: a tab of the asset side panel, run on the displayed file (AWS Rekognition, Face Recognition, Remove BG, Toast UI Photo Editor); `basket` and `automation`: Expose.
- **Incoming requests**: a few integrations expose their own endpoint, called by another service (Uploader, Asset import, Phraseanet Renditions, AWS Transcribe).

### Errors and automatic disabling

When a job of an integration fails with an `InvalidArgumentException`, a `RateLimitExceededException` (budget limit reached) or a `UserNotifyableException`, `AbstractIntegrationAction` notifies the owner of the workspace integration and appends the error to it (`ErrorDisableHandler`). After 3 errors, the integration is disabled; enabling it again clears its errors. The other exceptions only fail the job.

### Budget limit

The integrations calling a paid service (AWS Rekognition, AWS Transcribe, AWS Translate, Remove BG) have a `budgetLimit` node, enabled by default, checked by `ApiBudgetLimiter` (Symfony RateLimiter, one limiter per workspace integration) before each call:

| Key | Default | Description |
| --- | --- | --- |
| budgetLimit.enabled | true | Limit the calls |
| budgetLimit.policy | sliding_window | `token_bucket`, `fixed_window`, `sliding_window` or `no_limit` |
| budgetLimit.limit | 10 (5 for Remove BG) | Calls allowed per interval |
| budgetLimit.interval | 1 day | Interval of the limit, e.g. `12 hours`, `3 months` |

Each call consumes one token; when the limit is reached, the job fails (see above).

### Credentials

The credentials of the external services (`accessKeySecret` of the AWS integrations, `apiKey` of Happyscribe and Remove BG, `token` of Phraseanet Renditions) are `secret` keys: save them as secrets and refer to them as `${NAME}`, see [Secrets & variables](#secrets--variables) and [Secrets and variables](../secrets.md).

## Rendition integration (core.rendition)

Display name: Rendition

This integration builds media renditions based on workspace rendition definitions.
For each rendition definition, it creates a workflow job and executes rendition generation through the rendition build action.

Main behavior:

- Loads rendition definitions for the workspace.
- Creates one workflow job per definition.
- Applies parent/child dependencies between jobs when a definition has a parent.
- Runs generation in dependency order so child renditions wait for parent renditions.

Operational notes:

- Rendition definitions must exist in the workspace configuration.
- Dependency chains are strict: a missing parent definition breaks the expected build sequence.
- This integration is part of workflow orchestration, not a manual user action.

## Expose integration (phrasea.expose)

Display name: Expose

This integration connects Phrasea baskets to the Expose publication service.
It is a basket-oriented integration driven by user actions and asynchronous synchronization.

Main behavior:

- Supports user actions: sync, force-sync, and stop.
- On sync, creates a publication in Expose and stores the publication ID in integration data.
- Dispatches asynchronous basket synchronization messages after creation and on basket updates.
- Transforms stored publication data to expose useful URLs (public URL and edit URL).

Operational notes:

- Requires a valid OAuth integration token to call Expose API endpoints.
- Requires configuration of baseUrl, clientId, and clientUrl.
- The Redirect URI generated by Phrasea must be allowed in Expose OAuth client settings.
- The user actions work in the basket context only.
- The saved search automations publish through the same integration with their `expose_publish` action ("Publish to Expose", `src/Automation/Action/ExposePublishAction.php`): the results are published to an existing publication, with the Expose access of the owner of the saved search, who must be an editor of it. Options: `ignoreRoi` (whole files) and `removeMissing` (remove the assets that left the results, scope "all" only).
- What is published, what is skipped, and the Expose side of the publications: see [Publishing from Databox](../../Expose/publications.md#publishing-from-databox).

OAuth client (`clientId`):

- `clientId` is the client ID of a **public** Keycloak client with the **standard flow** (the one provisioned by the configurator, `expose-integration`, by default: `${DATABOX_EXPOSE_INTEGRATION_CLIENT_ID}`). Its redirect URIs must allow `<Databox API URL>/integrations/{id}/code` (shown as "Redirect URI" once the integration is saved).
- When the Keycloak account of the user has the `view-clients` role of the `realm-management` client, the form lists the compatible clients (enabled, public, standard flow, the built-in Keycloak clients excepted): the ones allowing the redirect URI of the integration come first, the others are flagged "Redirect URI not allowed". The clients whose access tokens Expose refuses are flagged "Refused by Expose" (and listed after the accepted ones): Expose only accepts the clients having the `expose-api` default client scope, which puts Expose in the audience of their tokens, see [Audiences](../../Authentication/clients.md#audiences). When Expose refuses the token anyway (e.g. the scope was added after the user authorized the integration), Databox answers `400` with a message naming the client and the scope, both when creating a publication and through the proxies. With `manage-clients`, the form also creates a client, with the `expose-api` scope. `realm-admin` includes both roles. Without them, the field stays a plain text field.
- A client created from the form is public, with the standard flow only (no implicit flow, direct access grants nor service account), `rootUrl` the Databox API URL and the redirect URI `<Databox API URL>/integrations/*`, so that it can be created before the integration is saved and shared by several integrations. It carries the attribute `phrasea.created_by: databox`.
- Databox calls the Keycloak admin API (`GET`/`POST /oauth-clients`) with **the token of the user**: Keycloak applies the user's own `realm-management` roles, there is no service account involved. While an admin impersonates another user, neither listing nor creating is allowed.

## Read Metadata integration (core.read_metadata)

Display name: Read Metadata

This integration extracts technical metadata from the asset source file during asset ingest workflows.
It reads raw file metadata, normalizes it, then stores normalized metadata on the source file entity.

Main behavior:

- Only runs on asset ingest workflows.
- Executes only when the asset has a source file.
- Fetches the source file, reads metadata, normalizes metadata, and persists it.
- Triggers attribute initialization after metadata extraction in the workflow chain.

Operational notes:

- If no source file exists, the action is skipped.
- Metadata is stored on the file linked to the asset source.
- File retrieval must succeed for extraction to complete.

## Uploader integration (phrasea.uploader)

Display name: Uploader

This integration connects Phrasea to an external uploader service that sends incoming commits to Phrasea.
It is configuration-driven and exposes a target endpoint that the uploader service can call.

Main behavior:

- Defines uploader connection settings such as base URL, collection target, and a security key.
- Generates a secure default security key when not explicitly configured.
- Exposes a Target URL and authorization information for the uploader-side setup.
- Validates uploader API URL format before activation.

Operational notes:

- Requires a valid base URL for the uploader API.
- Uses an ApiKey authorization scheme.
- Security key must match on both Phrasea and uploader configurations. The API checks it itself: it is written in full (or refers to a variable), never to a secret (see [Keys read by the API](#keys-read-by-the-api)).
- The target endpoint answers 404 when the integration is disabled.
- Only the Uploader of the instance (`UPLOADER_API_URL`) and its storage (`S3_ENDPOINT`) may be reached on the private network: any other base URL, and the file URLs it answers, must be public.
- The user sent with a commit (the uploading user) becomes the owner of the assets, and must be allowed to create assets in the destination collection or workspace (and to edit the asset for a rendition substitution); otherwise the ingestion fails.

Values of the entity lists, for the forms filled through an upload link (no user to call Databox on behalf of):

- The Uploader reads the usual `GET /attribute-entities` with `Authorization: ApiKey <integrationId>:<securityKey>` (`IntegrationAuthenticator`): 404 when the integration is unknown, disabled or not an Uploader one, 403 with another key. Without the integration ID, the key alone is no credential (the request stays anonymous).
- The integration acts as its owner (`IntegrationUser`, with the roles and groups of the owner, read from Keycloak as for an impersonation and kept 60 seconds by `IntegrationOwnerProvider`, so that a change of the owner is seen up to a minute late; 403 when the owner is disabled): it reads the approved values only (never the pending or rejected ones, even when the owner edits the list or created them: nobody is behind the integration, see `AttributeEntityVisibilityResolver`), of the lists of the workspace of the integration only; any other workspace answers an empty collection.
- Only the API Platform operations flagged with `IntegrationAuthenticator::OPERATION_ACCESS` in their `extraProperties` accept this key (the `/attribute-entities` collection): it is refused with 403 anywhere else. The usage counts (`usage=1`) are refused with 400, as they would count the values on assets the holder of the key cannot see. See [Integration keys](#integration-keys).

## Asset import integration (core.asset_import)

Display name: Asset import

This integration exposes a webhook receiving import instructions (JSON or NDJSON, one entry per asset):
the assets are created and their files downloaded from URLs. See [Import of assets](../import.md).

Main behavior:

- `POST /integrations/asset-import/{integrationId}/imports`, authenticated by the `Authorization: ApiKey <securityKey>` header; the format is given by the `Content-Type` (`application/x-ndjson`, `application/json`).
- Answers 202 with the ID of the created import, followed in the Imports tab of the workspace (`GET /asset-imports/{id}`).
- The entries are imported as the owner of the integration, in the configured `collectionId` (the workspace root otherwise) with the configured default `privacy`.
- Generates the security key at creation, and a new one when the integration is duplicated.

Operational notes:

- Requires a workspace; the `collectionId` must belong to it.
- 401 without the ApiKey header, 403 on a wrong key, 404 for an unknown or disabled integration, 415 on an unsupported Content-Type.
- The API checks the `securityKey` itself: it is written in full (or refers to a variable), never to a secret (see [Keys read by the API](#keys-read-by-the-api)).

## Toast UI Photo Editor integration (tui.photo-editor)

Display name: Toast UI Photo Editor

This integration enables editing actions from the asset view using Toast UI Photo Editor.
It handles save and delete user actions and tracks generated edited files as integration data.

Main behavior:

- Supports user actions: save and delete.
- On save, creates a new file version and stores a link in integration data.
- On delete, removes integration data for a previously saved edited output.
- Pushes real-time file updates after save and delete actions.

Operational notes:

- Scope is asset view context.
- Save and delete actions require valid user context and integration data identifiers.
- Unsupported actions are rejected.

## Similarity integration (core.similarity)

Display name: Similarity

This integration computes a visual embedding (CLIP vector) for each ingested asset and stores it in the asset Elasticsearch document (dense_vector field).
It powers the **Similar** tab of the asset view and the **Find similar** action of the assets, through a kNN search that enforces the same ACL filters as the regular search.

Main behavior:

- Adds a workflow job on asset ingest (`heavy_low` queue), depending on the configured rendition job (required) and on the thumbnail rendition jobs.
- Sends the rendition image to the similarity-embedder service (self-hosted, CLIP) to compute the vector. When the configured rendition is not an image (the preview of a document is a PDF, the one of a video is a video), the thumbnail rendition (`useAsThumbnail`) is used instead: documents and videos are searchable by similarity too.
- Stores the vector in database (asset_embedding table) and reindexes the asset in Elasticsearch.
- Similar assets are served by `GET /assets/{id}/similar` using an Elasticsearch kNN query filtered by permissions, and searched with the `@similar` built-in attribute of AQL (`@similar = "<asset ID>"`, `SimilarBuiltInAttribute`), which ranks the results by similarity.

Configuration:

| Key | Default | Description |
| --- | --- | --- |
| rendition | (required) | Rendition used to compute the embedding; when it is not an image (document, video), the thumbnail is used instead |

Client:

- A workspace has `similarity: true` (workspace output) when it has an enabled Similarity integration: only then does the client show the similarity features of its assets.
- The **Similar** tab of the asset side panel shows the 24 closest assets, and **Search similar assets** opens the search of the assets similar to the displayed one.
- **Find similar**, in the actions of a single asset, opens the asset search with a `@similar` condition on that asset; the results show their similarity score.

Operational notes:

- Requires the `similarity-embedder` (maintained in [alchemy-fr/phrasea-similarity-embedder](https://github.com/alchemy-fr/phrasea-similarity-embedder)) and `elasticsearch` (8.12 or later) services.
- Backfill existing assets with `bin/console app:similarity:index` (assets already embedded are skipped unless `--force`).

## Face Recognition integration (core.face_recognition)

Display name: Face Recognition

This integration detects the faces of image assets and recognizes the persons that users have already named, through a self-hosted service (no data leaves the platform).
It is the self-hosted counterpart of the "faces" category of AWS Rekognition, with recognition on top of detection.

There is no face-specific data model nor API: **a face is a value of the configured entity attribute** of the asset (`attribute`), and **a person is an entity** of the list that attribute is bound to. Each face value carries the person (the entity ID), the box of the face as a `face` annotation (an oval drawn by the client), the detection or recognition confidence, and the face embedding (`attribute.embedding`, never exposed by the API; only indexed in the internal `face` index, see below). Everything else goes through the generic attribute and entity APIs.

Main behavior:

- Sends the configured rendition (optional, falls back to the source file), downscaled to `maxImageSize` pixels, to the `face-recognition` service, which returns for each face a bounding box, a detection confidence and a 512-d ArcFace embedding.
- Runs on asset ingest when `processIncoming` is enabled (workflow job depending on the rendition job), or on demand from the asset view ("Detect faces", user action `analyze`).
- Each detected face is recognized among the faces of the workspace (cosine similarity, `matchThreshold`), searched in the `face` Elasticsearch index (kNN, `FaceIndex`) rather than compared one by one: its nearest faces and its nearest faces named by a user, whose person and origin are then read from the database. It joins the person of the closest face a user named, or else the closest face of a person nobody has named yet. Otherwise it starts a new **pending entity** ("Unknown person · 3f2a", `unknownLabel`): the pending persons are the groups of look-alikes still to be named; being pending, they never show up in the search facets, and they are removed as soon as no face references them any more. Only the faces named by users (`origin: human`) are references: a recognized face never becomes one, so that the recognition cannot drift.
- Users name a face by changing the value of its attribute (`PATCH /attributes/{id}`, `/attributes/batch-update`), to an existing entity or to one they create (`POST /attribute-entities`): the face becomes a reference of the person (`origin: human`, `originUserId`, confidence 1) and, when `autoPropagate` is enabled, the person is propagated asynchronously to the similar faces of the workspace that no user named. Deleting the value (`DELETE /attributes/{id}`) forgets the face.
- Users name a whole unknown group by renaming and approving its pending entity (`PATCH /attribute-entities/{id}` with `value`, `note` and `status: 0`): its most confident face becomes the reference and is propagated. Attaching the group to an existing person is the entities merge (`PUT /attribute-entities/{id}/merge`), which re-points its faces. Two persons with the same name are told apart by their `note` (the same value may appear twice in a list).
- In the client, faces are named from the asset view. The **Face Recognition** tab of the side panel (`FaceRecognitionPanel`) lists the faces of the asset, each with its crop, its person picker ("Search or type a name…": an existing person, or a new one created from the typed name), **Confirm the person** for a recognized face (which makes it a reference) and **Forget this face**; **Detect faces** runs the detection again. On the image itself, the faces are drawn as ovals: selecting one opens the same person picker in a floating panel (`FaceAnnotationControl`), for the users who may name the faces. The persons are managed from the exploration page of their entity list (`/entity-lists/{id}/explore`, "Explore" on the list in the workspace entities, under an entity facet or in an entity pinned filter): each person with a few of the assets showing it, renamed (`value`, `note`) or merged into another one by the list editors; the values grid of the list (workspace management, entities) covers the rest (approval, bulk edits). Both are built on `GET /attribute-entities?list=…&status=…&usage=1` (with the number of faces, `usage`) and, for the pending persons, which are not indexed, `GET /attributes?definition[]=…&value=…`. Pending persons are listed to the users who may edit the attributes of the workspace, so that they can name them. In the values of an asset, the name of a pending person is only sent to the managers of the entity list and to its creator (the users who may edit the entity); the others get a null value, shown as "Pending".
- Permissions are those of the attributes and the entity lists: reading the faces of an asset requires reading its face attribute (asset readable, attribute policy public or granted), naming requires editing it, creating or renaming a person requires the rights on the list.
- Re-running the detection on an asset keeps the person of the faces that overlap a previous detection (what users said included). Replay the recognition after a change of `matchThreshold` with `bin/console app:face-recognition:relink` (no detection involved).
- When `autoPhoto` is enabled, every person with a face, unknown and pending ones included, gets the photo of its best face (the entity image shown by the chips, the exploration cards and the facets): the most confident face, so a face named by a user first, cropped on the server from the file the detection ran on (configured rendition or source, EXIF-oriented) with the framing of the client (a square centered on the box, 1.8 times its longest side, shifted inside the image at its edges), then processed like an uploaded image. It is asked for asynchronously (`FaceRecognitionPhoto` message, `heavy` transport) after a detection, when a user names, moves or deletes a face, approves a pending person, after a merge and after a relink.
- An automatic photo remembers the face it was cropped from (`image_set.source_attribute_id`). It is replaced only when that face no longer shows the person (deleted, moved to another person, or its asset deleted), or when it comes from a recognized face and the person now has a face named by a user: a more confident recognized face alone does not replace it. A photo uploaded by a user is never replaced; once a user deletes it, the next of these events gives the person an automatic one. Disabling `autoPhoto` keeps the existing automatic photos.

Configuration:

| Key | Default | Description |
| --- | --- | --- |
| attribute | (required) | Slug of a multi-valued attribute of type `entity`, receiving one value per detected face; its entity list holds the persons |
| unknownLabel | Unknown person | Name of a person nobody has named yet (a short code is appended) |
| rendition | (none: source file) | Rendition sent to the service (must be an image) |
| maxImageSize | 2048 | Longest side (pixels) of the downscaled JPEG copy actually sent to the service |
| processIncoming | false | Detect faces on all incoming assets |
| minConfidence | 0.5 | Minimum detection confidence for a face to be kept |
| matchThreshold | 0.5 | Minimum cosine similarity with a known face to recognize the same person (0 disables recognition: every face is a new unknown person) |
| autoPropagate | true | Apply a newly named person to the similar faces of the workspace |
| autoPhoto | true | Give the persons without a photo the photo of their best face, cropped from its asset (a photo uploaded by a user is never replaced) |

Operational notes:

- Requires the `face-recognition` service (maintained in [alchemy-fr/phrasea-face-recognition](https://github.com/alchemy-fr/phrasea-face-recognition), InsightFace `buffalo_l` model pack on CPU; image `FACE_RECOGNITION_IMAGE`, `face-recognition` Docker Compose profile). Databox reaches it through `FACE_RECOGNITION_URL`.
- Backfill existing assets with `bin/console app:face-recognition:index` (add `--sync` to run in the foreground).
- The faces are indexed in the `face` Elasticsearch index (definition, asset, origin and embedding: 512 dimensions, HNSW in full precision) by the detection itself, which refreshes it so that the next detection recognizes them, and by the naming and the deletion of faces, the deletion of their asset included. The faces of the trashed assets stay in it and keep recognizing their person (but a person never gets its reference nor its photo from them). A face deleted by a DQL query (`delete` action of `/attributes/batch-update`, deleted workspace) may stay in it: the recognition ignores the faces the database no longer has, and a populate (`bin/console fos:elastica:populate --index=face`) rebuilds the index from the database, as after an upgrade. Until this index exists, nothing is written to it and the detections that have to recognize a face fail.
- The propagation of a named person also searches the similar faces in this index, and scans every face of the workspace only when more than 10,000 of them are similar. The relink (`app:face-recognition:relink`) still compares every face to every face processed before it.
- Give the existing persons their photo with `bin/console app:face-recognition:photos` (`--workspace` to limit it to a workspace): it queues the persons that have no photo or an automatic one, in the integrations having `autoPhoto` enabled.

## Phraseanet Renditions integration (phraseanet.renditions)

Display name: Phraseanet Renditions

This workflow integration has the renditions of incoming assets built by a Phraseanet instance (`baseUrl`, API key `token`).

Main behavior:

- `api` method: the source file is submitted to the subdefinition service of the Phraseanet API (`databoxId`), and each rendition comes back on `POST /integrations/phraseanet/{integrationId}/renditions/incoming/{assetId}`, authenticated by a token signed for the asset.
- `enqueue` method: Phraseanet fetches the asset (`collectionId`) with a token signed for the asset, then notifies each generated sub definition (`record.subdef.created` event) on the **Webhook URL** shown in the integration information. The rendition is then downloaded from the permalink of the event.
- The Webhook URL carries the `webhookSecret` of the integration in its `token` query parameter: `POST /integrations/phraseanet/{integrationId}/events?token=<webhookSecret>`. The secret is generated when the integration is created, and a new one when it is duplicated.

Operational notes:

- Set the Webhook URL as is in Phraseanet, and keep it private: whoever holds it can replace the renditions of the assets being processed.
- 401 without the `token` or with a wrong one, and for an integration without `webhookSecret` (add one to its configuration, e.g. `openssl rand -hex 32`, then copy the new Webhook URL into Phraseanet); 404 for an unknown integration.
- The API checks the `webhookSecret` itself: it is written in full (or refers to a variable), never to a secret (see [Keys read by the API](#keys-read-by-the-api)).
- Only permalinks on the host and port of `baseUrl` are downloaded. The type and size given by the event are ignored: they come from the downloaded file, whose content is checked like any imported file.

## File Analyzer integration (core.file_analyzer)

Display name: File Analyzer

This workflow integration checks the source file of the incoming assets with analyzers, and quarantines, trashes or deletes the assets whose file is rejected. See [File Analyzers](../FileAnalyzers.mdx) for the options of each analyzer and [Quarantine](../quarantine.md) for the asset statuses.

Main behavior:

- Adds a job on asset ingest only (`heavy` queue), whose outputs are `analysis` (the analysis of the file) and `analyzed` (whether it ran).
- Runs each analyzer of `analyzers` on the source file. The analyzers are `checksum`, `debug`, `doc_unique_id`, `filename`, `image_colorspace` and `image_dimension` (`App\Border\FileAnalyzer\Analyzer\*`); each item has the `name` of an analyzer, its own options, `max_severity` (the highest severity its findings may get, default `critical`) and `actions_on_reject`.
- A file is analyzed again only when the configuration changed since its last analysis (hash of the file ID and the configuration), or when the job is rerun. A file refused by the content check is never analyzed; a file not stored in the Databox storage is marked skipped.
- An accepted file sets the asset status to `Accepted`. A rejected file ends the job in `failure` and applies the actions on reject: those of the first analyzer declaring some, plus the `actions_on_reject` of the integration. `quarantine` quarantines the asset, `move_to_trash` moves it to the trash, `delete` deletes the asset and its file (and then excludes the other actions).
- When `skipNonSourceFiles` is enabled, a file that is not the source file of the asset (a rendition) is marked skipped instead of analyzed.

Configuration:

| Key | Default | Description |
| --- | --- | --- |
| analyzers | (none) | List of analyzers, each with its `name` and options (the form offers one variant per analyzer, see [Configuration form & schema](#configuration-form--schema)) |
| actions_on_reject | (none) | `quarantine`, `move_to_trash` and/or `delete`, applied when the file is rejected |
| skipNonSourceFiles | false | Skip the analysis of the files that are not the source file of the asset |

Operational notes:

- A workspace created with **Requires file analysis** gets a File Analyzer integration (`checksum` and `doc_unique_id`, quarantine on rejection), see [File analysis](../quarantine.md#file-analysis).
- `bin/console app:file:unblock-analyses` re-triggers the analyses left pending, see [Stuck analyses](../FileAnalyzers.mdx#stuck-analyses).

## Moderation integration (core.moderation)

Display name: Moderation

This workflow integration holds the incoming assets in their workflow for a human review.

Main behavior:

- Adds two jobs to the workflows of its workspace (asset ingest and attributes update): `core.moderation:{integrationId}` and `core.moderation`, both running `ModerationAction`, which retains its job (`RunContext::retainJob()`): the job stays running, and the jobs needing it wait.
- A retained job is continued from outside the workflow by `WorkflowOrchestrator::continueJob()`; no endpoint of the API calls it for the moderation jobs, so the assets stay held.

Configuration:

| Key | Default | Description |
| --- | --- | --- |
| emails | (required) | Email addresses of the people to notify when an asset comes in (each one validated as an email) |

Operational notes:

- The catalog describes the integration as notifying the moderators by email, but no code sends to the `emails` addresses.

## Watermark integration (core.watermark)

Display name: Watermark

This workflow integration stamps the value of an attribute as a text watermark on renditions of the asset.

Main behavior:

- Adds a job to the asset ingest and attributes update workflows (`heavy` queue), skipped when the asset has no source file.
- Reads the value without locale of the `attributeName` attribute: an empty value stamps nothing.
- For each rendition of `applyToRenditions`, draws the text (Roboto font, centered on `position`) on the rendition image and replaces the file of the rendition with the stamped copy. A rendition missing on the asset fails the job: declare the Rendition integration in the needs of the Watermark integration so that the renditions are built first.

Configuration:

| Key | Default | Description |
| --- | --- | --- |
| attributeName | (required) | Slug of the attribute whose value is stamped |
| applyToRenditions | (required, one at least) | Names of the renditions to stamp |
| fontSize | 14 | Font size in pixels (a `px` suffix is accepted) |
| color | #000000 | Hexadecimal color of the text |
| position.top | 50% | Vertical position of the center of the text: a percentage of the height, or pixels |
| position.left | 50% | Horizontal position of the center of the text: a percentage of the width, or pixels |
| regenerateOnUpdate | true | Read by no code: the job runs in the attributes update workflow whatever its value |

## Blurhash integration (blurhash)

Display name: Blurhash

This workflow integration computes a [BlurHash](https://blurha.sh/), a compact blurred placeholder of the image, and stores it in an attribute.

Main behavior:

- Adds a job to the asset ingest and attributes update workflows (`heavy` queue), skipped when the asset has no source file or when its source is not an image.
- Computes the BlurHash (4 by 3 components) of the configured rendition, or of the source file, downscaled to 100 pixels at most, and writes it to the `attribute` attribute as a machine value (`originVendor` `blurhash`, `originVendorContext` `v1.0`).
- The Databox client does not read this attribute: it is a value for the API consumers.

Configuration:

| Key | Default | Description |
| --- | --- | --- |
| rendition | (none: source file) | Rendition to compute the BlurHash from |
| attribute | blurhash | Slug of the attribute receiving the BlurHash |

## AWS Rekognition integration (aws.rekognition)

Display name: AWS Rekognition

This integration detects labels, texts and faces in images with Amazon Rekognition (`DetectLabels`, `DetectText`, `DetectFaces`).

Main behavior:

- Three categories, enabled separately: `labels`, `texts` and `faces`.
- Workflow: on asset ingest only, one job per enabled category whose `processIncoming` is set (`heavy_low` queue, job ID suffixed with the category), skipped when the asset has no source file or when its source is not an image. The job sends the `rendition` of the category (the source file otherwise), and fails when the asset does not have that rendition yet.
- User action `analyze` (asset view, with `fileId` and `category`): dispatches a `RekognitionAnalyze` message for the displayed file and pushes the result on the channel of the file when done.
- The result of each category is stored as integration data of the file: a file already analyzed for a category is not sent to AWS again.
- In the workflow only, the labels and the texts are written to their `attributes`: the values a previous analysis of the same category wrote (machine values of `aws.rekognition`) are replaced by the labels (names) or the text lines (`LINE` detections) whose confidence reaches the `threshold` of the attribute, with their confidence and their bounding boxes as rectangle annotations; the values set by users are kept. The faces stay integration data.
- In the client, the **Rekognition** tab of the asset view runs **Detect labels**, **Detect texts** and **Detect faces** on the displayed file, lists the results and draws them on the asset (**Show on the asset**).

Configuration:

| Key | Default | Description |
| --- | --- | --- |
| accessKeyId | `${AWS_ACCESS_KEY_ID}` | AWS IAM access key ID |
| accessKeySecret | `${AWS_ACCESS_KEY_SECRET}` | AWS IAM secret access key (`secret` key) |
| region | eu-central-1 | AWS region, among the regions offering Rekognition listed by the integration |
| labels.enabled, texts.enabled, faces.enabled | false | Enable the category |
| labels.rendition, texts.rendition, faces.rendition | (none: source file) | Rendition sent instead of the source file |
| labels.processIncoming, texts.processIncoming, faces.processIncoming | false | Analyze all incoming assets |
| labels.attributes, texts.attributes | (none) | Attributes receiving the results: `name` (slug of a multi-valued attribute) and `threshold` (minimum confidence, from 0 to 1) |
| budgetLimit | enabled, 10 per day | See [Budget limit](#budget-limit) |

Operational notes:

- Rekognition refuses images above 5 MiB. A category with a `rendition` is validated on save: the integration must declare the Rendition integration in its needs, and the rendition must be built (not the source file) with an `image` family resized by an `imagine` `thumbnail` or `resize` filter to 3000 pixels at most.
- The IAM user needs the Rekognition detection rights in the region.

## AWS Transcribe integration (aws.transcribe)

Display name: AWS Transcribe

This workflow integration transcribes the speech of video files with Amazon Transcribe, and stores the detected language, the transcript and the subtitles in attributes.

Main behavior:

- Adds a job to the asset ingest and attributes update workflows (`heavy_low` queue), skipped when the asset has no source file or when its source is not a video.
- Copies the source file to the `workloadS3Bucket` bucket (key `workload/…`), then starts a transcription job with language identification and `vtt` and `srt` subtitles, tagged with the IDs of the asset and the file.
- Amazon Transcribe announces the end of the job asynchronously: its job state change events (`source: aws.transcribe`) reach Databox through an SNS topic subscribed to `POST /integrations/aws-transcribe/{integrationId}/events`. When a job is `COMPLETED`, the worker reads it and writes, as machine values, each to its configured attribute: the identified language (a locale, with the identification score as confidence) and, in that locale, the transcript (the JSON file of Amazon Transcribe) and the subtitles.

Configuration:

| Key | Default | Description |
| --- | --- | --- |
| accessKeyId | `${AWS_ACCESS_KEY_ID}` | AWS IAM access key ID |
| accessKeySecret | `${AWS_ACCESS_KEY_SECRET}` | AWS IAM secret access key (`secret` key) |
| region | eu-central-1 | AWS region, among the regions offering Transcribe listed by the integration |
| workloadS3Bucket | (required) | S3 bucket receiving the copies of the files to transcribe |
| attributes.language | (none) | Slug of the attribute receiving the language code |
| attributes.transcript | (none) | Slug of the attribute receiving the transcript |
| attributes.vtt | (none) | Slug of the attribute receiving the WebVTT subtitles |
| attributes.srt | (none) | Slug of the attribute receiving the SubRip subtitles |
| budgetLimit | enabled, 10 per day | See [Budget limit](#budget-limit) |

Operational notes:

- The events endpoint is public: it only accepts the messages signed by AWS SNS (403 otherwise), and ignores the events addressed to an integration that is not an AWS Transcribe one. The subscription confirmation of SNS is accepted automatically: a worker requests its `SubscribeURL` when it is on an `sns.<region>.amazonaws.com` host.
- The IAM user needs to write to the bucket, and to start and read the transcription jobs; Amazon Transcribe must be able to read the bucket.

## AWS Translate integration (aws.translate)

Display name: AWS Translate

This workflow integration translates the values of the translatable attributes into the missing locales with Amazon Translate.

Main behavior:

- Adds a job to the asset ingest and attributes update workflows.
- For each translatable attribute definition of the workspace, takes the first value found among `preferredSourceLanguages`, then the other enabled locales of the workspace, then the value without locale (in the `defaultSourceLanguage` language), and translates it into each locale of `translatedLanguages` (every enabled locale of the workspace when empty) having no value yet: an existing value is never overwritten. The values of a multi-valued attribute are each added in the target locale.
- The translations are machine values (`originVendor` `aws.translate`). A run consumes one call of the budget, whatever the number of translations.

Configuration:

| Key | Default | Description |
| --- | --- | --- |
| accessKeyId | `${AWS_ACCESS_KEY_ID}` | AWS IAM access key ID |
| accessKeySecret | `${AWS_ACCESS_KEY_SECRET}` | AWS IAM secret access key (`secret` key) |
| region | eu-central-1 | AWS region, among the regions offering Translate listed by the integration |
| defaultSourceLanguage | (required) | Language of the values without locale |
| preferredSourceLanguages | (required, may be empty) | Locales translated from first |
| translatedLanguages | (required, may be empty) | Locales to translate into (all the enabled locales of the workspace when empty) |
| budgetLimit | enabled, 10 per day | See [Budget limit](#budget-limit) |

## Happyscribe integration (happyscribe)

Display name: Happyscribe

This workflow integration transcribes audio and video files with [Happyscribe](https://www.happyscribe.com/), and stores the transcript (or subtitles) in an attribute, translated into the enabled locales of the workspace.

Main behavior:

- Adds a job to the asset ingest and attributes update workflows (`heavy_low` queue). The job does nothing for a source that is neither audio nor video, nor when the attribute already has a value in every enabled locale.
- Uploads the configured rendition, or the source file, to Happyscribe and creates a subtitle transcription in the source language: the value of the `sourceLanguageAttribute` attribute, or `defaultSourceLanguage`.
- Workers then poll Happyscribe (delayed Messenger messages): once the transcription is done, it is exported in `transcriptFormat` and written to the `attribute` attribute as a machine value. For a translatable attribute, each enabled locale without value gets the export of the transcription (in the source language) or of a Happyscribe translation of it (other languages).

Configuration:

| Key | Default | Description |
| --- | --- | --- |
| organizationId | (required) | ID of the Happyscribe organization |
| apiKey | (required) | Happyscribe API key (`secret` key) |
| transcriptFormat | vtt | Export format: `srt`, `txt`, `json`, `vtt`, `docx`, `pdf` or `html` |
| attribute | subtitle | Slug of the attribute receiving the transcript (mono-valued when it is translatable) |
| sourceLanguageAttribute | sourceLanguage | Slug of the attribute giving the source language of the asset |
| defaultSourceLanguage | en | Source language when the asset has no value for `sourceLanguageAttribute` |
| rendition | (none: source file) | Rendition sent instead of the source file |

Operational notes:

- Both attributes must exist in the workspace, otherwise the job fails. The source language is a 2-letter code, or a code such as `fr-FR`.

## Webhook integration (core.webhook)

Display name: Webhook

This workflow integration calls an external URL from the asset workflows, to plug in other processes.

Main behavior:

- Adds a job to the asset ingest and attributes update workflows.
- Sends a `method` request to `url` with the request `options`. With `includeInputs`, the JSON body gets `asset` (`id`, `name`) and `workspace` (`id`, `name`); with `includeOrigin`, `origin`: the Databox API URL.
- The job outputs `status_code` and `body`, the response, shown with the job in the workflow; a response with an error status does not fail the job.

Configuration:

| Key | Default | Description |
| --- | --- | --- |
| url | (required) | URL to call (a URL key, see [Secrets & variables](#secrets--variables)) |
| method | POST | HTTP method |
| options | (none) | Request options of the Symfony HTTP client: only `headers`, `query`, `json`, `body`, `timeout` and `max_duration` are kept |
| includeInputs | true | Add the asset and the workspace to the JSON body |
| includeOrigin | true | Add the Databox API URL to the JSON body |

Operational notes:

- Outside the `dev` environment, the request cannot reach a private network (`NoPrivateNetworkHttpClient`).
- The references (`${NAME}`) only resolve in the first-level keys: a value within `options`, such as a header, is sent as written.
- The saved search automations have their own `webhook` action, see [Call a webhook](../automations.md#call-a-webhook-webhook).

## Remove BG integration (remove.bg)

Display name: Remove BG

This integration removes the background of images with the [remove.bg](https://www.remove.bg/) service.

Main behavior:

- User action `process` (asset view, with `fileId`): dispatches a `RemoveBgCall` message for the file and pushes the result on the channel of the file when done.
- Workflow, when `processIncoming` is enabled: a job in the asset ingest and attributes update workflows (`heavy_low` queue), skipped when the asset has no source file or when its source is not an image, processing the source file.
- Sends the image (22 MiB at most) to `https://api.remove.bg/v1.0/removebg` and keeps the answer in the cache directory of the API, keyed by the checksum of the file: the same image is not sent again.
- Creates a new PNG file in the workspace (`<original name>-bg-removed.png`) and stores its ID as integration data of the processed file (`file_id`, returned as `file` with its `id` and `url`). The source file and the renditions are left unchanged.

Configuration:

| Key | Default | Description |
| --- | --- | --- |
| apiKey | `${REMOVE_BG_API_KEY}` | remove.bg API key (`secret` key) |
| processIncoming | false | Process all incoming images |
| budgetLimit | enabled, 5 per day | See [Budget limit](#budget-limit) |

Operational notes:

- The client shows this integration in the generic integration tab of the asset view, whose **Analyze** button sends the `analyze` action: the integration only supports `process`.

## Test asset operation integration (test.asset_operation)

Display name: Test asset operation

This workflow integration, meant for the development and the tests, writes a test value (confidence 0.42) to the `attribute` attribute (default `test`) of the assets having a source file, in the asset ingest and attributes update workflows.

## Configuration form & schema

The workspace administration (`/workspaces/:id/manage/integrations`) configures an integration with a **form generated from its configuration tree**, the YAML editor remaining available as an advanced mode (the two are converted into each other on the client, and both `config` and `configYaml` are accepted by the API).

`GET /integration-types/{id}` exposes the tree built by `buildConfiguration(NodeBuilder)` as a `schema` (`ConfigSchemaDumper`), inspired by JSON Schema without following it: a root `object` whose `properties` are the configuration keys, each node carrying a `type` (`string`, `integer`, `float`, `boolean`, `enum`, `object`, `list`, `any`), its `label`, `info`, `example`, `required`, `default`, `min`/`max`, `values`, `items` and `minItems`. An object with an `enabled` boolean (`canBeEnabled()` / `canBeDisabled()`) gets `toggle: "enabled"`; a `variableNode` or an array without definition is `any` and stays edited as YAML.

The UI hints are declared **on the tree itself** with `NodeDefinition::attribute()` and the keys of `App\Integration\Config\ConfigWidget`:

```php
use App\Integration\Config\ConfigWidget;

ConfigWidget::apply($builder->scalarNode('attribute'), ConfigWidget::Attribute, [
    'type' => 'entity',
    'multiple' => true,
    'entityList' => true,
])
    ->isRequired()
->end()
->scalarNode('rendition')
    ->attribute(ConfigWidget::ATTR_WIDGET, ConfigWidget::Rendition->value)
->end()
->scalarNode('apiKey')
    ->attribute(ConfigWidget::ATTR_SECRET, true)
->end();
```

- `widget` (`ConfigWidget`): `attribute` (slug of an attribute definition of the workspace, with `widgetOptions` restricting its `type`, `multiple` and `entityList`; the picker offers to **create** the attribute, and the entity list it needs, right from the form), `rendition` (name of a rendition definition), `collection` (collection ID), `email`, `language`, `oauth_client` (client ID of a Keycloak OAuth client, public with the standard flow: the text field gets "Choose…" and "Create…" buttons when the Keycloak account of the user can list or create the clients, see the Expose integration). On a prototype (`scalarPrototype()`), the widget applies to the items of the list (multiple picker, chips).
- `secret`: the form masks a value typed in clear (with a "show" button) and offers to **save it as a secret** (see [Secrets & variables](#secrets--variables)). The API does not redact a value stored in clear: `configYaml` and `configValues` are only given to who may edit the integration. Prefer a reference to a secret, except in a key read by the API.
- `url` (`ConfigWidget::ATTR_URL`, first-level keys only, not shown to the form): the value is a URL the integration calls or gives to the users (`baseUrl` of `phrasea.uploader`, `phraseanet.renditions` and `phrasea.expose`, `clientUrl` of `phrasea.expose`, `url` of `core.webhook`). In a workspace integration, it keeps the instance variables away from a host chosen by an editor (see [Secrets & variables](#secrets--variables)).
- `readByApi` (`ConfigWidget::ATTR_READ_BY_API`, first-level keys only, not shown to the form): the API reads the value itself, e.g. the key a webhook compares to its requests (`securityKey` of `phrasea.uploader` and `core.asset_import`, `webhookSecret` of `phraseanet.renditions`). It cannot refer to a secret (see [Keys read by the API](#keys-read-by-the-api)).
- An integration whose tree does not tell the whole story implements `ConfigSchemaCustomizerInterface::customizeConfigSchema(array $schema): array`: `core.file_analyzer` replaces the items of `analyzers` by `{type: object, discriminator: name, variants: {<analyzer>: …}}`, one variant per registered analyzer.

`GET /integrations/{id}` returns the stored configuration twice to its editors: `configYaml` and `configValues` (the same values, structured), so that an integration written in YAML reopens in the form with its values. A configuration refused by the tree (or by `validateConfiguration()` when it sets the path of its `InvalidConfigurationException`, e.g. `root.attribute`) answers a 422 whose violation `propertyPath` points to the option (`config.minConfidence`, `config.analyzers.0.name`), reported under the matching field.

## Secrets & variables

A string value of the configuration can refer to a named value instead of holding it: `${NAME}` (or the older `$NAME`), alone or within a text (`https://${HOST}/api`). `IntegrationConfig` resolves the references of the first-level keys when the integration reads its configuration, in the workspace of the integration (the instance ones for an instance-wide integration, without falling back from a workspace to the instance), in this order:

1. a **secret** (`WorkspaceSecret`) of that name;
2. a **variable** (`WorkspaceEnv`) of that name;
3. the environment variable `PWI_<NAME>` of the instance;
4. an empty string otherwise.

The references are resolved in a single pass: a resolved value holding `$` is kept as is.

The `PWI_<NAME>` variables are shared by every workspace (instance keys of AWS, Phraseanet…): in a **workspace** integration, they never leave for a host chosen by an editor of the workspace. So, for the URL keys (`url` attribute, see [Configuration form & schema](#configuration-form--schema)):

- a URL set to another value than its default refers to nothing: it is used as written, and saving a value holding `$` is refused (422 on `config.<key>`, "write it in full");
- a URL left to its default (e.g. `${UPLOADER_API_URL}`) still resolves its references, from the three sources;
- as soon as one URL is not its default, or its default refers to a secret or a variable of the workspace (which would redirect it), the other keys no longer resolve the `PWI_<NAME>` variables: such a reference gives an empty string, with a `warning` log. The secrets and variables of the workspace still resolve: an integration calling its own endpoint uses its own keys, saved as secrets of the workspace.

Any other key may end up where the editor reads it (a validation message, an error of the integration, a value written to an asset…). So, even with the URLs of the instance, the `PWI_<NAME>` variables only resolve in a `secret` key (a credential, e.g. `token` of `phraseanet.renditions`, `apiKey` of `remove.bg`) or in a key left to its default (e.g. `accessKeyId: ${AWS_ACCESS_KEY_ID}` of the AWS integrations, `clientId` of `phrasea.expose`); elsewhere they give an empty string, with a `warning` log. A workspace needing such a value in another key saves it as a variable of the workspace.

The API never returns a resolved reference: what it shows of the configuration (`configInfo`, the client configuration `config`, the validation messages) holds the values as written, e.g. the "Security Key" of `phrasea.uploader` reads `${UPLOADER_KEY}` when it refers to a variable; the references only resolve server-side, where the integration runs (`IntegrationConfig::getClientValue()` gives a value as written). The client configuration of `core.face_recognition` gives the slug of the attribute definition it found (`attribute`), null when there is none.

The instance-wide integrations (no workspace, administrators only) are not restricted.

A secret is encrypted with a libsodium sealed box: the API only has the public key (it encrypts, never decrypts, and never returns the value), the workers hold the secret key and decrypt it when they run the integration. A variable is stored in clear and returned with its value to the same users: it never holds a sensitive value.

| Endpoint | Rights |
| --- | --- |
| `GET /workspace-secrets?workspace={id}` | edit the workspace (`EDIT`): even the names are of use to the editors only |
| `GET /workspace-secrets?global=true` | administrators (instance secrets) |
| `GET /workspace-secrets/{id}` | as above, for the workspace of the secret |
| `POST /workspace-secrets` (`workspace` IRI or `null`, `name`, `value`) | as above |
| `PATCH /workspace-secrets/{id}` (`value`) | as above |
| `DELETE /workspace-secrets/{id}` | as above |
| `GET /workspace-envs?workspace={id}` / `?global=true`, `GET /workspace-envs/{id}` | as above, with the values |
| `POST /workspace-envs` (`workspace` IRI or `null`, `name`, `value`), `PATCH /workspace-envs/{id}` (`value`), `DELETE /workspace-envs/{id}` | as above |
| `GET /system-envs?workspace={id}` / `?global=true` | as the listings above: the names of the non-empty `PWI_<NAME>` environment variables of the instance, without the prefix nor the value, sorted, not paginated (the same for every scope) |

The listings need `workspace` or `global` (400 otherwise), sort by name and accept `limit`/`page`. The value of a secret is write only (an empty one is ignored), the one of a variable may be empty. The name cannot change once created (the references would silently break): a `PATCH` replaces the value, a different `name` answers 400. A name (of a secret or a variable) matches `^[A-Za-z_][A-Za-z0-9_]*$` (100 characters at most), so that both reference forms resolve it, and is unique in its workspace, instance ones included (validated, as SQL does not compare `NULL` workspaces). Deleting a secret or a variable still referenced is allowed: the references then resolve to the next source of the list above.

In the configuration form, every plain text field switches between **Value** and **Reference**:

- a value which is a reference as a whole opens as a Reference, any other (mixed ones included) as a Value; switching to Value keeps the reference as text (neither masked nor offered to be saved as a secret), switching to Reference keeps a value which is a reference as a whole, empties any other and opens the picker;
- in Reference mode, the name is shown as a chip (removable), picked among the secrets and variables of the scope, typed (a name missing from the listing), or created: a `secret` field lists the secrets first and creates a secret, any other field lists the variables first (with their values) and creates a variable or a secret. A line under the field tells whether the name is a secret, a variable (with its value, or a warning on a `secret` field), or defined nowhere in the scope. The picker also lists the names of the `PWI_<NAME>` variables of the instance (`/system-envs`, without the prefix nor the value) in a last **System** group, and the line reads "System variable of the instance" when no secret nor variable of the scope has this name (no warning on a `secret` field: the value is not stored in clear in the database). In a workspace, as the API only resolves them in a `secret` field or as the default value of the field, the group of another field only lists the name its default refers to, and a reference to another one is flagged "only resolved in a secret field, or as the default value of the field". An editor of the integration (ACL) who cannot edit the workspace gets the free typing only;
- in Value mode, a `secret` field offers **Save as secret**: the typed value becomes a secret (named after the key, `apiKey` → `API_KEY`), and the field its reference.

The secrets and variables are managed in the **Secrets & variables** tab of the workspace (`/workspaces/:id/manage/secrets`: names, the integrations referring to them, value of a secret replaced but never shown, value of a variable edited in clear, deletion), and in the **Instance secrets & variables** tab of the instance integrations screen for the administrators. The admin still manages both, every workspace included.

### Keys read by the API

Without the secret key, `SecretsManager::decryptSecret()` throws `SecretKeyUnavailableException` (never a placeholder, which a request could send back), and so does a reference to a secret resolved in the API. A key the API reads itself, such as the key a webhook compares to its requests (`readByApi` attribute), therefore cannot refer to a secret:

- saving such a key with a reference to a secret of the workspace (`${NAME}` or `$NAME`, alone or within a text) is refused: 422 on `config.<key>`, "write the value in full". A reference to a variable is accepted;
- a reference that only becomes a secret afterwards (a secret created later under that name) matches no request: the webhook answers as for a wrong key (`IntegrationConfig::matches()`, which also refuses an empty key). A webhook comparing a value without `matches()` fails on the exception, never on a placeholder.

The dev stack gives the secret key to the API container (`docker-compose.dev.yml`): the refusal on save does not depend on it. The tests run with a test key pair; `WithoutSecretKeyTrait` runs a test as the API container.

## Integration keys

An **integration key** lets a service without user, such as an Uploader serving an upload link, call a few operations of the API on behalf of an integration: `Authorization: ApiKey <integrationId>:<securityKey>` (`App\Security\IntegrationAuthenticator`).

- Only the Uploader integrations have one: `UploaderIntegrationKeyChecker` answers 404 when the integration is unknown, disabled or not an Uploader one, and 403 when the key is not its `securityKey` (compared by `IntegrationConfig::matches()`, see [Keys read by the API](#keys-read-by-the-api)) or when the integration has no owner. Without the integration ID, the key alone is no credential.
- Only the read operations of API Platform flagged with `IntegrationAuthenticator::OPERATION_ACCESS` in their `extraProperties` accept it (`GET /attribute-entities`); any other operation answers 403.
- The request acts as the owner of the integration (`App\Security\IntegrationUser`, roles and groups of the owner read from Keycloak by `IntegrationOwnerProvider`, kept 60 seconds), restricted to the workspace of the integration and to the approved values of the entity lists; the usage counts (`usage=1`) answer 400. See [Uploader integration](#uploader-integration-phraseauploader).

The other endpoints called by a service check their key themselves, without integration key: the incoming commits of the Uploader (`POST /integrations/uploader/{integrationId}/incoming-commit`, `ApiKey <securityKey>`), the imports of `core.asset_import`, the webhook of `phraseanet.renditions` (`token` query parameter) and the events of `aws.transcribe` (signed by AWS SNS).

## Integration tokens (OAuth)

An integration calling another Phrasea API on behalf of a user (Expose, for the baskets and the `expose_publish` action of the automations) holds one OAuth token per user (`App\Entity\Integration\IntegrationToken`). The OAuth client it uses is described in the [Expose integration](#expose-integration-phraseaexpose) (`clientId`, client picker and creation through `GET`/`POST /oauth-clients`, `CreateOAuthClientProcessor`).

Authorization, from the client (`useIntegrationAuth`):

1. The client gets a one-time token of the signed-in user (`POST /ott`, always as the real user: the popup cannot carry the impersonation header), then opens a popup on `GET /integrations/{integrationId}/auth?token=<one-time token>`.
2. The API redirects the popup to `<baseUrl>/oauth/v2/authorize` with the `clientId` of the integration, the redirect URI `<Databox API URL>/integrations/{integrationId}/code`, and the one-time token as `state`.
3. On `/integrations/{integrationId}/code`, the API exchanges the code (`authorization_code` grant on `<baseUrl>/oauth/v2/token`, public client), consumes the one-time token to identify the user, and stores the token set (`IntegrationTokenManager::persistToken()`); the popup closes itself.

Lifetime and renewal (`App\Integration\Auth\IntegrationTokenManager`):

- `expiresAt` is the expiry of the refresh token, or of the access token when there is no refresh token. An expired token is deleted when it is used: the user has to authorize the integration again (the Expose synchronization of a basket then stops and notifies the user, `MissingIntegrationTokenException`).
- An expired access token with a refresh token is renewed when it is used; a refresh token refused by the provider (400 or 401) deletes the token.
- `bin/console app:integration:renew-tokens` (hourly, `MainSchedule`) renews ahead the tokens whose refresh token expires within `--threshold` seconds (7200 by default), for the integrations implementing `IntegrationTokenRenewerInterface` (Expose); the other ones are reported as skipped.

API:

| Endpoint | Rights |
| --- | --- |
| `GET /integrations/{integrationId}/tokens` | `READ` on the workspace of the integration: the tokens of the current user only (`userId`, `expiresAt`, `expired`), never those of the others |
| `GET /integration-tokens/{id}`, `DELETE /integration-tokens/{id}` | `READ` / `DELETE` on the token: no voter grants them, so the administrators only |

The admin lists the tokens (no creation nor edition), and the tokens of a workspace are deleted with it.

## Exhaustive integration list

The table below is the exhaustive integration list currently available in the Databox codebase (`databox/api/src/Integration`).

| Integration key | Display name | Primary usage | External service |
| --- | --- | --- | --- |
| core.rendition | Rendition | Workflow rendition generation orchestration | No |
| phrasea.expose | Expose | Basket and automation publication to Expose | No |
| core.read_metadata | Read Metadata | Asset source metadata extraction and normalization | No |
| phrasea.uploader | Uploader | Ingestion of the commits sent by a Phrasea Uploader | No |
| core.asset_import | Asset import | Webhook receiving JSON/NDJSON asset import instructions | No |
| tui.photo-editor | Toast UI Photo Editor | Asset view image editing | No |
| core.file_analyzer | File Analyzer | Analysis of the incoming files, quarantine of the rejected ones | No |
| core.moderation | Moderation | Workflow step holding the incoming assets | No |
| core.watermark | Watermark | Attribute value stamped on renditions | No |
| blurhash | Blurhash | BlurHash placeholder stored in an attribute | No |
| aws.rekognition | AWS Rekognition | Labels, texts and faces detection (asset view and workflow) | Yes |
| aws.transcribe | AWS Transcribe | Transcription and subtitles of videos | Yes |
| aws.translate | AWS Translate | Translation of the attribute values | Yes |
| happyscribe | Happyscribe | Transcription and subtitles of audio and video files | Yes |
| core.webhook | Webhook | Outbound workflow webhook calls | No (calls the configured URL) |
| remove.bg | Remove BG | Background removal of images | Yes |
| phraseanet.renditions | Phraseanet Renditions | Rendition generation by a Phraseanet instance | Yes |
| core.similarity | Similarity | Embeddings for the visual similarity search | No |
| core.face_recognition | Face Recognition | Face detection and recognition (asset view and workflow) | No |
| test.asset_operation | Test asset operation | Development and test workflow integration | No |

Note: for integrations marked Yes in the External service column, some assets, metadata, or derived content will necessarily be submitted to these external services for processing. The self-hosted services of Similarity and Face Recognition run on the platform: no data leaves it.

## Summary

Databox integrations provide the technical layer that interconnects Phrasea with external systems while keeping internal flows consistent.
In practice:

- core.rendition builds the renditions of the workspace; phraseanet.renditions has a Phraseanet instance build them.
- core.read_metadata extracts the metadata of the source file during ingest.
- core.file_analyzer checks the incoming files and quarantines, trashes or deletes the rejected assets; core.moderation holds the incoming assets in their workflow.
- phrasea.uploader ingests the commits of an Uploader, and core.asset_import the JSON/NDJSON import instructions.
- phrasea.expose publishes baskets and saved search results to Expose.
- tui.photo-editor saves and deletes the files edited in the asset view, and remove.bg removes the background of images.
- core.watermark stamps an attribute value on renditions, and blurhash stores a BlurHash placeholder.
- aws.rekognition detects labels, texts and faces; core.face_recognition detects and recognizes the faces with a self-hosted service; core.similarity computes the embeddings of the similarity search.
- aws.transcribe and happyscribe transcribe audio and video files, and aws.translate translates attribute values.
- core.webhook calls an external URL from the workflows, and test.asset_operation writes a test value for the development and the tests.

---
title: 'Workers'
---

# Databox workers

The asynchronous work of databox goes through Symfony Messenger transports (RabbitMQ
queues), consumed by the `databox-worker` containers. Each kind of processing has its own
transport, and each worker process consumes its transports in priority order, so that a
long job never delays a fast one: a 10-minute video rendition does not hold the
notifications back, and a bulk ingestion does not delay the upload of a single user,
while it still gets every idle process.

## Queues

| Transport        | Content                                                                                                                                                                                                        |
| ---------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `p1`             | Real time: indexing (`ESIndex`), live updates (Pusher), `AttributeChanged`, ACL and search updates, `Notify*Topic`, `AssetMove`…                                                                               |
| `p2`             | Standard: the light workflow jobs (default routing of `JobConsumer`), webhooks, notifications, e-mails, `DeployConfig`, `BasketUpdate`, `SyncBasket`, file deletions…                                          |
| `heavy`          | Computation, file transfers and slow external APIs: the jobs of the renditions built first, watermark, metadata reading, file analysis, blurhash, source downloads; heavy messages                             |
| `heavy_low`      | The heavy jobs that are not urgent: the jobs of the other renditions, faces, similarity, Rekognition, Remove.bg, Happyscribe, Transcribe                                                                       |
| `bulk`           | The light jobs of the workflows started by a bulk processing, the start of each workflow of a workspace ingestion (`IngestWorkspaceAsset`), the asset creation of each asset import entry (`ImportAssetEntry`) |
| `bulk_heavy`     | The heavy jobs (`queue: heavy`) of the workflows started by a bulk processing                                                                                                                                  |
| `bulk_heavy_low` | The non urgent heavy jobs (`queue: heavy_low`) of the workflows started by a bulk processing                                                                                                                   |
| `task`           | Bulk orchestrations (workspace ingestion, asset import dispatch, automations, operation tasks), `ESPopulate`, `IndexAllAssets`, `IndexAllCollections`, `DeleteWorkspace`                                       |

`IndexAllAssets` and `IndexAllCollections` only stay on `task` for every workspace: those of
one workspace (an access granted or revoked on it, a new owner) go to `heavy`. The permissions
are filtered in Elasticsearch, so a revoked access must not wait for the end of a populate.

The heavy messages outside of the workflows are the dynamic renditions
(`BuildDynamicRendition`), the story contact sheets, the image processing (`ProcessImageSet`),
the similarity embeddings, the face recognition messages, `RekognitionAnalyze`, `RemoveBgCall`,
the file imports (`ImportFile`, `PhraseanetDownloadSubdef`), the exports (`AssetExportProcess`),
the asset copies, the file analyses reevaluation, the indexing of a collection
(`IndexCollectionAssets`, `IndexCollectionBranch`) and the bulk deletions and restorations
(`DeleteCollection`, `AssetsDelete`, `CollectionsMoveToTrash`, `CollectionsRestore`,
`AssetsRestore`). They all stay on `heavy`: only workflow jobs go to `heavy_low`.

An export (`AssetExportProcess`) downloads up to `EXPORT_DOWNLOAD_CONCURRENCY` files at once
(8 by default, `1` downloads them one by one); the rest of its processing (clipping, metadata
writing, archive, upload) stays sequential. The default is set in
`databox/api/config/services.yaml`; neither `.env` nor the compose files pass this variable to
the containers, so changing it requires adding it to the environment of the worker.

A message of `App\…` declares its transport with `#[MessengerMessage('…')]`; a message of a
shared bundle is routed in `databox/api/config/packages/messenger.yaml`. An external call
only goes to `heavy` when it is long (a file download, a slow AI API); a webhook stays on
`p2`.

### Workflow jobs

A job of a workflow is triggered by a `JobConsumer` message, routed to `p2` by default. A job
declares another transport with its `queue` key:

```yaml
jobs:
    import_source:
        # Downloads the source file
        queue: heavy
        steps:
            -   uses: App\Service\Workflow\Action\ImportSourceFileAction
```

The jobs of the integrations get theirs from `WorkflowHelper::createIntegrationJob()` (`queue`
argument, see `App\Consumer\WorkerQueue`). The transport is resolved when the job is
triggered (`JobQueueResolverInterface` of the workflow library, `App\Service\Workflow\JobQueueResolver`
in databox) and passed to Messenger with a `TransportNamesStamp`.

The heavy jobs are split between two transports, so that the renditions everybody waits for
are built before the others, whatever the number of assets being ingested:

- the rendition jobs (`RenditionIntegration`) of the definitions marked "Build first"
  (`buildFirst`, set on the thumbnails by default), and of the definitions they are built from
  (their parents, up to the source), go to `heavy`; the other renditions (animated thumbnails,
  custom renditions…) go to `heavy_low`. With 1000 assets dropped at once, the 1000 thumbnails
  are built before the first animated thumbnail;
- the jobs of the AI integrations (similarity, face recognition, Rekognition, Remove.bg,
  Happyscribe, Transcribe) go to `heavy_low`;
- the source import, the metadata reading, the file analysis, blurhash and the watermark stay
  on `heavy`: the other jobs need them.

This order only holds if the workflows of every asset are started before the workers run out
of heavy jobs: a worker finding `bulk_heavy` empty takes a face recognition job even though
other assets are still waiting for their thumbnail. So the workspace ingestion task
(`ingest_workspace_assets`) only dispatches one `IngestWorkspaceAsset` per asset, on `bulk`,
which starts the workflow of its asset: every process but `p1` and `task` starts these workflows
in parallel, before any `bulk_heavy` job, and the jobs of every asset are queued before a
`bulk_heavy_low` one is taken. Only the last workflows still running may let a worker start a
low priority job before their thumbnail.

The asset import follows the same split: `DispatchAssetImport` reads the instructions file on
`task` and dispatches one `ImportAssetEntry` per entry, on `bulk`, so that the assets are
created in parallel and an import never waits behind a long `task` orchestration (`ESPopulate`).

### Bulk origin

The jobs of a workflow started while a message of `task`, `bulk`, `bulk_heavy` or
`bulk_heavy_low` is handled (a workspace ingestion, an asset import entry, an automation) go to
`bulk_heavy` when their own queue is `heavy`, to `bulk_heavy_low` when it is `heavy_low`, to
`bulk` otherwise (`BulkOriginMiddleware` marks the handling, `JobQueueResolver` reads it). A job consumed from a bulk transport continues its workflow with
the same origin: the whole workflow stays on the bulk transports. A job retried from the admin,
or continued by a controller, goes back to its own queue.

Only the workflow jobs follow the bulk origin: the messages a bulk processing dispatches
directly (indexing, notifications, `ProcessImageSet`…) keep their own transport.

Nothing of this origin is persisted: a workflow started once the message is handled, by a
callback deferred to its end (`PostFlushStack` defers its callbacks to `TerminateStackListener`
when the flush happens inside a transaction), runs on the interactive queues.

## Priorities

A `messenger:consume` process given several receivers goes back to the first one after each
handled message, and the AMQP transport fetches one message at a time (`basic_get`, no
prefetch): a free process always takes the most urgent message waiting among its receivers.
There is no RabbitMQ priority: a queue is consumed in order.

A message being handled is never interrupted, though: a process busy with a 10-minute rendition
of a bulk ingestion only looks at `p1` once it is done. So the processes are layered by what
they may be held by, each one listing every transport it may consume, the most urgent first:

| Program                                                | Held at most by                    |
| ------------------------------------------------------ | ---------------------------------- |
| `p1`                                                   | a real-time message                |
| `p1,p2,bulk`                                           | a light job, interactive or bulk   |
| `p1,p2,heavy,heavy_low,bulk,bulk_heavy,bulk_heavy_low` | a heavy job, interactive or bulk   |
| `task`                                                 | a bulk orchestration (may be long) |

In the heavy program, the interactive transports come before the bulk ones, and in each family
the jobs built first come before the low priority ones: an interactive animated thumbnail still
goes before a bulk thumbnail.

Without user activity, every process but `p1` and `task` works on the bulk transports. As soon
as an interactive message comes, the first process to be free takes it before any bulk one:
immediately for `p1`, after one light job at most for `p1,p2,bulk`.

`task` stays apart: an orchestration such as `ESPopulate` may run for hours, and keeping it
behind the bulk transports would delay the next bulk processing rather than speed it up.

## Worker processes

The worker image starts one supervisor program per entry of `WORKER_PRIORITIES` (separated by
spaces):

- an entry lists its receivers, separated by `,`, in priority order;
- an optional `:N` suffix sets its number of processes (1 by default, an integer >= 1);
- the program is named after its receivers joined by `-` (`messenger-consume-p1-p2-bulk`).

The databox default is `p1 p1,p2,bulk p1,p2,heavy,heavy_low,bulk,bulk_heavy,bulk_heavy_low task`:
4 programs of one process each. To give more room to the bulk processings, raise the number of
processes of the heavy program (`p1,p2,heavy,heavy_low,bulk,bulk_heavy,bulk_heavy_low:3`) rather
than adding programs that skip `p1`.
`scheduler_main` stays the only entry of the scheduler containers.

Every process has its own `PHP_MEMORY_LIMIT`; the container shares
`DATABOX_WORKER_MEMORY_LIMIT` between them. Raise it with the number of processes.

### Temporary files

The files the workers download or build (sources, renditions, transcripts, export archives, clips,
the working directories and remote file cache of the rendition factory) go to `/tmp/databox`
(parameter `app.tmp_dir`), and are deleted once used, on failure too. A process killed in the
middle of a message (OOM, time limit, deployment) leaves its files there: each worker process,
when it starts, deletes the entries of this directory older than 24 hours
(`TemporaryFilesPurgeListener`). The rest of `/tmp` is never touched; the external tools
(ImageMagick, ffmpeg, LibreOffice, exiftool) keep their own temporary files
(`IMAGEMAGICK_POLICY_TEMPORARY_PATH`, `TMPDIR`).

### Splitting the deployments

The queues can be consumed by different deployments, e.g. with Helm:

- a light deployment, `WORKER_PRIORITIES=p1 p1,p2,bulk task`, with little memory and several
  replicas;
- a heavy deployment, `WORKER_PRIORITIES=p1,p2,heavy,heavy_low,bulk,bulk_heavy,bulk_heavy_low:2`,
  with more memory (ffmpeg, ImageMagick, exiftool).

Every transport must be consumed by at least one deployment: the messages of a transport
nobody consumes pile up in RabbitMQ.

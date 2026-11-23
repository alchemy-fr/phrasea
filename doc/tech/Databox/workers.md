# Databox workers

The asynchronous work of databox goes through Symfony Messenger transports (RabbitMQ
queues), consumed by the `databox-worker` containers. Each kind of processing has its own
transport, consumed by its own processes, so that a long job never delays a fast one: a
10-minute video rendition no longer holds the notifications back, and a bulk ingestion
no longer delays the upload of a single user.

## Queues

| Transport | Content                                                                                                                                                                                                                         | Default program       |
| --------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------- |
| `p1`      | Real time: indexing (`ESIndex`), live updates (Pusher), `AttributeChanged`, ACL and search updates, `Notify*Topic`, `AssetMove`…                                                                                                | `p1`                  |
| `p2`      | Standard: the light workflow jobs (default routing of `JobConsumer`), webhooks, notifications, e-mails, `DeployConfig`, `BasketUpdate`, `SyncBasket`, file deletions…                                                           | `p2`                  |
| `heavy`   | Computation, file transfers and slow external APIs: the jobs of renditions, watermark, metadata reading, file analysis, blurhash, faces, similarity, Rekognition, Remove.bg, Happyscribe, Transcribe, downloads; heavy messages | `heavy,bulk`          |
| `bulk`    | Every job (light or heavy) of the workflows started by a bulk processing                                                                                                                                                        | (same, after `heavy`) |
| `task`    | Bulk orchestrations (workspace ingestion, asset imports, automations, operation tasks), `ESPopulate`, `IndexAllAssets`, `IndexAllCollections`, `DeleteWorkspace`                                                                | `task`                |

The heavy messages outside of the workflows are the dynamic renditions
(`BuildDynamicRendition`), the story contact sheets, the image processing (`ProcessImageSet`),
the similarity embeddings, the face recognition messages, `RekognitionAnalyze`, `RemoveBgCall`,
the file imports (`ImportFile`, `PhraseanetDownloadSubdef`), the exports (`AssetExportProcess`),
the asset copies, the file analyses reevaluation, the indexing of a collection
(`IndexCollectionAssets`, `IndexCollectionBranch`) and the bulk deletions and restorations
(`DeleteCollection`, `AssetsDelete`, `CollectionsMoveToTrash`, `CollectionsRestore`,
`AssetsRestore`).

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

### Bulk origin

The jobs of a workflow started while a message of `task` or `bulk` is handled (a workspace
ingestion, an asset import entry, an automation) all go to `bulk`, whatever their own queue
(`BulkOriginMiddleware` marks the handling, `JobQueueResolver` reads it). A job consumed from
`bulk` continues its workflow with the same origin: the whole workflow stays on `bulk`. A job
retried from the admin, or continued by a controller, goes back to its own queue.

Nothing of this origin is persisted: a workflow started once the message is handled, by a
callback deferred to its end (`PostFlushStack` defers its callbacks to `TerminateStackListener`
when the flush happens inside a transaction), runs on the interactive queues.

## Priorities

A `messenger:consume` process given several receivers goes back to the first one after each
handled message: the `heavy,bulk` processes only take a `bulk` message when `heavy` is empty.
There is no RabbitMQ priority: a queue is consumed in order.

## Worker processes

The worker image starts one supervisor program per entry of `WORKER_PRIORITIES` (separated by
spaces):

- an entry lists its receivers, separated by `,`, in priority order;
- an optional `:N` suffix sets its number of processes (1 by default, an integer >= 1);
- the program is named after its receivers joined by `-` (`messenger-consume-heavy-bulk`).

The databox default is `p1 p2 heavy,bulk task`: 4 programs of one process each.
`scheduler_main` stays the only entry of the scheduler containers.

Every process has its own `PHP_MEMORY_LIMIT`; the container shares
`DATABOX_WORKER_MEMORY_LIMIT` between them. Raise it with the number of processes.

### Splitting the deployments

The queues can be consumed by different deployments, e.g. with Helm:

- a light deployment, `WORKER_PRIORITIES=p1 p2 task`, with little memory and several replicas;
- a heavy deployment, `WORKER_PRIORITIES=heavy,bulk:2`, with more memory (ffmpeg,
  ImageMagick, exiftool).

Every transport must be consumed by at least one deployment: the messages of a transport
nobody consumes pile up in RabbitMQ.

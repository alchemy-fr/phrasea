# Databox workers

The asynchronous work of databox goes through Symfony Messenger transports (RabbitMQ
queues), consumed by the `databox-worker` containers. Each kind of processing has its own
transport, and each worker process consumes its transports in priority order, so that a
long job never delays a fast one: a 10-minute video rendition no longer holds the
notifications back, and a bulk ingestion no longer delays the upload of a single user,
while it still gets every idle process.

## Queues

| Transport    | Content                                                                                                                                                                                                                         |
| ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `p1`         | Real time: indexing (`ESIndex`), live updates (Pusher), `AttributeChanged`, ACL and search updates, `Notify*Topic`, `AssetMove`…                                                                                                |
| `p2`         | Standard: the light workflow jobs (default routing of `JobConsumer`), webhooks, notifications, e-mails, `DeployConfig`, `BasketUpdate`, `SyncBasket`, file deletions…                                                           |
| `heavy`      | Computation, file transfers and slow external APIs: the jobs of renditions, watermark, metadata reading, file analysis, blurhash, faces, similarity, Rekognition, Remove.bg, Happyscribe, Transcribe, downloads; heavy messages |
| `bulk`       | The light jobs of the workflows started by a bulk processing                                                                                                                                                                    |
| `bulk_heavy` | The heavy jobs (`queue: heavy`) of the workflows started by a bulk processing                                                                                                                                                   |
| `task`       | Bulk orchestrations (workspace ingestion, asset imports, automations, operation tasks), `ESPopulate`, `IndexAllAssets`, `IndexAllCollections`, `DeleteWorkspace`                                                                |

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

The jobs of a workflow started while a message of `task`, `bulk` or `bulk_heavy` is handled (a
workspace ingestion, an asset import entry, an automation) go to `bulk_heavy` when their own
queue is `heavy`, to `bulk` otherwise (`BulkOriginMiddleware` marks the handling,
`JobQueueResolver` reads it). A job consumed from a bulk transport continues its workflow with
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

| Program                             | Held at most by                     |
| ----------------------------------- | ----------------------------------- |
| `p1`                                | a real-time message                 |
| `p1,p2,bulk`                        | a light job, interactive or bulk    |
| `p1,p2,heavy,bulk,bulk_heavy`       | a heavy job, interactive or bulk    |
| `task`                              | a bulk orchestration (may be long)  |

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

The databox default is `p1 p1,p2,bulk p1,p2,heavy,bulk,bulk_heavy task`: 4 programs of one
process each. To give more room to the bulk processings, raise the number of processes of the
heavy program (`p1,p2,heavy,bulk,bulk_heavy:3`) rather than adding programs that skip `p1`.
`scheduler_main` stays the only entry of the scheduler containers.

Every process has its own `PHP_MEMORY_LIMIT`; the container shares
`DATABOX_WORKER_MEMORY_LIMIT` between them. Raise it with the number of processes.

### Splitting the deployments

The queues can be consumed by different deployments, e.g. with Helm:

- a light deployment, `WORKER_PRIORITIES=p1 p1,p2,bulk task`, with little memory and several
  replicas;
- a heavy deployment, `WORKER_PRIORITIES=p1,p2,heavy,bulk,bulk_heavy:2`, with more memory
  (ffmpeg, ImageMagick, exiftool).

Every transport must be consumed by at least one deployment: the messages of a transport
nobody consumes pile up in RabbitMQ.

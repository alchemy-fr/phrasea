---
title: 'Operation tasks'
---

# Operation tasks

Operation tasks are the maintenance operations an admin starts on a whole workspace or
attribute: reindexing, rerunning the ingest, importing the files referenced by URL,
rewriting attribute values. Each task is recorded with its payload, its status, its progress
and its output, and runs in the background on the `task` transport.

## Concepts

- A **task** is a row of `operation_task` (`App\Entity\Admin\OperationTask`): its type
  (`task`), its `payload`, its owner (`ownerId`, the admin who started it), its status, its
  dates (`createdAt`, `startedAt`, `endedAt`), its progress (`progress` out of `itemTotal`,
  `estimated`, `remaining`) and its `output` (text appended as it runs).
- A **task type** is a service implementing `App\OperationTask\OperationTaskInterface`
  (`getName()`, `validate(array $payload)`, `handle(array $payload, RunContext $context)`),
  registered under its name with `#[AsTaggedItem(index: self::NAME)]` and found by
  `App\OperationTask\OperationTaskRegistry`.
- `App\OperationTask\OperationTaskManager` creates the task (after `validate()`), dispatches
  a `RunOperationTask` message, then runs it in the worker.

## Task types

| Name (`task`)               | Client label                    | Payload                                                               | What it does                                                                                                                                                                                                                                                                           |
| --------------------------- | ------------------------------- | --------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `index_assets`              | **Index assets**                | `workspaceId`, `assetId` (both optional)                              | Indexes the assets in Elasticsearch, in the task itself, with a progress over the assets: all of them, those of the workspace, or the one asset (`App\Elasticsearch\AssetIndexer::index()`).                                                                                            |
| `ingest_workspace_assets`   | **Ingest workspace assets**     | `workspaceId` (required)                                              | Dispatches one `IngestWorkspaceAsset` message per asset of the workspace, on `bulk`; each one starts the ingest workflow of its asset, on behalf of the owner of the task, and counts one item. The last one completes the task (`App\Service\Asset\WorkspaceAssetIngester`).           |
| `import_files`              | **Import files**                | `workspaceId` (required)                                              | Dispatches one `ImportFile` message (on `heavy`) per file of the workspace stored as a URL (`storage` = `url`), to fetch it and store it on the main storage. The task completes once the messages are dispatched: the imports go on after it.                                         |
| `store_fallback_attributes` | **Store fallback as attribute** | `definitionId` (required)                                             | For every asset of the workspace of the attribute definition (if it is enabled and has a fallback), stores the value its fallback resolves to as a real attribute value (origin `fallback_persisted`), for each locale of the fallback, where no value exists yet.                      |
| `recompute_initial_values`  | **Recompute initial values**    | `definitionId` (required)                                             | For every asset of the workspace of the definition, deletes the values of that attribute and computes them again from its initial values and metadata reading (one transaction per asset).                                                                                              |
| `switch_attribute_locales`  | **Switch attribute locales**    | `definitionId`, `fromLocale`, `toLocale` (required); `_` = no locale  | Moves the values of the attribute from one locale to another in one SQL update (`_` matches, or sets, the values without locale), and writes the number of rows changed to the output.                                                                                                |

A missing required key answers **400** (`workspaceId is required`,
`definitionId is required`…); an unknown task name is refused (`Task "{name}" not found`).

The last two attribute tasks share `App\Service\Asset\Attribute\AttributeDefinitionOperationRunner`,
which walks the assets one by one and updates the progress after each.

:::warning
`switch_attribute_locales` is a bulk update of the attribute rows: Doctrine listeners do not
run on it, so the assets are not reindexed by the task itself. Run **Index assets** on the
workspace afterwards.
:::

## Lifecycle

| Status        | Value | Set when                                                                                     |
| ------------- | ----- | -------------------------------------------------------------------------------------------- |
| `Pending`     | `0`   | the task is created and its `RunOperationTask` message dispatched                            |
| `In Progress` | `1`   | a worker picks it up (atomic move from `Pending`, which also sets `startedAt`)               |
| `Completed`   | `2`   | `handle()` returned, or, for a fanned-out task, the last item was processed                  |
| `Failed`      | `3`   | `handle()` threw: the error message is appended to the output                                |
| `Cancelled`   | `4`   | an admin cancelled it while it was `Pending` or `In Progress`                                |

`Completed`, `Failed` and `Cancelled` set `endedAt`.

- A task cancelled (or deleted) before a worker picks it up is never run.
- A task that fans out its work (`ingest_workspace_assets`) defers its completion
  (`RunContext::deferCompletion()`): it stays `In Progress` once its handler returns, its total
  is set once every message is dispatched, and the message that brings `progress` to
  `itemTotal` completes it (`App\OperationTask\OperationTaskProgress::finalizeIfComplete()`,
  exactly once even with several workers). A workspace without assets completes right away.
- A failure on one asset of an ingestion is only logged: it still counts as processed, so
  that the task completes.

### Progress

A task calling `RunContext::start($total)`, `advance()` and `finish()` updates `progress`,
`itemTotal`, `estimated`, `remaining` and its output in the database at each step. The API
also returns `progression` (percentage, null while the total is unknown). The fanned-out
ingestion counts its items with atomic SQL increments instead.

### Cancellation

`POST /operation-tasks/{id}/cancel` sets a `Pending` or `In Progress` task to `Cancelled`
(atomically: a task completing meanwhile is not turned into a cancelled one) and answers 409
otherwise. The task stops at its next step:

- a task reporting its progress reloads its row at each step and stops on a cancellation
  (`OperationTaskCancelledException`, logged, not an error); what it already did is kept;
- the pending `IngestWorkspaceAsset` messages of a cancelled ingestion are dropped; the
  workflows already started go on (see [Workflows](./workflows.md));
- a task without steps (`switch_attribute_locales`, the dispatch of `import_files`) runs to its
  end, and its final state never overwrites the cancellation.

### Deletion

`DELETE /operation-tasks/{id}` removes a `Completed`, `Failed` or `Cancelled` task and its
output from the history; it answers 409 for a task still `Pending` or `In Progress` (cancel it
first). Tasks are kept until they are deleted.

## API

Every operation requires `ROLE_ADMIN` (the `admin` or `databox-admin` role).

| Method and path                      | Body / response                                                                                                                                   |
| ------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| `GET /operation-tasks`               | The tasks, the last created first.                                                                                                                |
| `GET /operation-tasks/{id}`          | One task: `task`, `payload`, `status`, `output`, `createdAt`, `startedAt`, `endedAt`, `progress`, `itemTotal`, `progression`, `estimated`, `remaining`, `ownerId`, `owner`. |
| `POST /operation-tasks`              | `{"task": "ingest_workspace_assets", "payload": {"workspaceId": "…"}}` (`task` required): creates and queues the task, answers it `Pending`.        |
| `POST /operation-tasks/{id}/cancel`  | No body: the task, `Cancelled`; 409 unless `Pending` or `In Progress`.                                                                            |
| `DELETE /operation-tasks/{id}`       | 204; 409 unless `Completed`, `Failed` or `Cancelled`.                                                                                             |

## Client screen

**Operation tasks**, settings menu → **Operation tasks** (admins), one URL per view
(`features/operation-tasks/OperationTasksScreen.tsx`):

| Route                    | View                                                                                                                                                               |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `/admin/tasks`           | The history: label, owner, creation date, duration, **remaining** time, status badge (**Pending**, **In progress**, **Completed**, **Failed**, **Cancelled**), a progress ring while in progress; **Refresh**, **New task**, **Load more**; **No task yet** when empty. |
| `/admin/tasks/new`       | The catalog of the six types with their description.                                                                                                              |
| `/admin/tasks/new/[task]` | The form of a type: **Workspace**, then **Attribute** (picked in that workspace), **From locale** / **To locale** (**No locale** or the enabled locales of the workspace); **Run** starts it (**Task started**) and opens its details. |
| `/admin/tasks/[id]`      | The details: **Owner**, **Created at**, **Started at**, **Ended at**, **Duration**, **Items**, **Progression**, **Payload** (copiable), **Output**; **Cancel the task** while it is pending or in progress, **Delete the task** once it is over. Refreshed every 3 seconds until the task is over. |

The form of **Index assets** only offers the workspace (optional); `assetId` is only reachable
through the API. The workspace of the attribute tasks is only used by the form, to pick the
attribute: the task works on the workspace of the attribute definition.

The administration (EasyAdmin) lists and shows the tasks under **Maintenance → Operation
Tasks** (filters on task, status, owner and dates), without creation nor edition.

## Transport

`RunOperationTask` goes to the `task` transport, consumed by its own worker process: a long
task never delays the interactive queues, and does not wait behind them either. The messages a
task dispatches keep their own transport (`IngestWorkspaceAsset` on `bulk`, `ImportFile` on
`heavy`), and the workflows a task starts run on the bulk transports. See
[Databox workers](./workers.md).

## Required permissions

Admins only (`ROLE_ADMIN`), for the API, the client screen and the administration. A task acts
with no user rights involved, on every asset of the workspace; the ingest workflows it starts
are initiated on behalf of its owner.

## See also

- [Workflows](./workflows.md): the ingest started by **Ingest workspace assets**.
- [Databox workers](./workers.md): the `task` and bulk transports.
- [Elasticsearch populate](./es_populate.md): the full reindexing of the instance.
- [Console commands](./console-commands.md), [Administration](./administration.md).

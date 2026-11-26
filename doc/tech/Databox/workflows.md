---
title: 'Workflows'
---

# Workflows

A workflow is the chain of asynchronous jobs Databox runs on an asset: the ingest of a new
source file (source import, content check, metadata, renditions, AI integrations…), the
reaction to an attribute change, the reception of a file from the Uploader. Each run is
recorded with the state of every job, can be followed live, and its jobs can be rerun.

## Concepts

| Term        | Meaning                                                                                                                                                         |
| ----------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Definition  | A YAML file of `databox/api/config/workflows/` (the root jobs), completed at run time by the jobs of the integrations of the workspace.                         |
| Event       | What starts a definition (`on:`), with typed inputs: `asset_ingest`, `attributes_update`, `incoming_uploader_file`.                                             |
| Run         | A `WorkflowState` (`workflow_state` table, entity `App\Entity\Workflow\WorkflowState`): the event, its inputs, the context (`initiatorId`), the asset, a status. |
| Job         | A unit of work with `steps`, `needs`, an optional `if`, a `queue`. Each execution of a job is a `JobState` (`job_state` table).                                  |
| Stage       | The jobs whose needs are all in the previous stages, computed by the planner (`Alchemy\Workflow\Planner\WorkflowPlanner`). A circular `needs` is refused.       |
| Action      | The PHP service a step `uses` (`Alchemy\Workflow\Executor\Action\ActionInterface`).                                                                             |

The engine is the `lib/php/workflow` library (`Alchemy\Workflow\WorkflowOrchestrator`); the
`lib/php/workflow-bundle` bundle stores the states with Doctrine
(`Alchemy\WorkflowBundle\Doctrine\DoctrineStateRepository`), triggers the jobs through
Messenger (`Alchemy\Workflow\Message\JobConsumer`) and pushes their updates (Pusher).

## Shipped definitions

| File                          | Name (`name:`)           | Event                    | Root jobs                                                                                                                            |
| ----------------------------- | ------------------------ | ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------ |
| `asset-ingest.yaml`           | `asset-ingest`           | `asset_ingest`           | `import_source` (download of a source referenced by URL, `queue: heavy`), then `check_content` (content check of the source file)    |
| `attributes-update.yaml`      | `attributes-update`      | `attributes_update`      | none: only the jobs of the integrations                                                                                              |
| `incoming-uploader-file.yaml` | `incoming-uploader-file` | `incoming_uploader_file` | `border` (fetches the file from the Uploader, `queue: heavy`), then `uploader_ack` and `assign_file_to_asset`                        |

Event inputs:

| Event                    | Inputs                                                                                                                   |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------ |
| `asset_ingest`           | `assetId`, `workspaceId` (required)                                                                                      |
| `attributes_update`      | `attributes` (array of attribute definition IDs), `assetId`, `workspaceId` (required, typed)                             |
| `incoming_uploader_file` | `baseUrl`, `assetId` (Uploader asset), `userId`, `token` (required), `workspaceId`, `collectionId`, `storyCollectionId` |

A missing required input, or an input of another type than its `type`, makes the dispatch
fail (`Alchemy\Workflow\Validator\EventValidator`).

### Jobs of the integrations

`asset-ingest` and `attributes-update` are root workflows: for a workspace, they are run as
`asset-ingest:{workspaceId}` and `attributes-update:{workspaceId}`, built by
`App\Service\Workflow\IntegrationWorkflowRepository`:

- every workspace integration implementing `App\Integration\WorkflowIntegrationInterface`
  adds its jobs (`getWorkflowJobDefinitions()`), in the order the integrations were
  created; an integration may only add jobs for some events (Read Metadata only on
  `asset_ingest`);
- each job is built by `App\Integration\WorkflowHelper::createIntegrationJob()`: its ID is
  `{integration name}:{workspace integration ID}[:{suffix}]`, its name the name of the
  integration (or its display name) followed by the suffix name, its `integrationId` input
  is set, its queue is given by the `queue` argument (see `App\Consumer\WorkerQueue`), and an
  action implementing `App\Integration\IfActionInterface` becomes its `if`
  (`{Action}::evaluateIf`);
- the jobs of an integration are always `continue-on-error`: a failing integration does
  not stop the others;
- the **needs** of a workspace integration (other integrations that must run before it) are
  turned into job needs; an integration implementing
  `App\Integration\FilterNeedIntegrationInterface` may keep only some jobs of the needed one;
- a job without needs gets every root job of the definition as needs: in `asset-ingest`, no
  integration runs before `import_source` and `check_content`;
- the jobs of a disabled integration are marked disabled (**Integration is disabled**),
  and so are the jobs needing a disabled job (**Dependency is disabled**,
  `Alchemy\Workflow\Normalizer\DisabledNeedNormalizer`). A disabled job is never triggered
  and does not count in the progress.

`incoming-uploader-file` is not a root workflow: it gets no integration job.

See [Integrations](./integration/01_integrations.md) for the integrations themselves and
[Renditions](./Renditions.mdx) for the rendition jobs.

## Definition format

```yaml
name: asset-ingest          # unique: runs reload their definition by this name

on:
    asset_ingest:
        inputs:
            assetId:
                required: true
            workspaceId:
                required: true

jobs:
    import_source:
        name: Import source file
        if: App\Service\Workflow\Action\ImportSourceFileAction::evaluateIf
        continue-on-skip: true
        queue: heavy
        steps:
            -   uses: App\Service\Workflow\Action\ImportSourceFileAction

    check_content:
        name: Check source file content
        needs: [import_source]
        steps:
            -   uses: App\Service\Workflow\Action\CheckFileContentAction
```

| Key                      | Effect                                                                                                                                                                                                                                       |
| ------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `name`                   | Required. A run only stores this name and reloads the definition by it (to plan the next jobs, to rerun one, to show the graph): keep it unique among the files, and keep a run name stable, otherwise its pending runs cannot continue. |
| `on`                     | The events starting the workflow and their `inputs` (`required`, `type`).                                                                                                                                                                    |
| `env`                    | Variables of the workflow, readable as `env` in the expressions.                                                                                                                                                                             |
| `jobs.{id}.name`         | Label of the job (its ID by default).                                                                                                                                                                                                        |
| `jobs.{id}.needs`        | Jobs that must end first. A need is satisfied by a `success`, or by a `skipped` job marked `continue-on-skip`.                                                                                                                               |
| `jobs.{id}.if`           | Condition (Symfony ExpressionLanguage over `inputs`, `env`, `jobs`, `steps`). `Class::method` calls the method of that action with the job context. Not met, the job is `skipped`.                                                           |
| `jobs.{id}.continue-on-skip` | A skipped job still satisfies the needs of its dependents. Without it, the dependents of a skipped job never run.                                                                                                                        |
| `jobs.{id}.continue-on-error` | A failing job does not end the workflow in failure right away: the jobs not needing it go on.                                                                                                                                          |
| `jobs.{id}.queue`        | Messenger transport of the job (`heavy`, `heavy_low`…); `p2` by default. See [Databox workers](./workers.md#workflow-jobs).                                                                                                                  |
| `jobs.{id}.with`         | Inputs of the job, merged over the event inputs.                                                                                                                                                                                             |
| `jobs.{id}.outputs`      | Values exposed to the other jobs (`jobs.{id}.outputs.{key}`).                                                                                                                                                                                |
| `jobs.{id}.steps`        | The steps, run in order: `uses` (an action service, by class name) or `run` (a script for the `bash` or `php` executor), with `id`, `name`, `with`, `continue-on-error`.                                                                     |

Dynamic values are written `${{ expression }}`, as in `incoming-uploader-file.yaml`:

```yaml
    assign_file_to_asset:
        needs: [ border ]
        steps:
            -   uses: App\Service\Workflow\Action\AssignSourceFileToAssetAction
        with:
            assetId: ${{ jobs.border.outputs.assetId }}
            fileId: ${{ jobs.border.outputs.fileId }}
```

A condition written by a user (the `if` of a workspace integration, which also gets the
`asset` variable) may only call getters (`get…`, `is…`, `has…`): any other method call is
refused (`ExpressionParser::evaluateCondition()`).

The definitions are compiled into the Symfony cache (`var/cache/{env}/workflows`,
`Alchemy\WorkflowBundle\Loader\KernelCacheLoader`): outside of debug mode, a changed YAML
file is only read after a cache clear. The directory is set by `alchemy_workflow.workflows_dirs`
(`%kernel.project_dir%/config/workflows` by default).

## Triggers

| Source                                                                                                          | Workflow                         | Initiator (`initiatorId`)           |
| --------------------------------------------------------------------------------------------------------------- | -------------------------------- | ----------------------------------- |
| A new source file assigned to an asset (`App\Service\Asset\AssetManager::assignNewAssetSourceFile()`, after the flush): upload, import, Uploader, `app:workflow:trigger-asset-ingest` | `asset-ingest:{workspaceId}`     | owner of the asset                  |
| `PUT /assets/{id}/trigger-workflow` (**Trigger workflow again**, **Run a new workflow**)                         | `asset-ingest:{workspaceId}`     | the user or OAuth client            |
| The **Run the workflow** action of a saved search automation (`trigger_workflow`, see [Automations](./automations.md)) | `asset-ingest:{workspaceId}` | owner of the saved search           |
| The **Ingest workspace assets** operation task, `app:workspace:ingest-assets`                                    | `asset-ingest:{workspaceId}`     | owner of the task, or of the asset  |
| `app:file:unblock-analyses` (a file analysis left pending, see [File analyzers](./FileAnalyzers.mdx))            | `asset-ingest:{workspaceId}`, or a rerun | owner of the asset          |
| An attribute change (`AttributeChanged` message, `App\Consumer\Handler\Asset\AttributeChangedEventHandler`)       | `attributes-update:{workspaceId}` | author of the change               |
| A commit of the Uploader integration (`IngestUploaderCommitHandler`), one run per uploaded file                   | `incoming-uploader-file`         | none (`userId` input)               |
| The admin action **Trigger Ingest** of an asset (EasyAdmin)                                                       | `asset-ingest:{workspaceId}`     | the admin                           |

`PUT /assets/{id}/trigger-workflow` answers **409** while an ingest run of the asset is still
started ("A workflow is already running on this asset, cancel it to run a new one"). The other
sources do not check it.

The `assign_file_to_asset` job of `incoming-uploader-file` assigns the received file to the
asset, which starts its `asset-ingest` run: an upload shows two runs, **Upload** then
**Ingest**.

A job of a bulk processing (operation task, automation, asset import) is routed to the bulk
transports: see [Bulk origin](./workers.md#bulk-origin).

## Execution

- Starting a run persists it, then triggers every job whose needs are satisfied, one
  `JobConsumer` message each. When a job ends, the worker continues the run: it triggers the
  jobs that became ready (`WorkflowOrchestrator::continueWorkflow()`, under a lock of the run).
- A job message whose job is no longer `triggered` is ignored when it is `cancelled`; a job
  message of a deleted run is dropped (the runs are deleted with their asset).
- A job message handled again by a Messenger retry (its first handling was interrupted by an
  error) is not run twice: it marks the job `failure` with the error `Job timeout error`, then
  continues the run.
- A step may keep its job `running` (`RunContext::retainJob()`): the job is ended later by
  `WorkflowOrchestrator::continueJob()`, from a callback (Phraseanet renditions, moderation).
- The run ends once every enabled job of every stage is over: `success`, or `failure` if a
  job failed. A failing job without `continue-on-error` ends it in `failure` right away. A job
  whose dependency failed or was skipped without `continue-on-skip` is never triggered: it
  shows as **Not started**.

### Statuses

| Run status  | Value | Job status  | Value |
| ----------- | ----- | ----------- | ----- |
| `started`   | `0`   | `triggered` | `0`   |
| `success`   | `1`   | `success`   | `1`   |
| `failure`   | `2`   | `failure`   | `2`   |
| `cancelled` | `3`   | `skipped`   | `3`   |
|             |       | `running`   | `4`   |
|             |       | `error`     | `5`   |
|             |       | `cancelled` | `6`   |

`error` is the status of a job whose `if` could not be evaluated ("Error while evaluating if
condition: …"): it does not end the run, which stays `started` until the job is rerun or the
run cancelled.

### Skip reasons

A skipped job carries its reason (`skipReason`), shown in the job panel:

- the `if` returned false: the reason set by the action, or `Condition not met: {if}`;
- `No source file import pending` (`import_source`), `Asset has no source file`,
  `Source file has a private path and cannot be fetched`, `Asset is not a story` (rendition
  generated from a story), and the reasons of each integration action (`getSkipReason()` of
  `App\Integration\AbstractIntegrationAction`);
- `Integration condition not met: {if}` when the `if` of the workspace integration is false;
- the message of an exception implementing `Alchemy\Workflow\Exception\JobSkipExceptionInterface`
  thrown by a step (`PrivateFileException`, `RenditionBuildException`).

### Refused jobs

A step throwing an exception implementing `Alchemy\Workflow\Exception\JobRefusalExceptionInterface`
(`FileNotProcessableException`: a file whose content was rejected or whose analysis failed) ends
the job in failure with the message of the exception as its only error, logged as a warning: an
expected refusal, not a bug reported to Sentry. Any other exception adds its message and trace to
the errors of the job and is logged as an error.

### Refused source files

`import_source` fails when the downloaded file is refused (the asset goes to quarantine) or no
longer exists; `check_content` fails when the content of the source does not match an allowed
type (unless an admin bypassed the analysis). Every integration job needs them: a refused file
is never processed. See [Quarantine](./quarantine.md).

## Run numbering

- A run gets a `number`: its rank among the runs of the same workflow name on the same asset,
  from `1` (`App\Doctrine\Listener\WorkflowStateListener`). The client titles it after the
  workflow and this number: **Ingest #2**, **Attributes update #1**, **Upload**.
- A job state gets a `number` too: `0` for the first execution of the job in the run, then one
  more at each rerun. Only the last state of each job is shown.

## Rerunning a job

`POST /workflows/{id}/jobs/{jobId}/rerun` (`App\Api\Processor\RerunWorkflowJobProcessor`)
triggers the job again with a new job state (input `rerun: true`), if the job already has a
state in the run and is not disabled; the run goes back to `started`. The jobs already run
after it are not rerun; the jobs that had not run yet are triggered once it succeeds. The
client offers **Rerun** on a job that is over (not **Triggered** nor **Running**).

In the administration, a job state offers **Retry job** when it failed
(`WorkflowOrchestrator::retryFailedJobs()`, `failure` and `error` jobs) and **Rerun job**
otherwise.

## Cancelling a workflow

`POST /workflows/{id}/cancel` (`App\Api\Processor\CancelWorkflowProcessor`) sets the run to
`cancelled` and its `triggered` and `running` jobs to `cancelled`: their pending messages are
ignored and no other job is triggered. A step already running is not interrupted. A run already
ended, or failed, is left as it is. The client offers **Cancel** while the run is `started`; the
administration offers **Cancel Workflow** on a started run and **Cancel job** on a running job
(which cancels its whole run).

## API

| Method and path                            | Security                         | Response                                                                                         |
| ------------------------------------------ | -------------------------------- | ------------------------------------------------------------------------------------------------ |
| `GET /workflows`                           | authenticated                    | Summaries, the last started first: `id`, `name`, `status`, `number`, `eventName`, `startedAt`, `endedAt`, `duration`, `assetId`, `assetName`. Filters `asset` (ID or IRI, or `asset[]`), `status` (or `status[]`). |
| `GET /workflows/{id}`                      | `READ` on the run                | The dump: `id`, `name`, `status`, `number`, `asset` (`id`, `name`), `event` (`name`, `inputs`), `context`, `startedAt`, `endedAt`, `duration`, and `stages[].jobs[]` (`jobId`, `name`, `needs`, `if`, `with`, `disabled`, `disabledReason`, then, once triggered, `stateId`, `status`, `number`, `inputs`, `outputs`, `triggeredAt`, `startedAt`, `endedAt`, `duration`, `skipReason`, `errors`). |
| `POST /workflows/{id}/jobs/{jobId}/rerun`  | `EDIT` on the run                | The dump.                                                                                        |
| `POST /workflows/{id}/cancel`              | `EDIT` on the run                | The dump.                                                                                        |
| `PUT /assets/{id}/trigger-workflow`        | `EDIT` on the asset              | The asset; 409 while an ingest of the asset runs.                                                |

The dump is computed from the current definition of the run name
(`App\Api\Serializer\Normalizer\WorkflowStateDumpNormalizer`): the jobs of an integration added
since the run started show as not started, those of a removed integration disappear.

Triggering, rerunning and cancelling are written to the [audit log](./audit-log.md) on the
asset (**Workflow triggered**, **Workflow job rerun**, **Workflow cancelled**).

## Realtime

| Channel                      | Event             | Payload                 | When                                                        |
| ---------------------------- | ----------------- | ----------------------- | ----------------------------------------------------------- |
| `private-workflow-{id}`      | `job_update`      | `jobId`, `status`       | a job state is saved                                        |
| `private-workflow-{id}`      | `workflow_update` | `status`                | the status of the run is saved (start, end, rerun, cancel)  |
| `private-asset-{assetId}`    | `workflow_update` | empty                   | a run of the asset starts or changes status (not `Upload`)  |
| `private-asset-{assetId}`    | `asset_ingested`  | empty                   | an ingest run of the asset ends in `success` or `failure`   |

Subscribing to `private-workflow-{id}` requires `READ` on the run
(`App\Realtime\EntityChannelAuthorizer`); the asset channel only requires `READ` on the asset,
hence its empty payloads: the client reloads the runs it may read.

The run channel events are pushed synchronously, from the job itself, by
`Alchemy\WorkflowBundle\Listener\PusherListener`, and `asset_ingested` by
`App\Listener\AssetIngestWorkflowListener`: an unreachable Pusher server is only logged, never
failing the job, and nothing is pushed when no Pusher host is configured. The `workflow_update`
event of the asset channel goes through a `PusherMessage` on `p1`
(`App\Listener\AssetWorkflowRealtimeListener`). See [Realtime](./realtime.md).

## Client screens

- **Asset panel → Workflow** tab (`features/assets/manage/tabs/AssetWorkflowTab.tsx`): the
  runs of the asset, the last one first (title, start and end, status, **View**), and
  **Trigger workflow again**; **No workflow ran for this asset** when empty.
- **Run** (`/workflows/[id]`, `/workflows/[id]/jobs/[jobId]` for a selected job, opened as a
  dialog over the current page): the runs of the asset on the left (**Runs**, with **Run a new
  workflow**), the graph of the jobs by stage, the job panel on the right (**Status**,
  **Duration**, **#**, **Started at**, **Ended at**, **Job ID**, **Depends on**,
  **Condition**, **Inputs**, **Outputs**, **Errors**, the skip or disabled reason, **Rerun**,
  and **Edit integration** for a job of an integration). The header shows the asset, the
  dates, the duration, the event, **Refresh**, **Cancel**, and **Show details** (event
  inputs, **Context**). The screen follows the `private-workflow-{id}` events. Closing it leads
  back to the asset, on its Workflow tab, unless it was opened from the **Workflows** list.
- **Progress card** (`features/workflows/AssetWorkflowProgress.tsx`), over the media in the
  asset viewer and in the asset preview of the lists: the last run of the asset while it is
  `started` (a progress bar, **`{done}`/`{total}` jobs**, **Running:** with the names of the
  running jobs), then its status and end date when it ended in failure or was cancelled; a
  successful run hides it. In the viewer, an icon (**Open the run**) opens the run. The jobs
  over count whatever their outcome, the disabled ones are left out.
- **Workflows** (`/admin/workflows`, settings menu → **Workflows**, admins): every run of the
  instance, filtered by **All**, **Started**, **Success**, **Failure**, **Cancelled**
  (`?status=`), with **Refresh** and **Load more**.

The administration (EasyAdmin) has **Workflows → Workflow States** and **Job States**: filters
on status, name, asset, initiator and dates, **View** (opens the run in the client), and the
cancel, retry and rerun actions above.

## Required permissions

| Action                                             | Required                                                                  |
| -------------------------------------------------- | ------------------------------------------------------------------------- |
| List the runs of an asset (`GET /workflows?asset=`) | `EDIT` on every asset filtered on                                         |
| List every run (`GET /workflows` unfiltered)        | admin (`ROLE_ADMIN`: role `admin` or `databox-admin`); others get nothing |
| Read a run, follow its channel                      | `EDIT` on its asset, or admin                                             |
| Rerun a job, cancel a run                           | `EDIT` on its asset, or admin                                             |
| Run the ingest again (`trigger-workflow`)           | `EDIT` on the asset                                                       |

A run without asset (`Upload`) is only readable by admins (`App\Security\Voter\WorkflowStateVoter`).
The Workflow tab and the progress card are only shown to the editors of the asset (`edit`
capability).

## Console commands

```bash
# Assign the source file of an asset again: resets its analysis and duplicate links, then runs its ingest
dc run --rm databox-api-php su app -c "bin/console app:workflow:trigger-asset-ingest <assetId>"

# Run the ingest of every asset of workspaces (IDs or slugs; all by default), source left as it is
dc run --rm databox-api-php su app -c "bin/console app:workspace:ingest-assets [workspaces...]"

# Re-trigger the file analyses left pending (rerun, retry or new ingest), hourly by the scheduler
dc run --rm databox-api-php su app -c "bin/console app:file:unblock-analyses --dry-run"

# Print the stages and jobs of a run with their states
dc run --rm databox-api-php su app -c "bin/console alchemy:workflow:dump-workflow <workflowId>"
```

`app:workspace:ingest-assets` reports the assets ingested, skipped (no source) and in error;
`app:file:unblock-analyses` takes `--older-than` (seconds, `3600` by default), `--limit` and
`--dry-run` (see [Periodic tasks](../01_setup.md#periodic-tasks)). The library also registers
`alchemy:workflow:run <name>`, which starts a workflow without event: the shipped definitions
need the inputs of their event and are not meant to be started this way.

## Configuration

- `alchemy_workflow` (`databox/api/config/packages/alchemy_workflow.yaml`): the run entity
  (`doctrine.workflow_state_entity: App\Entity\Workflow\WorkflowState`), the Pusher channel
  prefix (`private-workflow-`; Pusher disabled in the test environment), `workflows_dirs`.
- The Messenger transports and the worker processes consuming them: [Databox workers](./workers.md).

## See also

- [Databox workers](./workers.md): transports of the jobs, priorities, bulk origin.
- [Integrations](./integration/01_integrations.md), [Renditions](./Renditions.mdx),
  [File analyzers](./FileAnalyzers.mdx), [Quarantine](./quarantine.md).
- [Operation tasks](./operation-tasks.md), [Automations](./automations.md),
  [Console commands](./console-commands.md), [Realtime](./realtime.md),
  [Asset lifecycle](./asset-lifecycle.md).

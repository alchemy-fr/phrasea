---
title: 'Saved search automations'
---

# Saved search automations

An automation applies an ordered list of actions to the results of a saved search, on demand or
on a cron schedule. It runs in a worker, **as the owner of the saved search**, and remembers the
assets it has already handled, so that each run can work on the new results only.

## Concepts

| Entity                  | Table                    | Role                                                                    |
|-------------------------|--------------------------|-------------------------------------------------------------------------|
| `SavedSearchAutomation` | `saved_search_automation` | the automation: saved search, schedule, scope, actions                  |
| `AutomationRun`         | `automation_run`         | one run: trigger, status, counters, results per action                  |
| `AutomationSeenAsset`   | `automation_seen_asset`  | the cursor: the assets the automation has handled                       |
| `AutomationShare`       | `automation_share`       | the share kept by a `share_export` action (see [Share export](./automation-share-export.md)) |

The entities live in `databox/api/src/Entity/Automation/`, the engine in
`databox/api/src/Automation/` (`AutomationRunner`, `AutomationAssetIterator`, `AutomationCursor`)
and the actions in `databox/api/src/Automation/Action/`.

### The automation

| Field             | Type                 | Default | Notes                                                                                     |
|-------------------|----------------------|---------|-------------------------------------------------------------------------------------------|
| `savedSearch`     | saved search         |         | required, set on creation only; the automation is deleted with its saved search           |
| `workspaceId`     | workspace            | none    | limits the results to this workspace (see [Workspace scope](#workspace-scope))            |
| `name`            | string (255)         |         | required                                                                                  |
| `enabled`         | bool                 | `true`  | a disabled automation is not scheduled, but can still be run on demand                    |
| `schedule`        | cron expression (100) | none   | none (empty string in the API) = on demand only                                           |
| `timezone`        | PHP timezone         | `UTC`   | timezone of the schedule                                                                  |
| `scope`           | `new` or `all`       | `new`   | `new`: the results not handled by a previous run; `all`: every result at each run         |
| `maxAssetsPerRun` | positive int         | `1000`  | see [Limit of assets per run](#limit-of-assets-per-run)                                   |
| `actions`         | list of `type` + `config` |    | at least one, applied in order                                                            |

Read-only fields: `nextRunAt`, `lastRunAt`, `lastRunStatus`, `consecutiveFailures`, `createdBy`
(the user who created it, for the audit only) and `capabilities` (`edit`, `delete`, `run`).

## Scheduling

`schedule` is a standard cron expression (`Cron\CronExpression`), evaluated in `timezone`
(`databox/api/src/Automation/Schedule/CronHelper.php`). It is refused when it is invalid, never
runs, or runs more often than every **5 minutes** (`CronHelper::MIN_INTERVAL_SECONDS`, checked on
its next occurrences).

`nextRunAt` (stored in the server timezone) is computed when the automation is created and each
time its `schedule`, `timezone` or `enabled` is written; it is null for a disabled automation or one
without schedule. Enabling an automation again also resets `consecutiveFailures`.

Every minute, the `main` Symfony Scheduler schedule (`databox/api/src/Schedule/MainSchedule.php`)
sends `DispatchDueAutomations`, handled in the `databox-scheduler` container by
`AutomationScheduler::dispatchDue()`. For each enabled automation whose `nextRunAt` is past, it
first moves `nextRunAt` to the next occurrence after now (missed occurrences are not replayed one by
one: one run catches up), then creates a pending run with the trigger `schedule`. A schedule that
cannot be computed any more sets `nextRunAt` to null and is logged. See
[Periodic tasks](../01_setup.md#periodic-tasks): a long periodic task of the scheduler delays the
automation triggers.

The same dispatch can be run once by hand, for development:

```bash
dc run --rm databox-api-php su app -c "bin/console app:automation:run-due"
```

## Runs

A run is created as `pending` (by the scheduler, or by the API for a manual run) and dispatched as
a `RunAutomation` message on the `task` transport (see [Databox workers](./workers.md)), where
`AutomationRunner::run()` executes it.

| Status      | Meaning                                                                                       |
|-------------|-----------------------------------------------------------------------------------------------|
| `pending`   | created, waiting for a worker                                                                 |
| `running`   | in progress                                                                                   |
| `success`   | finished, no asset failed                                                                     |
| `partial`   | finished, some assets failed: they stay new and are retried at the next run                   |
| `failed`    | the run itself failed (owner unavailable, automation deleted during the run, search error…), message in `error` |
| `skipped`   | another run of the same automation was in progress                                            |
| `cancelled` | defined in `AutomationRunStatusEnum`, set by no code path                                     |

One run at a time per automation: the runner holds the lock `automation_run_<id>` (10 minutes,
refreshed after each batch and between slow uploads). A run finding it taken ends as `skipped`.

A run records the trigger (`manual` or `schedule`), `dryRun`, the initiator of a manual run,
`startedAt`/`endedAt` and, updated after each batch:

- `matchedCount`: the results of the saved search the owner may read;
- `newCount`: those not handled by a previous run;
- `processedCount`: the assets every action succeeded on;
- `failedCount`: the assets an action failed;
- `limitReached`: whether eligible assets were left out by the limit of assets per run;
- `actionResults`: per action, `type`, `status` (`success`, `partial`, `failed`), the `processed`,
  `failed` and `skipped` counts and up to 100 `errors` (`assetId` and `message`; skip reasons and
  notices included).

The run status only depends on `failedCount` and on errors outside the actions: an action failing
in its final step (an email report, a webhook summary, the upload of a share export) shows as
`failed` in `actionResults` while the run is `success`. Runs are never purged: they go with their
automation.

### Processing order

The results are read from Elasticsearch (`AssetSearch::iterateIds()`, saved search conditions plus
the asset rights of the owner, in index order) by batches of 500, loaded by 200 and filtered again
with the asset `READ` vote. For each batch, the actions run in order; an asset failed by an action
is not handed to the next ones. An exception thrown by an action fails the assets it had not
reported yet; when it closes the entity manager, the next actions of the batch mark the remaining
assets failed (`Not run: a previous action failed`). Once the last batch is done, each action gets
a final step (`finish()`), used by the actions that aggregate the run (report, summary, upload).

As a failed asset is retried at the next run, actions are written to be idempotent.

### Dry run

A dry run (`dryRun: true`, manual only) only counts: it goes through every result, runs no action,
leaves the cursor untouched and sets `limitReached` when a real run would reach the limit. It does
not change `lastRunAt`, `lastRunStatus` nor `consecutiveFailures`.

### Failures and automatic disabling

After a real run, `lastRunAt` and `lastRunStatus` are updated. A `failed` scheduled run increments
`consecutiveFailures`; any other finished run resets it. The automation is disabled (and
`nextRunAt` cleared) when:

- the owner of the saved search is not available (no owner, or the IdP cannot give the identity
  of the user): on the first run that finds it so, whatever its trigger;
- its last 5 scheduled runs failed (`SavedSearchAutomation::MAX_CONSECUTIVE_FAILURES`).

The owner is then notified (`Automation "…" disabled`, topic `user:exception`).

## The cursor

`automation_seen_asset` holds, per automation, the assets already handled (with the run that
handled them). After each batch of a real run, the assets that **no action failed** are marked
seen, skipped ones included; a failed asset stays new. An asset marked seen stays so even if it
leaves the results and comes back later. Both scopes update the cursor: with `all`, it only feeds
`newCount`.

The cursor can be reset (`POST /saved-search-automations/{id}/reset-cursor`, `EDIT` on the
automation):

| `mode`    | Effect                                                                                                   |
|-----------|----------------------------------------------------------------------------------------------------------|
| `empty`   | forgets every handled asset at once: every result is new at the next run                                 |
| `current` | marks every current result (as seen by the owner) as handled, without running any action                |

`current` is dispatched as a `ResetAutomationCursor` message on `task`; it waits (retried) while a
run of the automation holds the lock.

## Limit of assets per run

A real run hands at most `maxAssetsPerRun` assets to the actions (failed ones included), then stops
and sets `limitReached`; `matchedCount` and `newCount` stop at the batch where the limit was hit.
With the scope `new`, the assets left out are still new and are handled by the next runs; with
`all`, each run handles the first results only. The actions still run their final step, told that
the run was truncated (`expose_publish` then removes nothing from the publication).

## Workspace scope

`workspaceId` limits the results of the saved search to one workspace. This filter is added to the
search at each run, apart from the saved search conditions, which cannot widen it. The workspace
must be readable by the user writing the automation, and the automation is deleted with it.

It is optional, except for the `share_export` action, which requires it (see
[Share export](./automation-share-export.md)). Some actions check their options against it
(`AutomationAwareActionInterface`) or against the scope (`ScopeAwareActionInterface`) when the
automation is written.

## Execution as the owner

The runs act as the **current owner of the saved search** (`UserContextSwitcher`): the worker
builds a security token for this user, with the roles and groups read from the IdP at each run
(`Impersonator::getIdentity()`), never a snapshot. Every check of the search and of the actions
goes through the voters with this token: an asset the owner may not read is not a result, an asset
the owner may not change is skipped. An owner holding `ROLE_ADMIN` runs with every right.

The audit log records the owner as the actor, with the source `automation`; a manual run is also
logged as `automation_triggered` (with `runId` and `dryRun`) by the user who asked for it (see
[Audit log](./audit-log.md)).

Changing the owner of a saved search disables its automations
(`SavedSearchOwnerChangeListener`): the new owner must enable them again.

The options of the actions are validated when the automation is written, with the rights of the
user writing it (a collection must accept the assets of this user…), and checked again at each run
with the rights of the owner.

## Secrets

The secret options of the actions (destination credentials, webhook secret) are encrypted when
written (`ActionConfigSecretsHandler`, with the databox secrets key pair), decrypted only by the
workers, which hold the secret key, and never output: the API returns them as `null` with a
`…Set` boolean flag (e.g. `passwordSet`). A secret sent empty keeps the stored value of the action
at the same position, when it has the same type. See [Secrets](./secrets.md).

## API

| Method   | Path                                           | Notes                                                                                   |
|----------|------------------------------------------------|-----------------------------------------------------------------------------------------|
| `GET`    | `/saved-search-automations?savedSearch={id}`   | the automations of a saved search, unpaginated, oldest first                             |
| `GET`    | `/saved-search-automations/{id}`               |                                                                                         |
| `POST`   | `/saved-search-automations`                    | body: the fields above, `savedSearch` (ID or IRI) required                              |
| `PATCH`  | `/saved-search-automations/{id}`               | partial update; `workspaceId: ""` removes the workspace, `schedule: ""` the schedule    |
| `DELETE` | `/saved-search-automations/{id}`               | deletes the runs and the cursor with it                                                  |
| `POST`   | `/saved-search-automations/{id}/run`           | body `{"dryRun": false}`, answers `201` with the pending run (allowed when disabled)     |
| `POST`   | `/saved-search-automations/{id}/reset-cursor`  | body `{"mode": "empty"}` or `{"mode": "current"}`, answers `204`                        |
| `GET`    | `/automation-runs?automation={id}`             | the runs, newest first, paginated                                                        |
| `GET`    | `/automation-runs/{id}`                        |                                                                                         |

An action is written as `{"type": "…", "config": {…}}`. Invalid options are reported on
`actions[i].config.<option>` (or on `scope` / `workspaceId` when that field is the one written).

## Client screens

- **Manage saved search** dialog (route `/saved-searches/[id]/manage/automations`), tab
  **Automations** (shown when the saved search has the `manageAutomations` capability): the list,
  with **New automation**, and per automation a menu with **Run now**, **Dry run**, **Edit**,
  **History**, **Reset the cursor** (**Process every result again** = `empty`, **Ignore the current
  results** = `current`) and **Delete**.
- Form **New automation** / **Edit automation**: **Name**, **Enabled**, **Trigger** (**On demand
  only**, **Every hour**, **Every day**, **Every week**, **Custom (cron expression)**),
  **Timezone** (the browser timezone by default), **Workspace** (**All workspaces** when empty),
  **Assets** (**New results only** / **All results**), **Ignore the existing assets** (creation
  with **New results only**: resets the cursor with `current` right after the creation),
  **Max. assets per run**, **Actions** with **Add an action**. A secret already set shows
  **Unchanged**.
- **History of "…"**: the runs with their status (**Pending**, **Running**, **Success**,
  **Partial**, **Failed**, **Skipped**, **Cancelled**), trigger (**Manual**, **Scheduled**),
  **Run by**, **Started at**, **Ended at**, the counts (`matched · new · processed · failed`), the
  per-action counts, **Limit reached**.
- In the list of the saved searches, the menu of a search has one sub-menu per automation with
  **Run now**, **Dry run** and **Edit**.

The admin (EasyAdmin, menu **Searches**) lists **Saved Search Automations**, **Automation Runs**
and **Automation Shares**, read-only.

## Permissions

`SavedSearchAutomationVoter` only grants the **owner of the saved search** (and an admin, through
`AdminVoter`), never a user granted on the saved search:

| Check                                   | On               | Used for                                     |
|-----------------------------------------|------------------|----------------------------------------------|
| `MANAGE_AUTOMATIONS`                    | the saved search | listing and creating its automations         |
| `READ`, `EDIT`, `DELETE`                | the automation   | reading, writing and resetting the cursor, deleting |
| `RUN`                                   | the automation   | running on demand                            |
| `READ` (`AutomationRunVoter`)           | a run            | whoever may read its automation              |

## Actions

The actions are services tagged `app.automation_action`, indexed by their `TYPE`
(`AutomationActionRegistry`). Each asset of a batch ends **processed**, **skipped** (not allowed,
nothing to do: considered handled, the reason goes to `errors`) or **failed** (retried at the next
run).

| `type`               | Client label                              |
|----------------------|-------------------------------------------|
| `move_to_collection` | **Move to a collection**                  |
| `add_to_collection`  | **Add to a collection**                   |
| `attribute`          | **Change an attribute**                   |
| `email_report`       | **Send an email report**                  |
| `remote_export`      | **Export to S3, SFTP or FTP**             |
| `share_export`       | **Share and export the links (XLSX)**     |
| `webhook`            | **Call a webhook**                        |
| `trigger_workflow`   | **Run the workflow**                      |
| `trash`              | **Move to the trash**                     |
| `set_privacy`        | **Change the privacy**                    |
| `expose_publish`     | **Publish to Expose**                     |

### Add to a collection (`add_to_collection`)

Adds each asset to a collection, keeping it where it is (same checks as adding assets to a
collection through the API, `AssetCollectionAdder`).

| Option         | Type          | Required | Default |
|----------------|---------------|----------|---------|
| `collectionId` | ID or IRI     | yes      |         |

The writer must be allowed to add assets to the collection. At run: a deleted collection fails
every asset; without the right to add assets to it, every asset is skipped; an asset the owner may
not read, or refused by the collection, is skipped.

### Move to a collection (`move_to_collection`)

Moves each asset to a collection of its workspace (same checks as moving an asset through the API),
through an `AssetMove` message; an asset already in this collection is counted as processed.

| Option         | Type          | Required | Default |
|----------------|---------------|----------|---------|
| `collectionId` | ID or IRI     | yes      |         |

A deleted collection fails every asset; without the right to add assets to it, every asset is
skipped; an asset of another workspace, or that the owner may not edit, is skipped.

### Change an attribute (`attribute`)

Changes a value of the assets through the batch attribute update (`BatchAttributeManager`). The
written values get the origin `machine` with the vendor `automation`.

| Option         | Type              | Required                  | Default | Notes                                                                       |
|----------------|-------------------|---------------------------|---------|-----------------------------------------------------------------------------|
| `definitionId` | ID or IRI         | yes                       |         | an editable attribute whose policy the writer may edit                      |
| `action`       | string            | no                        | `set`   | `set`, `add` (multi-valued only), `delete`, `replace`                       |
| `locale`       | string            | no                        | none    |                                                                             |
| `value`        | mixed             | for `add` and `replace`   | none    | `set`: the value (a list for a multi-valued attribute); `delete`: the values to remove, none = all of them; `replace`: the text to replace |
| `replaceWith`  | string            | no                        | `""`    | `replace` only                                                              |
| `regex`        | bool              | no                        | `false` | `replace` only, pattern of 1000 characters at most                          |
| `flags`        | string            | no                        | none    | `replace` only, regex flags                                                 |

The values are validated against the attribute type when written. At run: a deleted definition
fails every asset; the assets of another workspace than the definition, or whose attributes the
owner may not edit, are skipped; an error of the update fails the assets of the workspace (and the
batch when it closes the entity manager).

### Send an email report (`email_report`)

Collects the handled assets during the run and, at the end, sends a notification (topic
`automation:report`, channels email and in-app) to users, to the members of groups (resolved at the
end of the run) and to raw email addresses. Every asset is processed; as a failed asset of another
action is retried, an asset may be reported twice.

| Option          | Type            | Required | Default | Notes                                                       |
|-----------------|-----------------|----------|---------|-------------------------------------------------------------|
| `users`         | list of user IDs | one recipient at least | `[]` | 50 recipients at most, users, groups and emails together |
| `groups`        | list of group IDs |        | `[]`    |                                                             |
| `emails`        | list of emails  |          | `[]`    | deduplicated case-insensitively                             |
| `sendWhenEmpty` | bool            | no       | `false` | send a report when the run handled no asset                 |
| `maxListed`     | int, 1 to 200   | no       | `50`    | assets listed in the report, the others are only counted    |
| `subject`       | string (255)    | no       | none    | replaces the default subject                                |

The report gives the automation, the saved search, the scope, the count, the listed assets (title,
workspace, creation date) and a link to the saved search. With the notifications disabled, the
final step fails the action. See [Notifications](./notifications.md).

### Publish to Expose (`expose_publish`)

Publishes each asset to an Expose publication with the Expose access of the owner, who must be an
editor of the publication: the source file (cut to the region of interest unless ignored) and the
renditions used as preview and thumbnail. Each published asset is annotated with the automation
and the asset: a run only sends what changed since the previous one and only touches the assets of
its automation.

| Option          | Type      | Required | Default | Notes                                                                        |
|-----------------|-----------|----------|---------|------------------------------------------------------------------------------|
| `integrationId` | ID or IRI | yes      |         | an enabled Expose workspace integration the writer may interact with        |
| `publicationId` | ID        | yes      |         | the writer must have authorized the integration and be an editor of it       |
| `ignoreRoi`     | bool      | no       | `false` | publish the whole files                                                      |
| `removeMissing` | bool      | no       | `false` | mirror the results; requires the scope `all`                                 |

`publicationTitle` is stored with the configuration, for display. With `removeMissing`, the assets
of the automation that left the results are removed from the publication at the end of the run,
unless an asset failed or the run stopped at its limit (notice on the run).

At run: an asset the owner may not read, without source file, or whose region of interest cannot
be applied, is skipped. A missing or expired Expose access of the owner, a deleted or disabled
integration, a publication not found or not editable fails every remaining asset of the run
(`Expose access missing for the automation owner: authorize the Expose integration again`); any
other error fails the asset. The owner's Expose token is the integration user token (see
[Integrations](./integration/01_integrations.md)). The client form can also create a publication.

### Export to S3, SFTP or FTP (`remote_export`)

Uploads files of each asset to a remote destination: the source file, or renditions chosen by the
name of their definition.

| Option         | Type             | Required | Default                                  | Notes                                                 |
|----------------|------------------|----------|------------------------------------------|-------------------------------------------------------|
| `protocol`     | string           | yes      |                                          | `s3`, `sftp` or `ftp`                                 |
| `s3` / `sftp` / `ftp` | object    | yes      |                                          | the options of the protocol, below                    |
| `renditions`   | list of names    | no       | `[]`                                     | a name readable in at least one workspace; none = the source file |
| `pathTemplate` | string (1000)    | no       | `{collection}/{assetId}-{rendition}.{ext}` | no `..`, no backslash                                |
| `overwrite`    | bool             | no       | `false`                                  | otherwise an existing file is kept and counted as exported |

Path variables: `{assetId}`, `{title}` (slugified name, or the ID), `{rendition}` (`source` for the
source file), `{ext}` (a missing extension drops its dot), `{originalName}` (for a rendition, the
source name with the rendition extension), `{workspace}` (slug), `{collection}` (slugified name of
the reference collection), `{date}` (`Y-m-d` creation date of the asset, so that a retried asset
goes to the same place). Each value is reduced to one path segment.

| Protocol | Option            | Required | Default     | Secret |
|----------|-------------------|----------|-------------|--------|
| `s3`     | `bucket`          | yes      |             |        |
|          | `region`          | no       | `us-east-1` |        |
|          | `endpoint`        | no       | AWS         |        |
|          | `pathStyle`       | no       | `false`     |        |
|          | `accessKeyId`     | yes      |             |        |
|          | `secretAccessKey` | at run   |             | yes    |
| `sftp`   | `host`            | yes      |             |        |
|          | `port`            | no       | `22`        |        |
|          | `username`        | yes      |             |        |
|          | `password`        | no       |             | yes    |
|          | `privateKey`      | no       |             | yes (the key content, not a path) |
|          | `passphrase`      | no       |             | yes    |
|          | `hostFingerprint` | no       |             |        |
|          | `root`            | no       | `""`        |        |
| `ftp`    | `host`            | yes      |             |        |
|          | `port`            | no       | `21`        |        |
|          | `username`        | yes      |             |        |
|          | `password`        | no       |             | yes    |
|          | `ssl`             | no       | `false`     |        |
|          | `passive`         | no       | `true`      |        |
|          | `root`            | no       | `""`        |        |

`host` is a host name or an IP address, without scheme nor port. Connections time out after 30
seconds and are opened at the first upload of the run.

At run: an asset the owner may not read, or without any file to export (a rendition missing, not
built, not fetchable or not readable is left out), is skipped. An unreachable destination
(connection, authentication, host key, unknown bucket…) fails every remaining asset of the run
(`Destination unavailable: …`); any other upload error fails the asset.

### Share and export the links (`share_export`)

Adds the assets to a share link and uploads an XLSX file of their public URLs to S3, SFTP or FTP.
At most one per automation, and it requires the workspace of the automation. Its options, columns,
share and failure behavior are described in [Share export](./automation-share-export.md); its
secrets are those of `remote_export`.

### Call a webhook (`webhook`)

Calls a URL with the assets as JSON.

| Option              | Type              | Required | Default     | Notes                                                                          |
|---------------------|-------------------|----------|-------------|--------------------------------------------------------------------------------|
| `url`               | string (2048)     | yes      |             | absolute `http(s)` URL; private and local hosts refused outside the `dev` environment |
| `method`            | string            | no       | `POST`      | `POST` or `PUT`                                                                |
| `headers`           | map name to value | no       | none        | 20 at most; `Host`, `Content-Type`, `Content-Length` and the `X-Phrasea-*` headers are reserved |
| `secret`            | string            | no       | none        | secret; signs the body                                                         |
| `mode`              | string            | no       | `per_batch` | `per_batch` or `summary`                                                       |
| `includeAttributes` | bool              | no       | `false`     | adds the attribute values of each asset                                        |

The payload carries `event`, `automation` (`id`, `name`), `savedSearch` (`id`, `name`) and `run`
(`id`, `trigger`). Each asset is described by `id`, `title`, `workspaceId`, `url` (its page in the
client) and, with `includeAttributes`, `attributes` (by attribute slug, then by locale).

- `per_batch`: one call per batch of assets (event `automation.batch`, with `part` and `assets`);
  a failed call fails the assets of the batch, retried at the next run.
- `summary`: one call at the end of the run (event `automation.summary`, with `total`, `truncated`
  and up to 10,000 `assets`), none when no asset was processed; a failed call fails the action
  only, the assets are not retried.

Headers `X-Phrasea-Event` (the event) and, with a secret, `X-Phrasea-Signature`
(`sha256=` followed by the hex HMAC-SHA256 of the body). Timeout 10 seconds, 30 seconds overall,
3 redirects at most; outside `dev`, private and loopback addresses are refused again after DNS
resolution and on each redirect. A status outside 2xx is a failure.

### Run the workflow (`trigger_workflow`)

Runs the ingest workflow of each asset again, on behalf of the owner (same check as
`PUT /assets/{id}/trigger-workflow`). No option. An asset the owner may not edit is skipped; an
error while starting the workflow fails the asset. Started from the `task` transport, the jobs of
these workflows run on the bulk transports (see [Databox workers](./workers.md#bulk-origin) and
[Workflows](./workflows.md)).

### Move to the trash (`trash`)

Moves the assets to the trash (soft delete, same check as `DELETE /assets`), through an
`AssetsDelete` message. No option. An asset the owner may not delete is skipped; an asset already
deleted is processed.

### Change the privacy (`set_privacy`)

Sets the privacy of the assets, as `PATCH /assets/{id}` does; the updated assets are reindexed.

| Option    | Type            | Required | Notes                                                                                                   |
|-----------|-----------------|----------|---------------------------------------------------------------------------------------------------------|
| `privacy` | int or key      | yes      | `0` `secret`, `1` `private_in_workspace`, `2` `public_in_workspace`, `3` `private`, `4` `public_for_users`, `5` `public` (stored as the int) |

An asset the owner may not edit, or whose permissions the owner may not edit, is skipped; an asset
already at this privacy is processed.

## Related pages

- [Share export](./automation-share-export.md)
- [Databox workers](./workers.md)
- [Periodic tasks](../01_setup.md#periodic-tasks)
- [Secrets](./secrets.md)
- [Audit log](./audit-log.md)
- [Permissions](./permissions.md)
- [Notifications](./notifications.md)
- [Shares](./shares.md)
- [Workflows](./workflows.md)

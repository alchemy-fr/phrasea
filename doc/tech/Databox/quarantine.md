---
title: 'Quarantine'
---

# Quarantine

An asset whose file is rejected by the analyses (a duplicate, an unexpected content…) is put
in **quarantine**: it is hidden from the users who do not review the quarantine, and is not
processed further until someone accepts it, merges it into an existing asset, or deletes it.

See also [File analyzers](./FileAnalyzers.mdx) and [access control](./access-control.md).

## Asset status

`App\Entity\Core\AssetStatusEnum`:

| Value | Case          | Client label  |
|-------|---------------|---------------|
| 0     | `Accepted`    | "Accepted"    |
| 1     | `Pending`     | "Pending"     |
| 2     | `Quarantined` | "Quarantined" |

A new asset gets the default status of its workspace (`assetDefaultStatus` in the workspace
configuration, "Default asset status" in the workspace form; `Accepted` when unset).

## File analysis

The analysis of a file is stored in `App\Entity\Core\FileAnalysis`: a status (`success`,
`failed`, `skipped`, `bypassed`), a message, the results of each analyzer (`name`, `output`,
`actions`) and a hash of the analyzer configuration, used to tell when a file needs a new
analysis. The API exposes it as `analysisState` (`App\Entity\Core\FileAnalysisStateEnum`:
`not_analyzed`, `not_applicable`, `passed`, `failed`, `skipped`, `bypassed`).

The workspace setting **"Requires file analysis"** (`fileAnalysisRequired`, default `false`)
keeps the files whose analysis is pending from being processed. When it is set at the
creation of a workspace, `App\Service\Workspace\WorkspaceCreator` also adds a File Analyzer
integration (analyzers `checksum` and `doc_unique_id`, quarantine on rejection, from
`databox/api/src/Service/Workspace/fileAnalyzers.yaml`), and makes the main renditions wait for it.

`App\Service\Asset\FileProcessingGuard` refuses to process (renditions, analyses, external
services) a file whose content was rejected, whose analysis failed, or whose analysis is
pending in a workspace requiring it, the base renditions aside. A bypassed file is processed
again. A workflow job refused this way fails with the reason as its error (see
[Refused jobs](./workflows.md#refused-jobs)).

## What quarantines an asset

- **The File Analyzer integration** (`core.file_analyzer`, job `analyze` of the
  `asset_ingest` workflow, `heavy` queue): when an analyzer rejects the file, the job fails
  and applies the actions on rejection (`actions_on_reject` of the integration and the
  actions of the analyzers, `FileAnalyzerAssetActionEnum`): `quarantine`, `move_to_trash`,
  or `delete` (removes the asset and its file, and wins over the others). An accepted file
  makes the asset `Accepted`. The analyzers are in
  `databox/api/src/Border/FileAnalyzer/Analyzer/` (see [File analyzers](./FileAnalyzers.mdx)).
- **The content check** of the ingest workflow (`App\Service\Workflow\Action\CheckFileContentAction`):
  a file whose detected type is refused is marked as rejected and its asset quarantined. A
  later check accepting the content brings the asset back to the default status.
- **A refused import** of the source file (`ImportSourceFileAction`).

### Duplicates

The duplicates found by an analyzer (the `checksum` analyzer, for instance) are stored as
`App\Entity\Core\FileDuplicate` rows (file, duplicate file, analyzer), maintained by
`App\Border\FileAnalyzer\FileDuplicateManager`. They are the source of truth: the analysis
results hold no file id.

When a duplicate disappears (deleted, trashed, its source replaced),
`App\File\FileAnalysisReevaluator` updates the analyses of the files it duplicated, and
releases their assets from quarantine when no error is left; when it is restored, the
analyses fail again and the accepted assets go back to quarantine.

Analyses stuck in progress are unblocked hourly by `app:file:unblock-analyses`.

## Notification

When an asset enters the quarantine, whatever put it there, its owner gets the
`asset:quarantined` [notification](./notifications.md): in the app right away, by email in a
digest per workspace. It links to the review screen opened on the asset
(`/quarantine?asset={id}`). `App\Doctrine\Listener\AssetQuarantineListener` detects the status
change and `NotifyAssetQuarantinedHandler` sends it, unless the asset left the quarantine or
was deleted in between. Nothing is sent while notifications are disabled (the
`X-Notification-Disabled` header of the request behind the change), nor to an owner id that
is not a user UUID. The quarantine managers are not notified.

## Rights

| Right (`AssetVoter`) | Granted to                                                                 |
|----------------------|----------------------------------------------------------------------------|
| `QUARANTINE`         | the asset owner, the workspace owner, `OWNER` on the asset, metadata `PERM_QUARANTINE` (3) on the asset, its collections or the workspace, `OWNER` on a collection or the workspace |
| `QUARANTINE_BYPASS`  | `QUARANTINE`, plus `OWNER` or metadata `PERM_QUARANTINE_BY_PASS` (4) on a collection or the workspace (or their creator) |

In the permission editors of the workspaces and collections, the metadata are labeled
"View quarantined" and "Bypass quarantine". Reading a non-accepted asset requires
`QUARANTINE`.

## Visibility

- Searches only return `Accepted` assets by default (`AssetSearch::createBaseFilterQueries()`).
- A search filtering on the status (`@assetStatus`) returns the quarantined and pending
  assets the user may review: their owner, and the users and groups of the `quarantineUsers`
  and `quarantineGroups` fields of the asset document, computed by
  `App\Elasticsearch\AssetPermissionComputer` from the `OWNER` ACEs and the
  `PERM_QUARANTINE` metadata. Anonymous visitors get nothing.
- Similar asset searches, [shares](./shares.md) and the Expose synchronization of
  [baskets](./baskets.md) never deliver quarantined or pending assets.

## Resolutions

| Resolution        | API                                                     | Right                          | Effect |
|-------------------|---------------------------------------------------------|--------------------------------|--------|
| Bypass            | `POST /assets/{id}/quarantine-bypass`                   | `QUARANTINE_BYPASS`            | The analysis becomes `bypassed` (results kept) and the asset `Accepted`. Logged as "Quarantine bypassed" in the [audit log](./audit-log.md). |
| New version       | `POST /assets/{id}/add-as-version` `{targetAssetId}`    | `QUARANTINE_BYPASS`, `EDIT` on the target | The file becomes the source of the target asset (same workspace), its previous source a [version](./asset-lifecycle.md#file-versions); the quarantined asset is deleted. |
| Move to trash     | `POST /assets/delete-multiple`                          | `DELETE`                       | See [asset lifecycle](./asset-lifecycle.md#trash). |
| Delete for good   | `POST /assets/delete-multiple` with `hardDelete`        | `DELETE`                       | No trash. |

`GET /assets/{id}/duplicates` lists the duplicates of an asset (`AssetDuplicatesProvider`).

### Batch resolution

Up to 100 assets per request, `App\Service\Asset\QuarantineResolver` checking each one again:

- `POST /assets/quarantine/prepare-batch` `{ids}` (`QuarantineBatchPrepareProcessor`) answers,
  per asset, which actions are possible (`bypass`, `trash`, `delete`, `mergeAsVersion`,
  `mergeDiscard`), why the others are not (`reasons`), and its duplicates;
- `POST /assets/quarantine/batch` (`QuarantineBatchProcessor`) applies one action.

```json
{"action": "merge", "items": [{"id": "…", "targetId": "…"}], "addAsVersion": true}
```

`action` is `bypass`, `merge`, `trash` or `delete`. A merge with `addAsVersion` adds the file
as a version of `targetId` (optional when exactly one duplicate can receive it); without, it
deletes the incoming asset. The answer counts the `processed`, `skipped` and `failed` assets,
with a reason for each one left: `not_found`, `not_quarantined`, `forbidden`,
`no_duplicate`, `no_valid_target`, `target_required`, `invalid_target`, `error`. One asset
failing does not stop the others.

## Client

**Quarantine** in the collections panel opens the review screen `/quarantine`
(`?asset={id}` to open one asset; `features/assets/quarantine/QuarantineScreen.tsx`); its
icon applies the status filter to the search instead ("List quarantined assets in the
search"). The banner "This asset is quarantined" of a quarantined asset links to it.

- The queue lists the quarantined assets ("… assets to review", "Nothing in quarantine"),
  "Skip" passes to the next one, "Open in search" opens the queue in the search.
- The "Analysis report" shows the analysis message and, per analyzer, the level and the
  message of each result.
- "Possible duplicates" lists the duplicates.
- "Resolve" offers "Bypass", "Merge duplicates", "Move to trash" and "Delete permanently".
- "Merge duplicates" picks the existing asset, then, attribute by attribute, the "Incoming
  file" or "Existing asset" value; then "Keep the incoming file as a new version of the
  existing asset" or "Discard the incoming file".
- Batch: "Select" / "Select all" (Shift+click selects a range), then the same actions on the
  selection; a batch merge keeps the values of the existing assets ("The values of the
  existing assets are kept."). Deleting for good asks to type "Delete".

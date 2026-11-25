---
title: 'Export'
---

# Export

An export packs renditions of a set of assets, or of basket items, into a ZIP archive built
by a worker. The client follows its progress and downloads the archive when it is ready.

## Model

`App\Entity\Core\AssetExport` (API resource `asset-export`):

| Field          | Meaning                                                                       |
|----------------|-------------------------------------------------------------------------------|
| `assets`       | The asset ids, unless `basketAssets` is given.                                |
| `basketAssets` | Basket item ids: their extract, or else the region of interest of their asset, applies. |
| `renditions`   | The rendition definition ids to export, at least one.                         |
| `ignoreRoi`    | Export the whole files, ignoring the [regions of interest](./roi.md) and the extracts. Default `false`. |
| `status`       | `0` pending, `1` in progress, `2` ready, `3` failed (`App\Model\ExportStatusEnum`). |
| `downloadUrl`  | Signed URL of the archive, once ready.                                        |
| `ownerId`, `userData` | The requester, kept for the permission checks of the worker.           |

## API

| Method and path            | Security                                                         |
|----------------------------|------------------------------------------------------------------|
| `POST /asset-exports`      | `READ` on every asset, and on the baskets of the basket items    |
| `GET /asset-exports/{id}`  | the owner of the export only (`App\Security\Voter\AssetExportVoter`), since the answer holds a signed URL |

`App\Api\Processor\AssetExportProcessor` logs an "Asset downloaded" entry of the
[audit log](./audit-log.md) per asset, then dispatches the `AssetExportProcess` message.

## Pipeline

`AssetExportProcess` runs on the `heavy` transport (see [workers](./workers.md)), in
`App\Consumer\Handler\Asset\AssetExportProcessHandler`:

1. The export goes in progress.
2. For each item, the requested renditions the user may read (rendition policy, see
   [access control](./access-control.md#rendition-policies)) and the readable
   attachments of the asset are downloaded **in parallel**, `EXPORT_DOWNLOAD_CONCURRENCY` at a
   time (`App\Service\Asset\FileFetcher`); the items are processed in batches.
3. Then, item by item: the region of interest or the extract is applied to a temporary copy
   (a file that cannot be clipped is left out, never exported whole), and the metadata are
   written when the rendition definition asks for it.
4. The [terms](./workspace-terms.md) PDF of each workspace is added when the workspace
   attaches its terms to exports.
5. The archive is built natively (`ZipArchive`, `Alchemy\CoreBundle\Util\ZipUtil`), entries
   under `content/`, and stored under `exports/` in the storage; the export is ready.

A failed rendition download fails the export; a failed attachment download is logged and
skipped. An export without any file (no readable rendition) fails with "No files to export
(insufficient permissions)."

Entry names:

- rendition: `{rendition}-{asset name}-{asset or basket item id}.{ext}`;
- attachment: `attachments/{name}-{attachment id}.{ext}`;
- terms: `terms-{workspace slug}-v{version}.pdf`.

The `downloadUrl` is signed for `S3_REQUEST_SIGNATURE_TTL` seconds (default 3600). The stored
archives are not purged by Databox.

## Configuration

| Variable                      | Default | Meaning                                            |
|-------------------------------|---------|----------------------------------------------------|
| `EXPORT_DOWNLOAD_CONCURRENCY` | `8`     | Parallel downloads of an export; `1` downloads one file at a time. |
| `S3_REQUEST_SIGNATURE_TTL`    | `3600`  | Lifetime of the signed download URL, in seconds.   |

## Progress

The worker pushes `progress`, `ready` and `error` events on the private channel
`export-{id}` (see [realtime](./realtime.md)). The client (`features/assets/ExportWatcher.tsx`)
also polls `GET /asset-exports/{id}` (after 2 seconds, then every 5 seconds) until the export
is ready or failed, and starts the download.

## Client

The asset action **Export** opens the export dialog (`features/assets/actions/ExportDialog.tsx`):

- "Select the renditions to include in the export.": the rendition definitions of the
  workspaces of the assets, grouped by workspace;
- "Export the whole files (ignore the regions of interest and the extracts)", shown when an
  asset has a region of interest or an extract;
- for each workspace whose current terms the user has not signed, the terms and "I have read
  and accept the Terms & Conditions": the export is only sent once they are all accepted, and
  they are signed first (`POST /workspaces/{id}/terms/sign`). The API refuses the export of
  an unsigned workspace anyway, since reading its assets requires the signature.

Toasts: "Preparing export…", "Export ready!" with "Download", "Export failed".

Exporting the items of a [basket](./baskets.md) sends `basketAssets`, so that their extracts
apply.

## Administration

The EasyAdmin page **Asset Exports** lists the exports (owner, status, assets, renditions),
read-only.

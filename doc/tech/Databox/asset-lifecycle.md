---
title: 'Asset lifecycle'
---

# Asset lifecycle

What happens to an asset after its creation: trash and restoration, copy and move between
collections and workspaces, ordering, versions of its source file, attachments, alternate
URLs, and assets without file.

## Trash

An asset is **trashed** (`deletedAt`, `App\Entity\Traits\DeletedAtTrait`) when it is
deleted, or when its reference collection or its workspace is. A trashed asset is only
readable by the users allowed to delete it, and searches leave it out unless they filter
on `@deleted`.

| Method and path                  | Security                    | Effect |
|----------------------------------|-----------------------------|--------|
| `POST /assets/prepare-delete`    | authenticated               | `{ids}`: whether they may be deleted, the collections they may be removed from instead, the number of [shares](./shares.md) holding them. |
| `POST /assets/delete-multiple`   | `DELETE` on each asset, or `EDIT` on each collection | `{ids, collections?, hardDelete?}`: with `collections`, only removes the assets from these collections (not from their reference collection); otherwise trashes them, or deletes them for good with `hardDelete`. |
| `POST /assets/restore-multiple`  | `DELETE` on each asset      | `{ids}`: restores them. |
| `DELETE /assets/{id}`            | `DELETE`                    | Deletes the asset **for good**, without trash. |

Deleting and restoring run in workers (`AssetsDelete`, `AssetsRestore`). Trashing a
[story](./stories.md) trashes its collection; deleting or restoring an asset updates the
duplicate analyses of the [quarantine](./quarantine.md).

`app:trash:empty` (daily at midnight) deletes for good the collections and assets trashed
for longer than the **trash retention** of their workspace (`trashRetentionDelay`, 30 days by
default; "Trash retention (days)" in the workspace form).

Client: **Trash** in the collections panel lists the trashed assets. "Delete" opens a dialog
offering "Move to trash" or "Only remove from collections:", and warns when assets are shared
with a public link; on trashed assets it becomes "Delete permanently", confirmed by typing
"Delete". "Restore" moves the assets back to their collections; an asset whose collection is
itself trashed needs the collection restored first.

## Copy

`POST /assets/copy` (`App\Api\Processor\CopyAssetProcessor`) with:

| Field            | Meaning                                                        |
|------------------|----------------------------------------------------------------|
| `ids`            | The assets, each readable.                                     |
| `destination`    | A collection or workspace IRI, where the user may create assets. |
| `byReference`    | Add the asset to the destination instead of duplicating it. Default `false`. |
| `withAttributes` | Copy the attributes of a duplicate. Default `false`.           |

Each asset is copied by a worker (`AssetCopy`, `App\Service\Asset\AssetCopier`):

- **by reference** (the user may edit the asset and the destination is in its workspace): the
  same asset is added to the destination collection;
- **duplicate in the same workspace**: a new asset owned by the user, sharing the source file,
  with the renditions the user may read, and the attributes if asked;
- **duplicate in another workspace**: the source file is copied and ingested again, without
  the attributes nor the renditions; an asset without file is copied bare.

Client: "Copy" → "Destination", "Copy by reference (shortcut)" (checked by default) or
"Copy attributes"; across workspaces, "Assets cannot be moved or linked across workspaces:
they will be duplicated."

## Move

`POST /assets/move` with `{ids, destination}` (`App\Api\Processor\MoveAssetProcessor`):
`EDIT` on each asset, creation right on the destination. The assets never leave their
workspace (400 otherwise). A worker (`AssetMove`) changes their reference collection (or puts
them at the workspace root); the other collections holding them are kept.

Client: "Move" ("Assets are moved within their workspace."), or drag and drop onto a
collection: move by default, Ctrl to add, Ctrl+Shift to duplicate.

## Positions

Every collection orders its assets (`CollectionAsset.position`). `PUT /assets/{id}/position`
with `{destination, position}` moves an asset in a collection or a [story](./stories.md)
(`EDIT` on the collection), and `GET /collections/{id}/assets` lists the assets of a
collection in that order. The client reorders the items of stories.

## File versions

Replacing the source file of an asset keeps the previous one as an
`App\Entity\Core\AssetFileVersion` (`App\Doctrine\Listener\AssetListener`), named after
its `versionName` or its date (`v-Y-m-d_H-i-s`).

- The source is replaced with `PATCH /assets/{id}` (`sourceFile` or `sourceFileId`); the
  renditions are generated again.
- `POST /assets/{id}/add-as-version` makes the file of a quarantined asset the new source of
  another asset (`App\Api\Processor\AddAsAssetVersionProcessor`, see [quarantine](./quarantine.md)).
- `GET /asset-file-versions?assetId=…`, `GET` and `DELETE /asset-file-versions/{id}` follow the
  rights on the asset (`AssetFileVersionVoter`).

Client: asset panel → **Versions** (editors), with download, "Save as…" (a "New asset", a
"Rendition of this asset", or "Replace source file of this asset") and delete. "Replace source
file": "The current file is kept as a version. Renditions will be regenerated."

## Relationships

`App\Entity\Core\AssetRelationship` links a new asset to the asset (and possibly the file and
the integration) it was made from, with a `type`: the client uses `attachment` and `save_as`.
It is set on creation only (`relationship` of `POST /assets`), the source being readable, and
has no endpoint of its own; the EasyAdmin page **Asset Relationships** lists them.

## Attachments

An attachment (`App\Entity\Core\AssetAttachment`) attaches an asset to another of the same
workspace, with a `name` and a `priority`.

| Method and path                 | Security                                          |
|---------------------------------|---------------------------------------------------|
| `GET /attachments?assetId=…`    | `READ` on the host asset                          |
| `POST /attachments`             | `EDIT` on the host, `READ` on the attached asset  |
| `PATCH`, `DELETE /attachments/{id}` | `EDIT` on the host                            |

Attachments go along in [exports](./export.md) and [shares](./shares.md).

Client: asset side panel → **Attachments**: "Add attachment" uploads a file or a URL as a new
asset and attaches it; "Detach" removes the link ("The attached asset is kept."); delete
removes the link and trashes the attached asset.

## Alternate URLs

A file can carry alternate URLs, by type (`alternateUrls` of the file input: `type`, `url`;
any URI scheme but an executable one, such as `indexer:///…`). `App\Entity\Core\AlternateUrl` gives a type its
label in a workspace (EasyAdmin **Alternate URLs**, workspace templates). The client lists the
http(s) alternate URLs of the source in the asset menu. They are not given to
[share](./shares.md) visitors.

## Assets without file

`POST /assets` without any source creates an asset without file. In the upload dialog: "No
file" ("The asset is created without any file: one can be attached later."), with an "Asset
title". The viewer shows "No source file" and, to editors, "Add a source file"; its renditions
are generated once a file is added.

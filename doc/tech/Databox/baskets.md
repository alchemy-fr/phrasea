---
title: 'Baskets'
---

# Baskets

A basket is a personal selection of assets, across collections and workspaces, that its
owner can share with other users, export, or publish on Expose. Each item can carry its own
extract of the media.

## Model

- `App\Entity\Basket\Basket`: `name`, `description`, `ownerId`, `archivedAt`, and its items.
  ACL object type `basket`.
- `App\Entity\Basket\BasketAsset`: an item, an asset in a basket, with its `position` and a
  `context` whose `clip` is the **extract** of the item: time ranges and crop, as for the
  [regions of interest](./roi.md). An item without extract inherits the region of interest of
  its asset; an extract replaces it as a whole.

## Visibility and rights

`GET /baskets` lists, for every user **admins included**, the baskets they own and the
baskets shared with them or with one of their groups (`App\Elasticsearch\BasketSearch`).
`AdminVoter` still opens any basket to an admin by its id, and the EasyAdmin page lists them
all (see [instance permissions](./permissions.md#defaults)).

`App\Security\Voter\BasketVoter`:

| Right              | Granted to                                                          |
|--------------------|---------------------------------------------------------------------|
| `CREATE`           | every authenticated user                                            |
| `READ`             | the owner, `VIEW`                                                   |
| `EDIT`             | the owner, `EDIT`                                                   |
| `DELETE`           | the owner, `DELETE`                                                 |
| `SHARE`            | the owner holding the "Share own baskets" capability, `SHARE` or `OWNER` |
| `EDIT_PERMISSIONS` | the owner holding the "Share own baskets" capability, `OWNER`       |

The capability is an [instance permission](./permissions.md); baskets shared before their
owner lost it stay shared.

## API

| Method and path                         | Security | Effect |
|-----------------------------------------|----------|--------|
| `GET /baskets`                          | authenticated | `query` (name, description), `archived`, `includeArchived`, `order=createdAt` (newest first; by name otherwise), `limit` (30 at most), `page`. |
| `POST /baskets`                         | `CREATE` | |
| `GET`, `PATCH`, `DELETE /baskets/{id}`  | `READ`, `EDIT`, `DELETE` | |
| `POST /baskets/{id}/archive`, `/unarchive` | `EDIT` | |
| `POST /baskets/{id}/assets`             | `EDIT`, `READ` on each asset | `assets[]`: `{id, clip?}`. |
| `POST /baskets/default/assets`          | authenticated | Adds to the oldest basket of the user, created if none. |
| `POST /baskets/{id}/remove`             | `EDIT`   | Removes items. |
| `GET /baskets/{id}/assets`              | `READ`   | The items, by position. |
| `PATCH /basket-assets/{id}`             | `EDIT` on the basket | `{clip}`: sets or removes (`null`) the extract. |

Adding, removing and changing an extract, as well as changing the region of interest of an
asset, send a `BasketUpdate` message, coalesced so that at most one copy waits in the queue (see
[message deduplication](./message-deduplication.md)), which updates the Expose
publications of the basket.

## Archiving

Archiving a basket sets `archivedAt`. `GET /baskets` leaves archived baskets out by default.
The client hides them from the basket switcher, refuses them as drop targets, and shows them
with "Display archived"; "Unarchive" brings one back. Archiving is recorded in the
[audit log](./audit-log.md).

## Extracts

"Cut / crop" on a basket item edits its extract: "The file is not modified: the extract
applies to this basket item when it is played, published or exported." The items show
whether they have an "Extract of this basket item", inherit the "Region of interest of the
asset", or are "Cropped". The [export](./export.md) of basket items applies their extracts,
unless the whole files are asked for.

## Expose publication

A basket can be synchronized with one or several Expose publications, through the
`phrasea.expose` integration (`App\Integration\Phrasea\Expose\ExposeIntegration`, see
[integrations](./integration/01_integrations.md)). Its actions are called with
`POST /integrations/{integrationId}/actions/{action}`:

| Action       | Body                                 | Effect |
|--------------|--------------------------------------|--------|
| `sync`       | `{basketId, publication, ignoreRoi}` | Creates the publication and synchronizes it. Requires the user to have authorized the integration and to read the basket. `ignoreRoi` is fixed for the life of the publication. |
| `force-sync` | `{id}`                               | Synchronizes again. |
| `stop`       | `{id, deletePublication}`            | Stops the synchronization; the publication is kept on Expose unless `deletePublication`. |

The synchronization (`ExposeSynchronizer`, `SyncBasket` message) publishes, as the user who
set it up, the items with a source file that this user reads, neither trashed, pending nor
quarantined, with their extracts unless `ignoreRoi`. Assets published on the same publication
by other means are left alone; the assets Expose refuses are reported as skipped. A missing
authorization or an unavailable user stops it, with a notification.

Client: basket management → **Integrations**: "Authorize", "New publication" (title, slug,
parent publication, publication profile, "Publish the whole files (ignore the regions of
interest and the extracts)"), "Create & sync", then per publication "Force sync" and "Stop"
("Also delete the publication from Expose"). The progress is shown in real time.

## Client

- The baskets panel and the basket switcher: "Create basket", "Set as current", "Switch
  basket", "Add selection to basket", and the asset action "Add to basket".
- `/baskets/{id}/view` shows the items ("This basket is empty" otherwise), with "Remove from
  basket" and "Cut / crop".
- `/baskets/{id}/manage/{tab}`: "Manage basket", tabs `info`, `edit`, `permissions` (for who
  may edit the permissions), `operations` (deletion) and `integrations`.

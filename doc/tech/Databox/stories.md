---
title: 'Stories'
---

# Stories

A **story** is an asset grouping other assets in an order of its own: a report and its
photos, the pages of a document, the takes of a shoot. It has its own attributes, a cover,
and a contact sheet of its items.

## Model

A story is an `App\Entity\Core\Asset` whose `storyCollection` is set (`Asset::isStory()`).
This collection is created with the story (`AssetManager::turnIntoStory()`), has no name,
never changes, and points back to its asset (`Collection::$storyAsset`). Its items are
ordinary `CollectionAsset` rows, ordered by their `position` (from 0, kept dense; new items
go at the end).

Attribute and rendition definitions apply to assets, stories or both (`target`:
`AssetTypeEnum`, labels "Assets", "Stories", "Assets and stories" of the "Applies to"
field).

The name of a story comes from its attributes whose definition applies to stories and has a
"Name priority" (`App\Service\Asset\Attribute\AssetNameResolver`); a story without such a
value, and without cover to take the file name from, shows "Untitled story".

## Creating a story

- **From a selection**: `POST /assets` with `isStory: true`, `storyAssets` (the item ids, in
  order) and optionally `storyCover`, one of them. Every item must be readable and belong to
  the workspace of the story. `storyAssets` and `storyCover` are only accepted at creation.
  In the client, the asset action "Create a story…" opens a dialog with "Story name" and
  "Story cover"; assets of several workspaces cannot be grouped.
- **At upload**: `POST /assets/multiple` with `isStory` and `story` (`name`, `attributes`).
  The story is created first, named after `story.name`, else the first asset, else "Story";
  the uploaded assets get its collection as reference collection. In the upload dialog:
  "Create a story with these files" (or "Create a story" with "No file", for an empty
  story), "Story title", "Story cover".

## Cover

`PUT /assets/{id}/story-cover` with `{assetId}` (`App\Api\Processor\SetStoryCoverProcessor`,
`EDIT` on the story, `READ` on the item) makes an item the cover:
`App\Service\Asset\StoryCoverManager` gives the story the source file of the item (the same
file, shared), and the story renditions are generated from it. The item must have a source
file.

The cover is only set explicitly: at creation, by this endpoint, or by the client after an
upload ("Mark as cover" in the carousel, "Use as story cover" in the upload dialog). A story
without cover has no source file; its thumbnail is its contact sheet, and the viewer shows its
first displayable item.

## Contact sheet

New workspaces get the rendition definition `StoryContactSheet`, applying to stories, used as
thumbnail and animated thumbnail (see [Renditions](./Renditions.mdx)). When a story has a
cover, its thumbnail comes from the cover and its animated thumbnail from the contact sheet
(`App\Service\Asset\StoryRenditionPreference`).

`App\Service\Asset\StoryContactSheetScheduler` rebuilds it (`BuildStoryContactSheet`
message, 3 seconds after the last change, deduplicated per story) when items are added,
removed, reordered, moved away or deleted.

## Items

| Method and path                           | Security                                   | Effect |
|-------------------------------------------|--------------------------------------------|--------|
| `GET /assets/{id}/story-assets`           | `READ` on the story                        | The items, in order, through the search: the rights, trash and quarantine rules apply. |
| `POST /assets/add-to-collection`          | `EDIT` on the story, `READ` on each asset  | `{ids, destination}`, the destination being the story asset; items already there are skipped. |
| `PUT /assets/{id}/position`               | `EDIT` on the story                        | `{destination, position}`: move an item (0-based, clamped). |
| `DELETE /assets/{id}/collections/{collectionId}` | `EDIT` on the story                 | Remove an item, the collection being the story collection. |
| `GET /assets/{id}/story-thumbnails`       | `READ` on the story                        | Thumbnail URLs of the readable items (hover carousel). |

An item must belong to the workspace of the story, and a story cannot be added to itself.
An asset uploaded **into** a story has the story collection as reference collection: it
cannot be removed from it, only moved or deleted.

## Rights and search

- The story collection has no ACE of its own: reading the story reads its items, editing the
  story edits its items list (`CollectionVoter`, see [access control](./access-control.md)).
  In Elasticsearch, the privacy and permissions of the story extend to its items.
- Built-in attributes: `@isStory`, `@story` (the stories holding the asset) and `@position`.
  Sorting on `@position` requires a search narrowed to a collection or a story.
- The items of a story are not listed when browsing the collections holding the story; the
  client searches them with `@story`.

## Trash

Trashing a story trashes its collection, so that the items whose reference collection it is
are trashed too; restoring the story restores them (see [asset lifecycle](./asset-lifecycle.md#trash)).

## Client

- **Viewer.** A story shows the carousel of its items ("… items", 30 per page,
  `features/assets/view/StoryCarousel.tsx`). Selecting an item opens it with
  `?story=<story id>` in the URL, so that a reload keeps the story context; the parameter is
  dropped when the story is not readable or the asset is not one of its items. Previous and
  next walk the cover then the items; at either end, "End of the story: press again to go to
  the next asset".
- **Carousel menu** (editors): "Mark as cover", "Move to start", "Move to end", "Remove from
  story", and drag and drop reordering.
- **Drag and drop** onto a story adds the assets by default (Ctrl moves them).
- **Pinned stories.** "Pin story" keeps a story in the "Pinned stories" panel of the sidebar,
  a drop target to add assets. Pins are stored in the browser (50 at most, the oldest dropped
  first).
- **Lists.** The thumbnail of a story scrolls through its items on hover
  (`features/assets/list/StoryThumb.tsx`). The asset side panel lists the "Stories" holding
  the asset.

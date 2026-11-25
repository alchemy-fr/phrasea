---
title: 'Entity lists'
---

# Entity lists

An entity list is a controlled vocabulary of a workspace: people, places, tags, products. The
attributes of type `entity` take their values from a list, so that a value is entered once,
translated, illustrated, and renamed or merged everywhere at once.

Who manages a list is described in [entity list permissions](./permissions.md#entity-list-permissions).

## Model

`App\Entity\Core\EntityList` (`/entity-lists`, ACL type `entity_list`) belongs to a workspace
and has a `name`, unique in the workspace, and options, all off by default:

| Option             | Client label                                        | Effect |
|--------------------|-----------------------------------------------------|--------|
| `allowNewValues`   | "Accept new values from attribute fields"           | Users editing assets may add a value missing from the list. |
| `approveNewValues` | "New values are approved automatically"             | Otherwise, the values added by users who cannot manage the list are pending. |
| `withTranslations` | "Translations"                                      | A translation per workspace language. |
| `withSynonyms`     | "Synonyms"                                          | Other terms finding the value in searches. |
| `withEmojis`       | "Emojis"                                            | An emoji next to the value. |
| `withColors`       | "Colors"                                            | A color next to the value. |
| `withImages`       | "Photos"                                            | A photo illustrating the value. |

An attribute definition of type `entity` names its list (`entityList`, "Entity list" in the
attribute form). Deleting a list deletes its values and the definitions bound to it.
Changing the type of a definition to or from `entity` migrates its values in a worker
(`AttributeMigrateToEntityList`, `AttributeMigrateFromEntityList`).

`App\Entity\Core\AttributeEntity` (`/attribute-entities`) is a value: `value`, `note` (tells
two values of the same name apart), `translations` (by locale), `synonyms` (by locale),
`emoji`, `color`, `externalId` (unique in the list), `position`, `status`, an image, and its
`usage` (how many attribute values reference it, on demand). The attributes store the id of
the value: an [AQL](./aql.md) condition on an entity attribute compares ids.

## Pending values

A value is **approved** (`0`), **pending** (`1`) or **rejected** (`2`).

- A reader of the list may propose a value when the list accepts new values; it is pending
  unless the list approves new values automatically, or the author may manage the values.
- Only the users who manage the values change the status; the author may edit or delete their
  value while it is not approved.
- A value that is not approved is not indexed on the assets nor suggested, and only its
  author, the users who manage the list and the asset editors of the workspace see it.
  Approving it reindexes the assets holding it.
- The list owner is notified of each pending value (`entity_list:pending_value`, see
  [notifications](./notifications.md)).

## Merging

`PUT /attribute-entities/{id}/merge` with `{ids}` (1 to 100 values of the same list) merges
these values into the value `{id}`: `EDIT` on the kept value, `DELETE` on the others. The
merged values are deleted, their names and translations become synonyms of the kept value,
and a worker (`AttributeEntityMerge`) rewrites the attributes and the index.

## Import, export, clear

They require the right to manage the values of the list.

- `POST /entity-lists/{id}/import` with `{format, data}`: format `csv` or `raw` (one value per
  line, existing ones skipped). The CSV columns are `value` (required), `id`, `external_id`,
  `emoji`, `color`, `status` and `translation_<locale>`; a row updates the value of its `id`
  or `external_id` or `value`, or creates it. The import is all or nothing: the first invalid
  line is reported (`Line N: …`) and nothing is saved.
- `POST /entity-lists/{id}/export` with `{format, locale?}`: `csv`, `json` or `liform` (for the
  Uploader), streamed as a file. Without locale, the CSV holds a `translation_<locale>` column
  per workspace language.
- `POST /entity-lists/{id}/clear`: deletes every value of the list, in a worker.

## Images

`POST /attribute-entities/{id}/image` (multipart, 20 MB at most: JPEG, PNG, WebP, GIF or SVG)
and `DELETE /attribute-entities/{id}/image` set and remove the photo of a value (`EDIT` on the
value). The output gives its `image` and `thumbnail` URLs. For the face recognition lists,
`app:face-recognition:photos` gives each person the photo of their best face.

## API

| Method and path                         | Security |
|-----------------------------------------|----------|
| `GET /entity-lists`                     | lists of the readable workspaces (`workspace`, `name`, `order[name]`, `order[createdAt]`) |
| `POST /entity-lists`                    | workspace editors |
| `GET`, `PATCH`, `DELETE /entity-lists/{id}` | `READ`, `EDIT`, `DELETE` |
| `GET /attribute-entities`               | `workspace`, `list`, `ids`, `value`, `status`, `note`, `usage`, `query` (search as you type), `order[…]` |
| `POST /attribute-entities`              | see pending values |
| `GET`, `PATCH`, `DELETE /attribute-entities/{id}` | `READ`, `EDIT`, `DELETE` |

`GET /entity-lists` and `GET /entity-lists/{id}` give the `capabilities` of the user on each
list (`edit`, `editValues`, `delete`, `editPermissions`). The Uploader reads the values with
an integration key. The [MCP server](./mcp.md) manages the lists.

## The "Tags" list

Tags are an ordinary entity list, named "Tags", bound to a multi-valued, faceted `tags`
attribute. The migration of the former tags creates one in every workspace that had tags,
keeping their ids (see `UPGRADE-5.md`). The [fixtures](./fixtures.md) and the Phraseanet indexer also create one.

## Client

- **Workspace management → Entities** (`/workspaces/{id}/manage/entities`): "New list", and,
  per list, its settings panel (name, then "New values", "Language" and "Appearance" groups,
  and the "Permissions" section for who may edit them), "Values" and "Explore".
- **Values** opens the spreadsheet grid of the list: inline editing, sort, column filters,
  search; columns Value, Note, Emoji, Color, Photo, one translation and one synonyms column
  per language (as the options allow), Status, Usage, Updated. Toolbar: "All", "Pending",
  "Approved", "Rejected", "New value", "Add row", and, on a selection, "Approve … values",
  "Reject … values", "Merge", delete; "Export…", "Import…", "Clear list". The row menu adds
  "Edit…", "Assets using this value" and the photo.
- **Explore** (`/entity-lists/{id}/explore`, also reached from the facets and the pinned
  filters): "Explore values", a card per value with a few assets holding it and "Open in
  search"; editors rename, merge ("Merge … into…") or delete a value from there.
- **Manage list** (`/entity-lists/{id}/manage`): the grid for the users given rights on the
  list, reached from the explore page ("Manage values") or the "Manage list" button of the
  attribute editor.
- In the attribute editor, an entity field offers to create a missing value when the list
  accepts new values.

## Administration and templates

The EasyAdmin pages **Entity Lists** and **Attribute Entities** list them, and
`app:diagnose:attribute-entity-links` reports broken links between attribute values and
entities. Workspace templates carry the lists (`App\Service\Workspace\Template\Section\EntityListSection`).

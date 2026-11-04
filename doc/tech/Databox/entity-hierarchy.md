# Entity hierarchies

The values of an entity list (`AttributeEntity`) can be organized in a forest: every entity
without parent is at the top level (depth 0), and any entity can take children. Entity
attributes are then bound to levels of that forest: Continent, Country, Region, City,
District and Street all use the same "Geography" list, each one at its own depth, while
a "Place" attribute may accept any level from Country to City.
Selecting a deep value fills the levels above it (City = Paris fills Region, Country
and Continent), while a general level can still be entered alone (Country without City).

Nothing changes for lists without a hierarchy, nor for the attributes not bound to one.

## Model

| Object                | Field                     | Role                                                                              |
|-----------------------|---------------------------|-----------------------------------------------------------------------------------|
| `AttributeEntity`     | `parent`                  | Single parent; null at the top level.                                             |
|                       | `hierarchyDepth`          | Absolute depth, maintained by the API; 0 = no parent.                             |
|                       | `placeholder`             | Fills an empty level; never proposed nor written in an attribute.                 |
|                       | `position`                | Order among the siblings.                                                         |
| `AttributeDefinition` | `entityHierarchyDepthMin` | Lowest level whose entities the attribute accepts (included). Null: no bound.     |
|                       | `entityHierarchyDepthMax` | Deepest level whose entities the attribute accepts (included). Null: no bound.    |
|                       |                           | Both null: any entity of the list (not bound to the hierarchy).                   |
|                       | `autoFillParents`         | A value fills the attributes bound to the ancestor levels.                        |

**Same concept, same depth.** All the entities of a concept (every street) are at the
same absolute depth, whatever their branch. When a level is missing in a branch (no
district in Tours), a placeholder entity fills it:

```
Monde (0) ─ Europe (1) ─ France (2) ─ Île-de-France (3) ─ Paris (4) ─ 16e (5) ─ Rue Victor Hugo (6)
                                   └─ Indre-et-Loire (3) ─ Tours (4) ─ [empty level] (5) ─ Rue Victor Hugo (6)
```

Integrity (validated by the API on every creation and move): the parent belongs to the
same list, no cycle, an entity having children cannot be removed nor merged. An entity is
placed at most once (it has a single `parent` column).

A flat list is a forest of single entities at depth 0: lists can be organized progressively.

## API

- `POST /attribute-entities` `{"list": …, "value": …, "parent": "/attribute-entities/{id}", "placeholder": false}`.
- `PATCH /attribute-entities/{id}` `{"parent": …, "position": n}`: moves a branch (`"parent": null`:
  to the top level); the depth of the whole subtree is shifted (`EntityHierarchyManager`).
- `GET /attribute-entities/{id}/hierarchy-impact`: entities of the branch, attribute values referencing
  them (and how many were entered by users), bound definitions — shown before a move.
- `GET /attribute-entities` parameters: `parent` (children, by position), `depth` (`0`: the top
  level), `branch` (descendants), `placeholder`, and `definition`, which applies the depth range of a
  definition and excludes the placeholders. Each entity has `parentId`, `hierarchyDepth`,
  `placeholder`, `childCount` and `path` (the ancestors, placeholders left out), which tells
  homonyms apart: "Rue Victor Hugo — 16e / Paris / Île-de-France".
- `POST /entity-lists/{id}/hierarchy-import` `{"data": "<csv>", "dryRun": true}`: places existing
  entities from a CSV `entity_external_id,parent_external_id,position` (empty parent: the top level).
  Labels, translations and synonyms are never changed. Answers `{applied, rejected: [{line, entity, reason}]}`;
  unknown entities, duplicates and cycles are rejected.
- `POST /entity-lists/{id}/hierarchy-export`: the same CSV, depth first, from every entity of the top level.

## Upward filling

`HierarchyAttributeFiller`. For each asset, the values of the definitions having `autoFillParents`
(the *sources*, any origin but `hierarchy`) walk up the tree; every other definition of the list is
filled with the closest ancestor within its depth range (Paris fills a Place bound to levels 1 to 3 with
Île-de-France). A value of another origin holding a farther ancestor of that range (Place = France) is
consistent: it is kept, and nothing is filled. Multi-valued sources fill multi-valued parents, deduplicated by entity
(Paris + Rome give France + Italie). Filled values get the `hierarchy` origin.

The `hierarchy` values follow their sources: they are added, updated and removed when the sources
change. A value of another origin is never overwritten silently. A single-valued attribute holding
another value than the expected ancestor is a **conflict**:

| `hierarchyConflict` | Behavior                                                                                           |
|---------------------|----------------------------------------------------------------------------------------------------|
| `reject` (default)  | 422, a violation per attribute (code `hierarchy_conflict`, path `hierarchy.<definition ID>`).      |
| `replace`           | The values written by the request win: Paris selected while Country holds Belgique replaces it;    |
|                     | Country changed from France to Italie removes Region = Île-de-France and City = Paris.             |

A single-valued parent cannot hold several expected parents (Paris and Rome): always rejected.

The strategy is a field of the attribute batch updates (`POST /assets/{id}/attributes`,
`POST /attributes/batch-update`), which fill the hierarchy synchronously, in the same transaction.
The client asks for confirmation on a conflict and sends the update again with `replace`.

The other writes (single attribute API, upload form, initial values, workflows…) are caught by
`HierarchyAttributeListener`, which recomputes the asset asynchronously (`FillAssetsHierarchy`):
missing parents are filled, `hierarchy` values follow, user values are kept and the remaining
conflicts are logged.

## Provenance

The origin of an attribute value (`Attribute::$origin`) tells the filled values apart:

| Origin                     | Meaning                     | On a recompute                      |
|----------------------------|-----------------------------|-------------------------------------|
| `human`                    | Entered by a user           | Kept, conflicts reported            |
| `hierarchy`                | Filled from a deeper value  | Updated or removed                  |
| `machine`, `initial`       | Integrations, imports       | Kept, conflicts reported            |

A filled value edited by a user takes the origin of the edit (`human`).

## Hierarchy changes and indexing

Moving an entity (or changing the binding of a definition) dispatches `EntityHierarchyChanged`: the
moved entities and their subtree are indexed again (parent, depth and ancestors of the
`attribute_entity` index), and the assets holding one of them are recomputed in chunks
(`FillAssetsHierarchy`) and indexed again. Only these assets are touched; a definition change
recomputes the assets holding a value of the list.

Search and facets need nothing specific: filled values are real attributes.

**Upgrade:** the `attribute_entity` index gets new fields (`parentId`, `ancestorIds`, `depth`,
`placeholder`). Populate it again once deployed (`bin/console fos:elastica:populate --index=attribute_entity`),
otherwise the search-as-you-type of hierarchical attributes finds nothing.

**Upgrade (forest):** `Version20261103100000` drops `entity_list.root_entity_id` and gives every
entity without parent the depth 0 (the former unclassified entities join the top level, the former
roots stay at 0). Populate `attribute_entity` and `asset` again: the depths and the indexed branches
change (the former roots now appear in the paths and the branches).

**Upgrade (depth range):** `Version20261104100000` turns `entity_hierarchy_depth` into
`entity_hierarchy_depth_min` = `entity_hierarchy_depth_max` (same behavior) and drops
`entity_hierarchy_branch_id`: the attributes restricted to a branch accept every entity of their levels.
The workspace templates exported with `entityHierarchyDepth` are still imported.

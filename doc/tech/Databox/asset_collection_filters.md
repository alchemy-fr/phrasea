---
title: 'Filtering assets by collection'
---

# Filtering assets by collection

Two distinct public contracts are available on `GET /assets`, depending on whether
the collection hierarchy must be traversed or not.

Each asset is indexed with the absolute path (`/<rootCollectionId>/…/<collectionId>`)
of **every collection it is directly attached to**. The `collectionPaths` field is
analyzed with a `path_hierarchy` tokenizer, so querying a parent path also matches
its descendants; its `collectionPaths.raw` keyword sub field keeps the un-analyzed
paths and therefore only matches exact — i.e. direct — memberships.

## Recursive: collection and its descendants

| Form                    | Example                                  |
|-------------------------|------------------------------------------|
| Query parameter         | `GET /assets?parents[]=<collectionId>`   |
| Query parameter (single)| `GET /assets?parent=<collectionId>`      |
| AQL built-in attribute  | `@collection = "<collectionId>"`         |

Matches assets belonging to the given collection **or to any of its sub collections**.

## Direct membership only

| Form                   | Example                                              |
|------------------------|------------------------------------------------------|
| Query parameter        | `GET /assets?directCollections[]=<id1>&directCollections[]=<id2>` |
| AQL built-in attribute | `@directCollection IN ("<id1>", "<id2>")`            |

Both accept a list of collection IDs and apply the following semantics:

```
exists(direct membership of the asset whose collection id belongs to the requested list)
```

Sub collections are never traversed: an asset attached only to `/A/B` is **not**
returned when filtering on `A`.

An asset attached to several of the requested collections is returned once.

The AQL attribute (key `@directCollection`, exposed by `GET /built-in-attributes`)
supports the `=`, `!=`, `IN`, `NOT IN`, `EXISTS` and `IS MISSING` operators, so it
can be freely combined with any other condition:

```
@directCollection IN ("<id1>", "<id2>") AND @createdAt >= "2026-01-01"
```

The `directCollections[]` query parameter is strictly equivalent to
`conditions[]=@directCollection IN (…)` and, like `parents[]`, answers `404` when
none of the requested collections exists. An unusable value passed to
`@directCollection` (not a UUID, or an unknown collection) answers `400`.

## Notes

- Both forms are applied on top of the regular ACL filtering: an asset the caller
  cannot read is never returned, whatever the requested collections.
- Assets attached to a **story** collection are indexed under the collections of the
  story asset itself, not under the story collection. Consequently, neither
  `@collection` nor `@directCollection` matches a story collection ID; use the
  `@story` attribute for that.

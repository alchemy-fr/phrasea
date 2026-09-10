# Search suggestions

`GET /assets/suggest?query=par` feeds the search bar autocomplete. It returns, in this order:

1. distinct attribute values matching the query, most frequent first
   (`t` is the attribute definition ID, `tName` its display name);
2. collection and asset names (`t` is `collection` or `asset`, `tId` the item ID).

## Attribute values

Every document of the `asset` index carries a `suggestions` nested field, built by
`AssetPostTransformListener` from the asset attributes:

```json
"suggestions": [
  {"definitionId": "<uuid>", "value": "Paris"},
  {"definitionId": "<uuid>", "entityId": "<uuid>", "value": "France"}
]
```

There is one entry per distinct (definition, value) pair of the asset, for the attribute types
supporting suggestions (text, keyword, entity, IP) and values up to 300 characters.
Entity attributes are stored with their label; the attribute entity handlers keep it in sync
when an entity is renamed, merged or deleted.

`SuggestionSearch` runs, on the asset index, a `nested` aggregation with a `filter` and a
`multi_terms` (`definitionId` + `value`) sub-aggregation:

- **permissions**: the query carries the same filters as the asset search (ACL, tag filter rules,
  not deleted, accepted status), and only the definitions flagged `suggest` that the user is allowed
  to read are aggregated;
- **deduplication**: one bucket per (definition, value), whose `doc_count` is the number of visible
  assets holding the value;
- the `filter` sub-aggregation, inside the nested one, keeps only the nested documents matching the
  query, so a multi-valued attribute does not leak its other values.

The highlight of each value comes from a `top_hits` sub-aggregation.

## Upgrading from the `attribute` index

Attribute values used to be indexed one by one in a dedicated `attribute` index, with the asset
permissions copied on every document. That index does not exist anymore:

1. re-populate the `asset` index so that the documents get their `suggestions` field:

   ```bash
   bin/console fos:elastica:populate --index=asset
   ```

2. delete the orphan `attribute` indices (`<ELASTICSEARCH_INDEX_PREFIX>attribute_<env>*`), for instance:

   ```bash
   curl -X DELETE "${ELASTICSEARCH_URL}/${ELASTICSEARCH_INDEX_PREFIX}attribute_prod*"
   ```

# Search suggestions

`GET /assets/suggest?query=par` feeds the search bar autocomplete. It returns, in this order:

1. distinct attribute values matching the query, most frequent first
   (`t` is the attribute definition ID, `tName` its display name, `locale` the locale of the
   values when the attribute is localized);
2. collection and asset names (`t` is `collection` or `asset`, `tId` the item ID).

The locale of the user comes from the request headers (`X-Data-Locale`, then `Accept-Language`),
resolved per workspace like the facets do (`LocaleContext::getBestWorkspaceLocale()`).

## Attribute values

Every document of the `asset` index carries a `suggestions` nested field, built by
`AssetPostTransformListener` from the asset attributes:

```json
"suggestions": [
  {"definitionId": "<city>", "locale": "fr", "value": "Paris"},
  {"definitionId": "<city>", "locale": "_", "value": "Parc"},
  {"definitionId": "<country>", "entityId": "<e1>", "locale": "_", "value": "Germany"},
  {"definitionId": "<country>", "entityId": "<e1>", "locale": "fr", "value": "Allemagne"},
  {"definitionId": "<country>", "entityId": "<e1>", "locale": "en", "value": "Germany"}
]
```

There is one entry per distinct (definition, locale, value) of the asset, for the attribute types
supporting suggestions (text, keyword, entity, IP) and values up to 300 characters:

- translatable text and keyword values keep the workspace locale of their attribute, `_` when
  untranslated; non-translatable definitions are always stored under `_`;
- entities get one label per suggestion locale of the workspace (enabled locales, fallback locales
  and `_`): their translation for the locale, falling back to the base label. The attribute entity
  handlers keep these labels in sync when an entity is renamed, translated, merged or deleted.

`SuggestionSearch` runs, on the asset index, a `nested` aggregation with a `filter` and a
`multi_terms` (`definitionId` + `value`) sub-aggregation:

- **permissions**: the query carries the same filters as the asset search (ACL, tag filter rules,
  not deleted, accepted status), and only the definitions flagged `suggest` that the user is allowed
  to read are aggregated;
- **locale**: per definition, the aggregated nested documents are restricted to the user's best
  workspace locale plus `_` for translatable text and keyword attributes, to the best workspace
  locale only for entities (hence exactly one label per entity), and to `_` for the other
  definitions. Values in other locales are never suggested;
- **deduplication**: one bucket per (definition, value), whose `doc_count` is the number of visible
  assets holding the value; the `fr` and `_` copies of the same value merge into one suggestion;
- the `filter` sub-aggregation, inside the nested one, keeps only the nested documents matching the
  query, so a multi-valued attribute does not leak its other values.

The highlight of each value comes from a `top_hits` sub-aggregation.

## Caveats

- Changing the `enabledLocales` or `localeFallbacks` of a workspace requires re-populating its assets
  (as for the `attrs` field): the entity labels are indexed for the locales known at indexing time.
- An asset holding the same value both translated and untranslated counts twice in the ordering of
  that value; the count is never displayed.
- Elasticsearch caps the nested documents of an asset (`index.mapping.nested_objects.limit`,
  10 000 by default): each entity value costs one entry per workspace locale.

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

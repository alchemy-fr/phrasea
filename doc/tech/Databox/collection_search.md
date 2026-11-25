---
title: 'Collection search'
---

# Collection search

`GET /collections?query=…` searches the collections in Elasticsearch
(`App\Elasticsearch\CollectionSearch`). The query is split by `QueryStringParser`:

- `in:all` includes the collections in the trash, `in:trash` only returns them;
- every quoted part (`"Lot 2"`) must match;
- the rest of the text must match as a whole.

Each part is a `multi_match` query of type `bool_prefix` with the `and` operator on the `name`
and `translatedNames` fields (the name and every translation of the name, in all the locales):
every word must be present, the last one being a prefix (search-as-you-type). A query also
makes the search deep by default (`deep`): it matches the collections at every level (below the
`parents` when given), not only the roots or the direct children.

The highlight comes from a `multi_match` on `name.suggest` and `translatedNames.suggest`, the
edge n-gram subfields of these fields, so that only the typed part of the words is
highlighted (`[hl]…[/hl]`). `CollectionOutputMapper::getNameHighlight()` returns the fragment of
the displayed name (`nameHighlight`).

## Collection order

Collections are listed in the same order whatever the path that lists them:
roots of an administrator (Elasticsearch), roots of a regular user, of a group
member or of an anonymous user (the `collection_access` table, Doctrine),
children (`parent`), children embedded in their parent (`coll:ic`), and the
client tree once a collection is created, renamed or moved without reloading.

The order is:

1. the **displayed name** (`displayName`: the translation of the name picked
   for the preferred locales of the user in the workspace, the name otherwise),
   in natural order, case and accent insensitive ("Lot 2" before "Lot 10",
   "été" next to "Ete");
2. the creation date (to the second, the precision of the API output and of
   the index);
3. the ID.

An `order` parameter given to the search wins over this default order.

### Sort key

`App\Util\NaturalSortKey::fromString()` (PHP) and `naturalSortKey()`
(`databox/client/src/lib/utils/naturalSortKey.ts`) compute the same key, which
is compared by code points (`strcmp()`, keyword sort in Elasticsearch,
`compareCodePoints()` in the client, as `<` orders the characters out of the
BMP by UTF-16 code units):

- NFD decomposition, lower case, combining marks (`\p{Mn}`) removed;
- every run of spaces (`\p{Z}`, tabulation, line breaks) becomes one space,
  trimmed;
- every run of ASCII digits loses its leading zeros and is prefixed by its
  length on two digits: `007` → `017`, `Lot 10` → `lot 0210`;
- truncated to 1000 code points (keyword terms are limited to 32766 bytes).

Limits: ligatures and `ß` are not folded, non ASCII digits are plain
characters, `Lot 02` and `Lot 2` get the same key (the creation date and the
ID break the tie).

### Index

The collection index holds:

- `sortName`: the key of the name;
- `sortNames`: the key of each translation of the name,
  `<position on 4 digits>\u001F<normalized locale>\u001F<key>`. The values of a
  multi-valued keyword come back sorted and deduplicated: the position restores
  the order of the translations, on which the choice of the displayed one
  depends;
- `id`, as sorting on `_id` is disabled in Elasticsearch.

`CollectionSearch::SORT_SCRIPT` is a painless port of
`LocaleUtil::getBestLocale()`: for the preferred locales of the workspace of
each document (`params.prefs`, computed by `LocaleContext::getPreferredLocales()`
for the workspaces of the parents, of the `workspaces` filter, or all of them),
it returns the key of the translation `getTranslatedField()` would display, or
`sortName`.

The roots of the regular users are not paginated: `CollectionSorter` sorts
them in PHP with the same key, from the displayed name.

### Deployment

The sort fields are part of the mapping of the collection index: after a change of
this mapping, repopulate the index (`bin/migrate.sh` only runs the Doctrine
migrations):

```bash
bin/console fos:elastica:populate --index=collection
```

The documents indexed without `sortNames` are sorted by their name.

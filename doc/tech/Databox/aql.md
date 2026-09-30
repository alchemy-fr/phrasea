# AQL (Asset Query Language)

AQL expresses the search conditions of databox: the `conditions[]` parameter of
`GET /assets`, saved searches, attribute filter rules and the condition builder of
both clients all use it.

```
title STARTS WITH "Report" AND (@tag HAS ALL OF ("a", "b") OR description IS EMPTY)
```

A condition is `field operator [value]`. Conditions are combined with `AND`, `OR`,
`NOT` and parentheses. A field is an attribute slug (`title`) or a built-in attribute
(`@tag`, `@collection`, `@createdAt`…). Values are quoted strings, numbers, `true`,
`false`, `null`, other fields, function calls (`NOW()`, `DATE_SUB(NOW(), "P7D")`…) or
arithmetic expressions.

Operator keywords are uppercase.

## Operators

The builders write the first keyword of each row. The other keywords are accepted
aliases, kept so that existing saved searches, filter rules and URLs keep working.

| Keyword (aliases)                                    | Field types              | Elasticsearch query                          |
|------------------------------------------------------|--------------------------|----------------------------------------------|
| `IS` (`=`)                                           | all                      | `term` on the exact (raw) value              |
| `IS NOT` (`!=`)                                      | all                      | negated `term`                               |
| `<` `<=` `>` `>=`                                    | number, date, date-time  | `range`                                      |
| `BETWEEN a AND b` / `NOT BETWEEN a AND b`            | number, date, date-time  | `range` (bounds included)                    |
| `IS ANY OF (…)` (`IN`, `HAS ANY OF`)                 | all                      | `terms`                                      |
| `IS NONE OF (…)` (`NOT IN`, `HAS NONE OF`)           | all                      | negated `terms`                              |
| `HAS ALL OF (…)`                                     | all (multi-valued)       | one `term` per value, all required           |
| `IS EMPTY` (`IS MISSING`)                            | all                      | negated `exists`                             |
| `IS NOT EMPTY` (`EXISTS`)                            | all                      | `exists`                                     |
| `CONTAINS` / `DOES NOT CONTAIN`                      | text, keyword            | `wildcard` `*value*` on the raw value        |
| `STARTS WITH` / `DOES NOT START WITH`                | text, keyword            | `prefix` on the raw value                    |
| `ENDS WITH` / `DOES NOT END WITH`                    | text, keyword            | `wildcard` `*value` on the raw value         |
| `MATCHES` / `DOES NOT MATCH`                         | text, keyword            | full-text `match`, all words required        |
| `WITHIN CIRCLE (lat, lng, radius)`                   | geo point                | `geo_distance`                               |
| `WITHIN RECTANGLE (tlLat, tlLng, brLat, brLng)`      | geo point                | `geo_bounding_box`                           |

Notes:

- `CONTAINS`, `STARTS WITH` and `ENDS WITH` are case-insensitive. Add
  `CASE SENSITIVE` after the value to change that:
  `title ENDS WITH ".JPG" CASE SENSITIVE`. They work on the raw value, which is only
  indexed up to 256 characters.
- `MATCHES` is analyzed with the language of the attribute (case, accents, stemming).
  Every word of the value must be present, in any order: `description MATCHES "red car"`
  matches "The car is red". It does not support `CASE SENSITIVE`.
- On dates, `IS` / `IS NOT` match a prefix of the date: `@createdAt IS "2024-03"`
  matches the whole month.
- After `IS`, the words `EMPTY`, `MISSING`, `ANY`, `NONE` and `NOT` are keywords and
  cannot be used as field names.

## Builder labels

The condition builders show the operators with an Airtable-like vocabulary, which
depends on the type of the field:

| Operator      | Text / keyword / ID | Number | Date              |
|---------------|---------------------|--------|-------------------|
| `IS`          | Is                  | =      | Is                |
| `IS NOT`      | Is not              | ≠      | Is not            |
| `<` / `<=`    | –                   | < / ≤  | Is before / Is on or before |
| `>` / `>=`    | –                   | > / ≥  | Is after / Is on or after   |
| `BETWEEN`     | –                   | Is between | Is within     |
| `MATCHES`     | Contains words      | –      | –                 |
| `IS ANY OF`   | Is any of           | Is any of | Is any of      |
| `HAS ALL OF`  | Has all of (multi-valued fields only) | | |
| `IS EMPTY`    | Is empty            | Is empty | Is empty        |

## Implementations

AQL is parsed in three places, which must stay in sync:

- API: `databox/api/src/Elasticsearch/AQL/AQLGrammar.peg`, compiled into
  `AQLGrammar.php` (committed). Running `tests/unit/AQL/AQLParserTest.php`
  regenerates it. `AQLToESQuery` turns the AST into an Elasticsearch query.
- Legacy client: `databox/client/src/components/Media/Search/AQL/grammar.ne`
  (nearley), compiled with `pnpm --filter databox-client compile-grammar`.
- Next.js client: `databox/client-nextjs/src/features/search/aql/parser.ts`.

See also [Asset collection filters](./asset_collection_filters.md).

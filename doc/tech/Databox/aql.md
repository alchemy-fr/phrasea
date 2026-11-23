# AQL (Asset Query Language)

AQL expresses the search conditions of databox: the `conditions[]` parameter of
`GET /assets`, saved searches, attribute filter rules and the condition builder of
both clients all use it.

```
title STARTS WITH "Report" AND (keywords HAS ALL OF ("a", "b") OR description IS EMPTY)
```

A condition is `field operator [value]`. Conditions are combined with `AND`, `OR`,
`NOT` and parentheses. A field is an attribute slug (`title`) or a built-in attribute
(`@collection`, `@createdAt`…). Values are quoted strings, numbers (`-74.006`), `true`,
`false`, `null`, other fields, function calls (`NOW()`, `DATE_SUB(NOW(), "P7D")`…) or
arithmetic expressions.

Operator keywords are uppercase.

## Operators

The builders write the first keyword of each row. The other keywords are accepted
aliases, kept so that existing saved searches, filter rules and URLs keep working.

| Keyword (aliases)                               | Field types             | Elasticsearch query                   |
| ----------------------------------------------- | ----------------------- | ------------------------------------- |
| `IS` (`=`)                                      | all                     | `term` on the exact (raw) value       |
| `IS NOT` (`!=`)                                 | all                     | negated `term`                        |
| `<` `<=` `>` `>=`                               | number, date, date-time | `range`                               |
| `BETWEEN a AND b` / `NOT BETWEEN a AND b`       | number, date, date-time | `range` (bounds included)             |
| `IS ANY OF (…)` (`IN`, `HAS ANY OF`)            | all                     | `terms`                               |
| `IS NONE OF (…)` (`NOT IN`, `HAS NONE OF`)      | all                     | negated `terms`                       |
| `HAS ALL OF (…)`                                | all (multi-valued)      | one `term` per value, all required    |
| `IS EMPTY` (`IS MISSING`)                       | all                     | negated `exists`                      |
| `IS NOT EMPTY` (`EXISTS`)                       | all                     | `exists`                              |
| `CONTAINS` / `DOES NOT CONTAIN`                 | text, keyword           | `wildcard` `*value*` on the raw value |
| `STARTS WITH` / `DOES NOT START WITH`           | text, keyword           | `prefix` on the raw value             |
| `ENDS WITH` / `DOES NOT END WITH`               | text, keyword           | `wildcard` `*value` on the raw value  |
| `MATCHES` / `DOES NOT MATCH`                    | text, keyword           | full-text `match`, all words required |
| `WITHIN CIRCLE (lat, lng, radius)`              | geo point               | `geo_distance`                        |
| `WITHIN RECTANGLE (tlLat, tlLng, brLat, brLng)` | geo point               | `geo_bounding_box`                    |

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
- On an attribute of type `entity` (an entity list), the values are the entity IDs:
  `tags IS "<entity ID>"`, `tags IN ("<ID>", "<ID>")`, `tags HAS ALL OF (…)`,
  `tags IS NOT "<entity ID>"`.
- After `IS`, the words `EMPTY`, `MISSING`, `ANY`, `NONE` and `NOT` are keywords and
  cannot be used as field names.
- The radius of `WITHIN CIRCLE` is a number of meters or an Elasticsearch distance
  string: `location WITHIN CIRCLE (48.85, 2.35, "1500m")`. `WITHIN RECTANGLE` takes the
  top-left then the bottom-right corner.

## Comparing fields (script conditions)

When the value of a condition depends on the asset (another field, or an expression or
function reading one), the condition becomes a Painless `script` query:

```
n >= m * 2
label = CONCAT(SUBSTRING(code, 0, 3), "!")
```

- The left operand is always a field. The operators are `=`, `!=`, `<`, `<=`, `>` and `>=`.
- Arithmetic: `+`, `-`, `*`, `/`. A division gives a decimal number, as between constants:
  `n / m` is `2.5` for `10 / 4`.
- `SUBSTRING(s, start[, length])` follows PHP `substr`: `start` is 0-based, `length` is a
  length (not an end position), a negative `start` or `length` counts from the end, and out of
  range bounds give a shorter or empty string rather than an error.
- `CONCAT(a, b)` joins the text of both values, numbers included.
- An asset missing a value read by the condition never matches it, whatever the operator,
  `!=` included: `n != m` does not retain the assets without `m` (unlike `n != 3`, which retains
  the assets without `n`). `NOT (n = m)` does retain them, as any `NOT`.
- A text field is compared on its raw value, indexed up to its first 256 characters: a longer
  value counts as missing. Only the first value of a multi-valued field is read.
- Dates are not supported: neither comparing date fields (`@createdAt > @editedAt`) nor
  `NOW()`, `DATE_ADD()` or `DATE_SUB()` in a condition that reads another field.

The constant values are passed as script parameters, so that conditions of the same shape share
a single script compilation.

## Builder labels

The condition builders show the operators with an Airtable-like vocabulary, which
depends on the type of the field:

| Operator     | Text / keyword / ID                   | Number     | Date                        |
| ------------ | ------------------------------------- | ---------- | --------------------------- |
| `IS`         | Is                                    | =          | Is                          |
| `IS NOT`     | Is not                                | ≠          | Is not                      |
| `<` / `<=`   | –                                     | < / ≤      | Is before / Is on or before |
| `>` / `>=`   | –                                     | > / ≥      | Is after / Is on or after   |
| `BETWEEN`    | –                                     | Is between | Is within                   |
| `MATCHES`    | Contains words                        | –          | –                           |
| `IS ANY OF`  | Is any of                             | Is any of  | Is any of                   |
| `HAS ALL OF` | Has all of (multi-valued fields only) |            |                             |
| `IS EMPTY`   | Is empty                              | Is empty   | Is empty                    |

## Implementations

AQL is parsed in two places, which must stay in sync:

- API: `databox/api/src/Elasticsearch/AQL/AQLGrammar.peg`, compiled into
  `AQLGrammar.php` (committed). Running `tests/unit/AQL/AQLParserTest.php`
  regenerates it. `AQLToESQuery` turns the AST into an Elasticsearch query.
- Client: `databox/client/src/features/search/aql/parser.ts`.

`AQLToESQuery` and `AQLDocumentEvaluator` share `AQLConditionResolver`: the fields a slug designates
(clusters, built-in attributes disabled for the user except in trusted conditions), the values, the
functions and the effective operator and field of each criteria (date `=` turned into a prefix,
boolean `= null` into `IS EMPTY`, length limit of the wildcard values…). A change to the semantics of
an operator goes there, or in both.

## Attribute filter rules

An attribute filter rule (`AttributeFilterRule`) restricts, for its targets (users, groups, or
everyone when it has none, anonymous users included), the assets of a workspace to those matching
its AQL condition. Every rule of the workspace targeting the user applies, whatever their role
(workspace owner included); only a token carrying an `asset:` scope bypasses them. Exception: an
administrator (`ROLE_ADMIN`) reads any asset by ID, since `AdminVoter` grants every attribute, while
the search still applies the rules to them. The conditions are
trusted (`AQLToESQuery::OPT_TRUSTED_CONDITIONS`): the built-in attributes disabled for the user and
the collections they cannot read stay usable in them. A condition that cannot be applied hides the
whole workspace (fail-closed).

They are enforced twice, with the same semantics:

- in the asset searches, inside the Elasticsearch query (`AssetSearch::createBaseFilterQueries()`);
- on every asset read by ID (`AssetVoter::READ`, through `AssetAttributeFilterChecker`): the
  condition is evaluated in PHP by `AQLDocumentEvaluator` on the Elasticsearch document of the asset
  built in memory (the one the indexing would send), so an asset not indexed yet is evaluated like
  the others. The checker first builds the Elasticsearch query of the condition: a condition the
  search would refuse denies the asset. The rules and the decisions are cached for the request,
  and the assets a search without `locale` returns are declared readable to the checker, its query
  having applied the same rules.

The PHP evaluation reproduces the query exactly or denies: whatever it cannot reproduce throws
`UnsupportedEvaluationException`, and the asset is denied (an error is logged). It is limited to:

- the analyzers `text` (attribute text fields in no locale) and `standard` (built-in text fields,
  e.g. `@filename`), on Latin text (Basic Latin, Latin-1 and Latin Extended-A letters and the usual
  punctuation; `DocumentTextAnalyzer`). The language analyzers of the translated text fields
  (stemming, stop words) are not reproduced: `MATCHES` denies an asset having a value in such a field.
  `=`, `IS ANY OF`, `CONTAINS`… on text fields read their raw value and are not affected;
- the case insensitive `CONTAINS`, `STARTS WITH` and `ENDS WITH`, where Elasticsearch ignores the
  case of the ASCII letters only (`"CHÂ"` does not match `Château`): a value holding a character
  that a Unicode case folding would match with an ASCII letter (`ſ`, `ı`, `İ`, the Kelvin sign…) is
  denied;
- the scripted criteria (comparing fields) on keyword, text (raw value), number and boolean fields,
  with `CONCAT` and `SUBSTRING` on texts and integers (`SUBSTRING` on ASCII text only), without
  integer overflow nor division by zero;
- the built-in attributes stored as document fields, `@deleted` and `@assetStatus`; `@similar`,
  `@score` and `@position` are denied.

It reads the document, not the index mapping: a field left out of the mapping by its field limit
(`index.mapping.total_fields`), or a script reading a field absent from the mapping (an error in
Elasticsearch, which fails the whole search), are evaluated on the values of the document.

See also [Asset collection filters](./asset_collection_filters.md).

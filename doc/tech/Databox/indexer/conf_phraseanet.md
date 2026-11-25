---
title: 'Phraseanet location'
---

# Databox Indexer: Phraseanet configuration

A `phraseanet` location imports the records and stories of one or more Phraseanet databoxes
into Databox workspaces, through the Phraseanet API (`/api/v1`, `/api/v3/search`).

## Example configuration

Below is an example configuration for a Phraseanet location. Each field is explained after the
code block.

```json
{
  "locations": [
    {
        "name": "phraseanet-full-example",
        "type": "phraseanet",
        "options": {
            "url": "https://phraseanet.phrasea.local/",
            "token": "-------mytoken-------",
            "verifySSL": false,
            "importFiles": false,
            "searchOrder": "%env(PHRASEANET_SEARCH_ORDER)%",
            "idempotencePrefixes": {
                "asset": "idmp_asset",
                "collection": "idmp_collection",
                "attributeDefinition": "idmp_attributeDefinition",
                "renditionDefinition": "idmp_renditionDefinition"
            },
            "databoxMapping": [
                {
                    "databox": "db_databox1",
                    "collections": "",
                    "workspaceSlug": "phnet",
                    "searchQuery": "",
                    "importStories": true,
                    "recordsCollectionPath": "/Records/{{ collection.name | escapePath }}/{{ record.getMetadata('Country', '_').value | escapePath }}/{{ record.getMetadata('City', '_').value | escapePath }}",
                    "storiesCollectionPath": "/Stories/{{ collection.name | escapePath }}/{{ record.getMetadata('Country', '_').value | escapePath }}/{{ record.getMetadata('City', '_').value | escapePath }}",
                    "copyTo": [
                        "{% for s in record.getMetadata('Subject', 'no_subject').values %}/classification/{{ record.getMetadata('Creator', 'no_creator').value | escapePath }}/{{ s | escapePath }}\n{% endfor %}"
                    ],
                    "fieldMap": {
                        "Title": {
                            "values": [
                                {
                                    "locale": "fr",
                                    "type": "metadata",
                                    "value": "Titre"
                                },
                                {
                                    "locale": "en",
                                    "type": "metadata",
                                    "value": "Title"
                                }
                            ]
                        },
                        "Subject": {
                            "type": "text",
                            "multivalue": true,
                            "values": [
                                {
                                    "type": "template",
                                    "value": "{% for v in record.getMetadata('Subject').values %}{{v}}\n{% endfor %}"
                                }
                            ]
                        },
                        "Copyright": {
                            "type": "text",
                            "values": [
                                {
                                    "type": "template",
                                    "value": "(c){{record.getMetadata('ArchiveDate', '').value | date('Y')}} {{record.getMetadata('Creator', '').value}}"
                                }
                            ]
                        },
                        "Filename": {
                            "values": [
                                {
                                    "type": "metadata",
                                    "value": "Filename"
                                }
                            ]
                        }
                    },
                    "sourceFile": "document",
                    "renditions": {
                        "main": {
                            "useAsMain": true,
                            "buildMode": 1,
                            "policy": "public"
                        }
                    }
                }
            ]
        }
    }
  ]
}
```

## Location options

| Option | Description |
| --- | --- |
| `url` | Base URL of the Phraseanet instance (`PHRASEANET_URL`). |
| `token` | OAuth token of a Phraseanet application, sent as `Authorization: OAuth <token>` (`PHRASEANET_APP_OAUTH_TOKEN`). |
| `verifySSL` | Verify the TLS certificate of Phraseanet (default `true`, `PHRASEANET_VERIFY_SSL`). |
| `importFiles` | `true`: Databox copies the files (source and renditions) into its own storage. `false` (default): the files stay Phraseanet permalinks, stored as URLs (`INDEXER_IMPORT_FILES`). |
| `searchOrder` | Order of the search: `record_id,asc` (default) or `record_id,desc`; any other value is refused (`INDEXER_PHRASEANET_SEARCH_ORDER`). It is also the direction in which an interrupted indexation resumes (see [Resuming a Phraseanet indexation](./01_intro.md#resuming-a-phraseanet-indexation)). |
| `idempotencePrefixes` | Prefixes of the keys of the created objects (`asset`, `collection`, `attributeDefinition`, `renditionDefinition`): an object found by its key is updated rather than created again. Each defaults to the base64 of `url` followed by `_`, so that two Phraseanet instances never share keys. |
| `instanceId` | Declared in the configuration type, not read by the indexer. |
| `databoxMapping` | The list of the Phraseanet databoxes to import, each with its target workspace (see below). |

An asset gets the key `<asset prefix><databox id>_<record id>` (`<story id>` for a story),
a collection the key `<collection prefix><databox id>:<path segment>`.

## `databoxMapping`

### `databox`
The name or ID of the Phraseanet databox to import. An unknown databox is skipped.

### `collections`
A list of Phraseanet collections (ID or name, separated by `,`) to import from. If unset or
empty, all the collections of the databox are searched.

### `phraseanetCollectionsInventory`
Optional helper field written in the generated mapping model (see
[Generate a mapping model](#generate-a-mapping-model)).

- Purpose: memo/debug only, to keep a human-readable inventory (`<name> id: <id> count: <n>`).
- Runtime behavior: **ignored by the indexer** during the import.
- Recommendation: keep it for reference or remove it once the `collections` filter is validated.

### `searchQuery`
The Phraseanet query that selects the records to import. If empty: all the records.

### `workspaceSlug`
The Databox workspace to import into. It is created if it does not exist, with the locales
of the `fieldMap` as enabled locales.

### `recordsCollectionPath`
Collection path where the records are imported as assets. An asset is named after the title
of its record (its `original_name` when the title is empty).

- The `recordsCollectionPath` can be a **[Twig](#about-twig)** template, to dispatch the assets
  into a tree of collections.

  e.g.: dispatch by Phraseanet collection name, then country and city:

  ```json
  "recordsCollectionPath": "/Collections/{{ collection.name | escapePath }}/{{ record.getMetadata('Country', '_').value | escapePath }}/{{ record.getMetadata('City', '_').value | escapePath }}"
  ```

  → `/Collections/MyPhraseanetCollection/France/Paris/IMG_1234.jpg`

- If the `recordsCollectionPath` is a plain string (no Twig tags), it is used as a root path
  **and completed with the Phraseanet collection name**:

  ```json
  "recordsCollectionPath": "/Collections"
  ```

  → `/Collections/MyPhraseanetCollection/IMG_1234.jpg`

- The `recordsCollectionPath` **can** be an empty string (or not set): as a plain string, it is
  completed with the Phraseanet collection name, which becomes the first-level collection.

  → `/MyPhraseanetCollection/IMG_1234.jpg`

### `importStories` and `storiesCollectionPath`

`importStories` and `storiesCollectionPath` define how the Phraseanet stories are imported, with
3 combinations. The stories without any record are imported first; the other stories are
imported along with the first of their records to be imported.

`importStories: true` requires a Phraseanet that returns the stories of the records
([Phraseanet PR #4602](https://github.com/alchemy-fr/Phraseanet/pull/4602)): the indexation
stops otherwise.

#### Do not import stories

```json
"importStories": false
```

#### Import stories as Phrasea stories

```json
"importStories": true
```

`importStories` set to `true` or unset, and no `storiesCollectionPath`. A story asset is created
in the collection given by `recordsCollectionPath` for the story, named after the title of the
story. Its renditions are those of the cover record of the Phraseanet story, or of its first
record.

Each record of the story (imported elsewhere as an asset) is added by reference to the story.

#### Import stories as Phrasea collections

```json
"importStories": true,
"storiesCollectionPath": "/Stories"
```

`importStories` set to `true` or unset, and a `storiesCollectionPath` (even empty). Each story
becomes a collection, named after the title of the story, and each record of the story
(imported elsewhere as an asset) is **added by reference** to this collection.

There is **no** story asset: the Phraseanet story record itself is **not** imported.

The `storiesCollectionPath` can be a **[Twig](#about-twig)** template, to dispatch the stories
into a tree of collections (the context is the story and its collection).

#### Examples

e.g. 1: import all stories **as Phrasea stories**:

```json
"recordsCollectionPath": "/Collections",
"importStories": true
```

→ `/Collections/MyPhraseanetCollection/JO-2024`, where "JO-2024" is the title of a Phraseanet
story: the story asset is placed like the records.

e.g. 2: import all stories in the same collection, **as Phrasea collections**:

```json
"importStories": true,
"storiesCollectionPath": "/Stories"
```

→ `/Stories/JO-2024`, where "JO-2024" is the title of a Phraseanet story.

e.g. 3: dispatch by Phraseanet collection name, then country and city:

```json
"importStories": true,
"storiesCollectionPath": "/Stories/{{ collection.name | escapePath }}/{{ record.getMetadata('Country', '_').value | escapePath }}/{{ record.getMetadata('City', '_').value | escapePath }}"
```

→ `/Stories/MyPhraseanetCollection/France/Paris/JO-2024`, where "JO-2024" is the title of a
Phraseanet story.

### `copyTo`
List (array) of paths where the assets are also added, by reference (without copying their
attributes).

Each path is a **[Twig](#about-twig)** template that must generate Databox collection path(s),
depending on run-time values like the record metadata.

If the asset is to be added in many places (paths), the template must generate **one line per
path**.

- e.g. 1: two-level dispatch with a single destination (mono-value fields):

  ```json
  "copyTo": [
    "/classification/{{record.getMetadata('Category', 'unknown_category').value | escapePath}}/{{record.getMetadata('SubCategory', 'unknown_subcategory').value | escapePath}}"
  ]
  ```

- e.g. 2: multiple destinations (multi-values field):

  ```json
  "copyTo": [
    "{% for s in record.getMetadata('Keywords', 'no_keyword').values %}/classification/{{ s | escapePath }}\n{% endfor %}"
  ]
  ```

  The `\n` outputs one line (= one path) per keyword.

  The default value "no_keyword" is needed: a record without keywords would otherwise not be
  added anywhere.

- e.g. 3: multiple destinations, one template each:

  To dispatch the records in many "classification" places, set several `copyTo` templates.

  ```json
  "copyTo": [
    "/classification/author/{{ record.getMetadata('Author', 'unknown_author').value | escapePath }}",
    "/classification/category/{{ record.getMetadata('Category', 'unknown_category').value | escapePath }}",
    "/classification/year/{{ record.getMetadata('Date', '').value is empty ? 'unknown_date' : record.getMetadata('Date').value | date('Y') }}"
  ]
  ```

### `fieldMap`
Map (key = attribute definition name) of the attributes to create and import.

#### Attribute definition settings

- `type`: a Databox attribute type (`text`, `number`, `date`, `date_time`, `boolean`,
  `keyword`, `json`, `html`, `code`, `color`, `geo_point`, `ip`…) or a Phraseanet field type
  (`string`, `date`, `number`). Default: the type of the Phraseanet field of the same name,
  `text` otherwise.
- `multivalue`: boolean.
- `readonly`: boolean; a read-only attribute is not editable in Databox.
- `labels`: map, e.g. `{"fr": "Titre", "en": "Title"}`.
- `allowInvalid`: boolean, accept values that do not match the type.
- `target`: `1` assets only, `2` stories only, `3` both.
- `values`: array of objects declaring the initial value(s) of the attribute:
  - `locale`: e.g. "fr";
  - `type`: how to evaluate the `value`: `metadata`, `template` or `text` (default);
  - `value`: the value expression, as a metadata name (= Phraseanet field name), Twig code
    or plain text.

  **text** type: the value is the immediate value of the attribute.

  **metadata** type: the value is the name of a Phraseanet field, like "Title". A mono-value
  attribute receives the values of the field joined with " ; ".

  **template** type: the value is Twig code, to compose complex value(s).

For the **template** type, the attribute value(s) are the result of the **[Twig](#about-twig)**
template, which must generate **one item per line** for a multi-values attribute (the lines of a
mono-value attribute are joined with " ; "). The Twig context only holds `record` (the record,
or the story).

_note 1:_

If the attribute definition name matches exactly a Phraseanet field name, the settings not set
in the configuration (type, multivalue, readonly, labels) are copied from the Phraseanet
metadata structure.

If the name does not match a Phraseanet field, those settings can be set in the configuration
block.

_note 2:_

If `fieldMap` **is not set**, the indexer generates a mapping model (see
[Generate a mapping model](#generate-a-mapping-model)).

_note 3:_

If a value of an attribute definition is declared with a locale, the definition is created
with `translatable=true`.

The locales of all the values are the "Enabled locales" of a workspace the indexer creates.

### `sourceFile`
The name of the Phraseanet subdef used as the source file of the asset.

Mostly `"sourceFile": "document"` for the original file.

If not set, the assets are created without a source file.

### `renditions`

Maps Phraseanet subdefs (and their structure) to Phrasea renditions (and their definitions).

A Phraseanet subdef is identified by its **type** (image, video, audio, document, unknown)
and its **name**, e.g. `image:thumbnail`.

A Phrasea rendition definition is declared by its **name** (the key of the map) and its
settings. The files of the mapped subdefs are imported as the renditions of the assets.

_notes:_

- If `renditions` **is not set**, the indexer generates a mapping model (see
  [Generate a mapping model](#generate-a-mapping-model)).
- To **not** import or create any rendition or rendition definition, set **both**
  `"sourceFile": false` **and** `"renditions": false`.
- A rendition can be declared without `builders`: it is not imported from Phraseanet, only its
  definition is created in Phrasea.

#### `parent`
A rendition can declare a `parent` rendition: the parent **must** be declared before the
child, otherwise the child is created without parent (and an error is logged).

If not set, the rendition is built from the source file of the asset.

#### `useAsMain`, `useAsPreview`, `useAsThumbnail`, `useAsAnimatedThumbnail`

Declare the rendition to be used as main, preview or thumbnail. `useAsAnimatedThumbnail` is not
read when the definition is created: the rendition named `thumbnailgif` (the name of the animated
thumbnail subdef of Phraseanet) is the one used as animated thumbnail.

#### `buildMode`
Tells Databox how to build the rendition (`RenditionDefinition::BUILD_MODE_*`):

- `0`: None, the rendition is not built;
- `1`: Pick source file: the file of the parent rendition, or the source file of the asset when
  there is no parent. Mostly used for the "main" rendition;
- `2`: Custom: built with the build definition that the indexer generates from the Phraseanet
  subdefs of the `builders`.

Default: `2` when `builders` are set, `1` otherwise.

#### `policy`
Name of the rendition policy of the definition, mostly "public" or "private"; the policy is
created in the workspace if it does not exist. If not set, the policy is named after the
**class** of the Phraseanet subdefs of the `builders` (document, preview, thumbnail…; `mixed`
when they differ), or `public` without builders.

#### `target`
`1` assets only, `2` stories only, `3` both.

#### `builders`
A builder can be defined for each family of renditions (image, video, audio, document).

##### `from`
Inside the builder, `from` maps the Phrasea rendition definition, for the family, to a
Phraseanet subdef.

The `from` value (Phraseanet subdef) is a string like `<document_type>:<subdef_name>`, e.g.
`video:preview`.

The build settings of the definition are generated from those of the Phraseanet subdef.

#### Example

```json
"sourceFile": "document",
"renditions": {
    "main": {
        "useAsMain": true,
        "buildMode": 1,
        "policy": "public"
    },
    "preview": {
        "useAsPreview": true,
        "policy": "public",
        "builders": {
            "image": {
                "from": "image:preview"
            },
            "video": {
                "from": "video:preview"
            }
        }
    },
    "thumbnail": {
        "useAsThumbnail": true,
        "policy": "public",
        "parent": "preview",
        "builders": {
            "image": {
                "from": "image:thumbnail"
            },
            "video": {
                "from": "video:thumbnail"
            }
        }
    }
}
```

### `importStatusBits`

Controls whether the Phraseanet status bits are imported in the Phrasea workspace, as the values
of an entity list attribute.

By default, all the status bits of the Phraseanet databox are imported (unless
`importStatusBits` is set to `false`). The status bits whose `label_on` and `label_off` are both
empty are skipped.

The status bits are imported as follows:
- an entity list named `Tags` holds one value per status bit, named after its `label_on` (or its
  `label_off` when `label_on` is empty);
- a multi-valued `entity` attribute named `Tags` (slug `tags`, faceted, for the assets and the
  stories) is bound to this list. When another attribute of the workspace already uses the slug
  `tags`, it is named `Tags (Phraseanet)` instead;
- every record or story receives, in this attribute, the values of its raised status bits.

The list, the attribute and the values that already exist in the workspace (found by name, the
attribute by its list) are reused: running the indexer again, or on a workspace whose former
tags were migrated to the `Tags` list, creates no duplicate. Status bits sharing the same label
share the same value.

To **completely disable** the import of status bits for a databox mapping, set:

```json
"importStatusBits": false
```

## Generate a mapping model

If `fieldMap` or `renditions` is missing in a databox mapping, the indexer logs a complete
generated model that you can copy, paste and adapt, and stops: nothing is imported.

### Procedure

1. In your location configuration, keep at least `databox` and `workspaceSlug` in the target
   `databoxMapping` item.
2. Remove (or do not set) `fieldMap` and/or `renditions`.
3. Run the indexation of the location:

   ```bash
   dc run --rm databox-indexer index <location-name> --no-server
   ```

4. In the logs, find the block starting with:
   `**** Missing 'renditions' or 'fieldMap' configuration ****`
5. Copy the generated JSON block (`Phraseanet full configuration equivalent`) into your
   configuration file, then adapt it.

Notes:
- The generated block contains `collections` and `phraseanetCollectionsInventory`.
- It maps every Phraseanet field and every subdef, and adds the `phr_record_id`, `phr_created_on`
  (and, with `importStories: true`, `phr_story_id`) attributes.
- `phraseanetCollectionsInventory` is informational only and is not used by the indexation.

## About Twig

The Twig templates of the configuration have the following context (`fieldMap` templates only
have `record`):

- `record`: record object (or story object)
    - `record.record_id`: string (`record.story_id` for a story)
    - `record.resource_id`: string
    - `record.databox_id`: string
    - `record.base_id`: string
    - `record.uuid`: string
    - `record.title`: string
    - `record.original_name`: string
    - `record.mime_type`: string
    - `record.created_on`: string
    - `record.updated_on`: string
    - `record.status`: list of status bits; ***use the `getStatus()` method***
    - `record.getStatus(<bit> [, <valueIfTrue> [, <valueIfFalse>]])`: boolean, the value of the
      status bit `<bit>`. The boolean value can be replaced by the string value(s)
      `valueIf…`
    - `record.subdefs`: list of subdefs; ***use the `getSubdef()` method***
    - `record.getSubdef(<name>)`: subdef object
        - `record.getSubdef(...).height`: number
        - `record.getSubdef(...).width`: number
        - `record.getSubdef(...).filesize`: number
        - `record.getSubdef(...).player_type`: string
        - `record.getSubdef(...).mime_type`: string
        - `record.getSubdef(...).created_on`: string
        - `record.getSubdef(...).updated_on`: string
        - `record.getSubdef(...).url`: string
        - `record.getSubdef(...).permalink`: permalink object
            - `record.getSubdef(...).permalink.url`: string
    - `record.metadata`: metadata objects by field name; ***use the `getMetadata()` method***
    - `record.getMetadata(<fieldName> [, <default>])`: metadata object, with the default value
      if the field is not set for this record.
        - `record.getMetadata(...).value`: the mono-value (if the field is multi-value: the
          values joined with " ; ").
        - `record.getMetadata(...).values`: the values as an array, sorted case-insensitively
          (if the field is mono-value: an array with a single value).
- `collection`: collection object (of the record)
    - `collection.databox_id`: string (same as `record.databox_id`)
    - `collection.base_id`: string (same as `record.base_id`)
    - `collection.collection_id`: number
    - `collection.name`: string

The `escapePath` filter escapes the `/` of a value (and replaces its control characters with
`_`, or with its argument), so that it makes a single collection name.

### Twig context technical note

So that a template does not fail when a field does not exist in a record (when accessing a
property like `.value`), `getMetadata(...)` returns an empty metadata object.

The same applies to the subdefs: `record.getSubdef('missingSubdef').permalink.url` returns
`null`.

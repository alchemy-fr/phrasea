# Databox indexer: Phraseanet configuration
```json lines
...
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
                            "type": "string",
                            "values": [
                                {
                                    "type": "template",
                                    "value": "{% for v in record.getMetadata('Subject').values %}{{v}}\n{% endfor %}"
                                }
                            ]
                        },
                        "Copyright": {
                            "type": "string",
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
                    }
                }
            ]
        }
    }
...
```

## `databox` 
The name or id of the Phraseanet databox to import.

## `collections`
A list/filter of Phraseanet collections (id or name, delimited by `,`) to import/search on. If unset or empty, will query all collections.

## `searchQuery`
The Phraseanet query to search for records to import. If empty: import all.

## `workspaceSlug` 
The Databox workspace where to import (created if not exists).

## `recordsCollectionPath`
Collection-path where to import records as "**main**" assets. The asset name will always be the same ase the "original_name" of the record.

- The `recordsCollectionPath` can be a **[Twig](#About-Twig)** expression (see [About Twig](#About-Twig)), allowing to dispatch the assets into a tree of collections.

    e.g.: Dispatch by phraseanet collection name, then country and city:
        
    ```json lines
    ...
    "recordsCollectionPath": "/Collections/{{ collection.name | escapePath }}/{{ record.getMetadata('Country', '_').value | escapePath }}/{{ record.getMetadata('City', '_').value | escapePath }}",
    ...
    ```
    --> `/Collections/MyPhraseanetCollection/France/Paris/IMG_1234.jpg`
    

- /!\ For backward compatibility: If the `recordsCollectionPath` is a simple string (no twig tags), it will be used as a root path
**and completd with phraseanet collection name**
    ```json lines
    ...
    "recordsCollectionPath": "/Collections",
    ...
    ```
  --> `/Collections/MyPhraseanetCollection/IMG_1234.jpg`


- `recordsCollectionPath` **can** be empty string (or not set): Since this is a simple (empty) string,
the backward compatibility applies, so the phraseanet collection name will be used as first-level collection.
  
    --> `/MyPhraseanetCollection/IMG_1234.jpg`


## `storiesCollectionPath`
Collection-path where to import stories. If unset: do not import stories.

Each story becomes a collection, and each contained record (= "main" asset) is **copied / aliased** to this collection.

The name of the collection will be the same as the name of the story.

- The `storiesCollectionPath` can be a **[Twig](#About-Twig)** expression, allowing to dispatch the stories into a tree of collections.


e.g. 1: Import all stories in the same collection:

```json lines
...
"storiesCollectionPath": "/Stories",
...
```
--> `/Stories/JO-2024` where "JO-2024" is the name of a phraseanet story.


e.g. 2: Dispatch by phraseanet collection name, then country and city:
        
```json lines
...
"storiesCollectionPath": "/Stories/{{ collection.name | escapePath }}/{{ record.getMetadata('Country', '_').value | escapePath }}/{{ record.getMetadata('City', '_').value | escapePath }}",
...
```
--> `/Stories/MyPhraseanetCollection/France/Paris/JO-2024` where "JO-2024" is the name of a phraseanet story.



## `copyTo`
list (array) of paths where to copy / alias "main" assets.

Each path is a **[Twig](#About-Twig)** expressions that must generate databox path(s), depending on run-time values like record metadata.

If the asset is to be copied in many places (paths), the twig must generate **one line per path**.

    
- e.g. 1: Two levels dispatch with unique destination (mono-value fields):
        
    ```json lines
    ...
    "copyTo": [
      "/classification/{{record.getMetadata('Category', 'unknown_category').value | escapePath}}/{{record.getMetadata('SubCategory', 'unknown_subcategory').value | escapePath}}"
    ]
    ...
    ```

- e.g. 2: Multiples destinations (multi-values field):

    ```json lines
    ...
    "copyTo": [
      "{% for s in record.getMetadata('Keywords', 'no_keyword').values %}/classification/{{ s | escapePath }}\n{% endfor %}"
    ]
    ...
    ```
  note: The `\n` is used to output one line (= one path) per keyword.

  note: The default value "no_keyword" is a must-have, because if the record had no keyword, it would not be copied anywhere.


- e.g. 3: multiples destinations :

  To dispatch the records in many "classification" places, one can set multiple `copyTo` settings.
    ```json lines
    ...
    "copyTo": [
      "/classification/author/{{record.getMetadata('Author', 'unknown_author').value | escapePath}},
      "/classification/category/{{record.getMetadata('Category', 'unknown_category').value | escapePath}},
      "/classification/year/{{record.getMetadata('Date', '').value is empty ? 'unknown_date' : {{record.getMetadata('Date').value | date('Y')}}
    ]
    ...
    ```

## `fieldMap` 
Map (key=AttributeDefinition name) of attributes to create / import.

### AttributeDefinition settings:

- `type`: "text", "number" or "json" (more todo) ; Default: "text"
- `multivalue`: boolean
- `readonly`: boolean
- `labels`: map, e.g. `{"fr": "Titre", "en": "Title"}`
- `values`: Array of objects to declare initial metadata value(s).

    #### values:
    - `locale`: e.g. "fr"
    - `type`: how to evaluate the `value` ("metadata", "template", "text" ; default: "text")
    - `value`: value expression, as metadata (=phraseanet field name), template (twig) code or simple text

    **text** type: The value is the immediate value for the attribute.

    **metadata** type: The value is the name of a Phraseanet field, like "Title".

    **template** type: The value is a Twig code, to compose complex value(s)

    
For the **template** type, the Attribute value(s) is the result of the **[Twig](#About-Twig)** expression,
which must generate **one item per line** for multi-values.
The Twig context is the same as `copyTo`


_note 1:_

If the attribute-definition name matches exactly a Phraseanet field name,
some AttributeDefinition settings will be copied from the
Phraseanet metadata-structure (type, multivalue, readonly, labels).

If the name does not match a Phraseanet field, those settings can be set in conf block.


_note 2:_

**If the `FieldMap` setting is not set**: All Phraseanet fields are imported


_note 3:_

If an AttributeDefinition setting is declared with a locale, it will be created
with `translatable=true`.

All the different AttributeDefinition locales are copied to the "Enabled locales" of the workspace.

## `sourceFile`
Declare le phraseanet subdef to be used as source file for the asset.

Mostly `"sourceFile": "document"` for the original file.

If not set, assets will be created without source file.

## `renditions`

Allows to map Phraseanet subdef / (structures) to Phrasea renditions / (definitions).

A Phraseanet subdef is identified by it **type** (image, video, audio, document, unknown) and its **name**. e.g. `image:thumbnail`.

A Phrasea rendition-definition is declared by its **name** and **build settings** (sections image, video, ...).

It is possible to declare a rendition with no `from`: not imported from Phraseanet, but created in Phrasea.

### `parent`
One can declare a `parent` relation between renditions, the parent rendition **must** be declared before the child.

If not set, the rendition will be built from the asset file.

### `useAsOriginal`, `useAsPreview`, `useAsThumbnail`, `useAsActiveThumbnail`

Declare the rendition to be used as original, preview, thumbnail or active thumbnail.

### `pickFromFile`
Tells the builder to copy the parent file (if no parent: copy the source file) to the rendition file.

### `class`
Phrasea rendition class, mostly "public" or "private". If not set, the value will be "guessed" from the subdef **class** (document, preview, ...).

### `builders`
A builder can be defined for each family of renditions (image, video, audio, ...).

#### `from`
Inside the builder, the `from` maps the phrasea rendition-definition - for the family -,
to the phraseanet subdef.

The `from` value (phraseanet subdef) is a string like `<document_type>:<subdef_name>`, e.g. `video:preview`.

The build settings will be generated from the phraseanet to match the subdef.


## e.g.

```json lines
...
        "sourceFile": "document",
        "renditions": {
            "original": {
                "useAsOriginal": true,
                "pickFromFile": true,
                "class": "public"
            },
            "preview": {
                "useAsPreview": true,
                "class": "public",
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
                "class": "public",
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
...
```


-------------------

# About Twig

When using twig expressions in the configuration, the context is the following:

- `record`: record object
  - `record.record_id` : string
  - `record.resource_id` : string
  - `record.databox_id` : string
  - `record.base_id` : string
  - `record.uuid` : string
  - `record.title` : string
  - `record.original_name` : string
  - `record.mime_type` : string
  - `record.created_on` : string
  - `record.updated_on` : string
  - `record.status` : status[] ***use `getStatus()` method***
  - `record.getStatus(<bit> [, <valueIfTrue> [, <valueIfFalse>]])` : boolean ; Value of sb <bit> (4...63).
    Boolean value can be replaced by string value(s) `valueIf...` 
  - `record.subdef` : subdef[] ***use `getSubdef()` method***
  - `record.getSubdef(<name>)` : subdef object
    - `record.getSubdef(...).height` : number
    - `record.getSubdef(...).width` : number
    - `record.getSubdef(...).filesize` : number
    - `record.getSubdef(...).player_type` : string
    - `record.getSubdef(...).mime_type` : number
    - `record.getSubdef(...).created_on` : string
    - `record.getSubdef(...).updated_on` : string
    - `record.getSubdef(...).url` : string
    - `record.getSubdef(...).permalink` : permalink object
      - `record.getSubdef(...).permalink.url` : string
  - `record.metadata` : metata[] ***use `getMetadata()` method***
  - `record.getMetadata(<fieldName> [,<default>])` : metadata object, with default value(s) if the field is not set for this record.
    - `record.getMetadata(...).value` : The mono-value (if the field is multi-value : concat values with " ; ").
    - `record.getMetadata(...).values` : The multi-values as array (if the field is mono-value : array with a single value).
- `collection`: collection object (of the record)
  - `collection.databox_id`: string (same as `record.databox_id`)
  - `collection.base_id`: string (same as `record.base_id`)
  - `collection.collection_id`: number
  - `collection.name`: string

## Twig context technical note:

To prevent twig to crash if a field doest not exists in a record (when trying to access a property like `.value`),
`getMetadata(...)` will return a "fake" empty metadata object.

Same method applies for subdefs: `record.getSubdef('missingSubdef').permalink.url` will return null. 

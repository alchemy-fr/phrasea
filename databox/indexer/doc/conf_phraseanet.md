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
                    "recordsCollectionPath": "/records",
                    "storiesCollectionPath": "/stories",
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
Collection-path where to import records as "main" assets.

## `storiesCollectionPath`
Collection-path where to import stories. If unset: do not import stories.

Each story becomes a collection, and each contained record (= "main" asset) is **copied / aliase** to this collection.

todo: How to import story metadata ?

## `copyTo`
list (array) of paths where to copy / alias "main" assets.

Each path is a **twig** expressions that must generate databox path(s), depending on run-time values like record metadata.

If the asset is to be copied in many places (paths), the twig must generate **one line per path**.

- ### `copyTo` Twig context :
    
    - `record` record object

    - `record.title` : _todo_

    - `record.uuid` : _todo_

    - `record.original_name` : _todo_

    - `record.status` : _todo_

    - `record.getMetadata(<fieldName> [,<default>])` : metadata object, with default value(s) if the field is not set for this record.

    - `record.getMetadata(...).value` : The mono-value (if the field is multi-value : concat values with " ; ").

    - `record.getMetadata(...).values` : The multi-values as array (if the field is mono-value : array with a single value).

        - e.g. 1: Two levels dispatch with unique destination (mono-value fields):

            ```json lines
            ...
            "copyTo": [
              "/classification/{{record.getMetadata('Category', 'unknown_category').value | escapePath}}/{{record.getMetadata('SubCategory', 'unknown_subcategory').value | escapePath}}"
            ]
            ...
            ```

        - e.g. 2: Multiple destinations (multi-values field):

            ```json lines
            ...
            "copyTo": [
              "{% for s in record.getMetadata('Keywords', 'no_keyword').values %}/classification/{{ s | escapePath }}\n{% endfor %}"
            ]
            ...
            ```
          note: The `\n` is used to output one line (= one path) per keyword.

          note: The default value "no_keyword" is a must-have, because if the record had no keyword, it would not be copied anywhere.

        - e.g. 3: multiple destinations :

          To dispatch the records in many "classification" places, one can set many `copyTo` setting.
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

    "text" type: The value is the immediate value for the attribute.

    "metadata" type: The value is the name of a Phraseanet field, like "Title".

    "template" type: The value is a Twig code, to compose complex value(s)

    
For the "template" type, the Attribute value(s) is the result of the **twig** expression,
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


_twig context technical note_: 

To prevent twig to crash if a field doest not exists in a record (when trying to access a property like `.value`),
`getMetadata(...)` will return a "fake" empty metadata object.

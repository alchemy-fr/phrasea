# Form Configuration

The form of a target is a **form schema** (`FormSchema`), in one of two formats (`format`):

- `builder` (default in the client): designed visually with the form builder of the Uploader client (Admin > Form Editor). Its fields come from the attributes of a Databox workspace, including entity lists.
- `liform` (legacy): a JSON schema in the [LiForm format](https://limenius.github.io/liform-react/#/), typed by hand. Still supported; the client can convert it to the builder.

Either way, a schema belongs to a target and a locale (empty for every language), and `localeMode` tells which locale the data is sent to Databox with.

Whatever the format, the data sent with a commit is a flat object: Databox reads each key as the `slug` of an attribute definition of the workspace (optionally suffixed by a locale: `title:fr`), and a few reserved keys (see below). The values of an entity attribute are entity UUIDs.

## Form builder

The builder lists, for the chosen Databox workspace:

- **Databox attributes**: the enabled attributes an uploader can fill. A field keeps the attribute slug as key; the label is initialized from the attribute translations.
- **Databox values**: `databox_name`, `databox_tags`, `databox_is_story`, `databox_story_name`, and `__notify`.
- **Custom fields**: free keys (text, long text, number, date, date and time, checkbox, color, choice list), e.g. for a Phraseanet target.
- **Layout**: section titles and texts.

Fields are dragged into the form and reordered. Each one has translated labels, help and placeholders, and can be required, hidden (its default value is always sent) or bounded (lengths, min/max). Entity and tag fields render as a searchable list, or as checkboxes / radio buttons.

### Stored definition

```json
{
    "version": 1,
    "workspaceId": "<Databox workspace id>",
    "fields": [
        {"id": "f_1", "source": "layout", "kind": "section", "label": {"_": "Information", "fr": "Informations"}},
        {
            "id": "f_2",
            "source": "attribute",
            "key": "keywords",
            "type": "entity",
            "multiple": true,
            "required": true,
            "widget": "autocomplete",
            "label": {"_": "Keywords", "fr": "Mots-clés"},
            "attribute": {"id": "<attribute definition id>", "slug": "keywords", "databoxType": "entity", "entityListId": "<entity list id>"}
        },
        {"id": "f_3", "source": "builtin", "key": "databox_tags", "type": "tag", "multiple": true, "label": {"_": "Tags"}},
        {
            "id": "f_4",
            "source": "custom",
            "key": "origin",
            "type": "choice",
            "hidden": true,
            "defaultValue": "web",
            "choices": [{"value": "web", "label": {"_": "Web"}}],
            "label": {}
        }
    ]
}
```

The texts are maps by locale; `_` is the text of every other language.

The API validates the definition when the schema is saved (`App\Form\Builder\FormDefinition`), then the submitted data against it (`App\Form\Builder\BuilderFormHandler`). Only the keys of the form are stored with the commit, and hidden fields always get their default value: a user cannot write an attribute the form does not offer. Entity and tag values must exist in Databox.

### Databox access

The uploader API reads Databox on behalf of the user (with their access token), at `DATABOX_API_URL`:

| Endpoint | Used by |
|---|---|
| `GET /databox/workspaces`, `GET /databox/workspaces/{id}` | the builder (form editors only) |
| `GET /databox/workspaces/{id}/fields` | the builder: the attributes an uploader can fill |
| `GET /databox/workspaces/{id}/tags`, `GET /databox/entity-lists/{id}/values` | the builder: default values, preview |
| `GET /databox/targets/{id}/destination` | the builder: the workspace of the target's default destination |
| `GET /targets/{id}/form-fields/{key}/options?query=&ids[]=&page=` | the upload form: the values of an entity or tag field of the target's form |

Users therefore need access to Databox (the `databox` role, and to the workspace) to fill entity and tag fields.

### Converting a LiForm schema

In the LiForm editor, "Convert to the form builder" turns the schema into a builder form, opened unsaved: the keys matching an attribute of the chosen workspace become Databox fields, the reserved keys built-in fields, the others custom fields. The titles of the schemas of the same target in other locales can be merged as translations. A report lists what could not be kept as is (`oneOf`, nested objects, restricted entity lists, type mismatches). Saving replaces the LiForm schema; the other schemas of the target are left untouched.

## LiForm schemas (legacy)

### Reserved Keywords

Certain field names have special meaning in the uploader:

- `__notify`: If a field is named `__notify`, users can request an email notification when their upload is processed.

  Example:
  ```json
  {
      "properties": {
          "__notify": {
              "title": "Notify me when done!",
              "type": "boolean"
          }
      }
  }
  ```

- `collection_destination`: Specifies the collection where the asset will be stored.

#### Databox Reserved Keywords

- `databox_name`: Sets the name of the asset (`databox_story_name` for the story).
- `databox_tags`: Sets the tags for the asset (array of tag UUIDs).
- `databox_is_story`: Indicates if the asset is a story (boolean).

### Hard-Coded Form Data (Bulk Data)

Uploader can define custom form data for a specific client. In the Bulk Data section (admin only), you can edit the JSON data that will be applied to every asset in a batch:

```json
{
  "my-key": "my-value",
  "key2": 1.5,
  "key-array": [
    "item1",
    "item2"
  ],
  "key-object": {
    "foo": "bar"
  }
}
```

### Databox Example

A typical databox form schema might look like this:

```json
{
    "type": "object",
    "required": [
        "databox_name"
    ],
    "properties": {
        "databox_name": {
            "type": "string",
            "title": "Asset Title"
        },
        "description": {
            "type": "string",
            "title": "Description",
            "widget": "textarea"
        },
        "keywords": {
            "type": "array",
            "items": {
                "type": "string"
            },
            "title": "Keywords"
        },
        "date": {
            "type": "string",
            "title": "Date",
            "format": "date",
            "widget": "compatible-datetime"
        },
        "number": {
            "type": "number",
            "title": "Number"
        },
        "transporttype": {
            "type": "string",
            "title": "Transport type",
            "enum": [
                "c84fb3a2-74e5-4323-87e9-15cc0899b1e6",
                "631d34d0-fb8d-4f1f-aee3-77d176784572",
                "c172e0c0-d486-4104-8b52-fa60d72d7100",
                "bf1f6c32-ac47-43de-8389-80daef1fba20"
            ],
            "enum_titles": [
                "Bike",
                "Boat",
                "Car",
                "Plane"
            ]
        },
        "transporttype_multi": {
            "title": "Transport types",
            "type": "array",
            "items": {
                "type": "string",
                "enum": [
                    "c84fb3a2-74e5-4323-87e9-15cc0899b1e6",
                    "631d34d0-fb8d-4f1f-aee3-77d176784572",
                    "c172e0c0-d486-4104-8b52-fa60d72d7100",
                    "bf1f6c32-ac47-43de-8389-80daef1fba20"
                ],
                "enum_titles": [
                    "Bike",
                    "Boat",
                    "Car",
                    "Plane"
                ]
            }
        },
        "databox_tags": {
            "type": "array",
            "widget": "choice-multiple-expanded",
            "title": "Tags",
            "items": {
                "type": "string",
                "enum": [
                    "2ac5ea50-51d6-449a-b64f-5f711d9d1b97",
                    "631d34d0-fb8d-4f1f-aee3-77d176784572"
                ],
                "enum_titles": [
                    "Embargo FR",
                    "#2"
                ]
            }
        }
    }
}
```

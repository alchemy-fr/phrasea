# Form Configuration

A target (`Target`) holds its upload form and its data:

- `form`: one form designed visually with the form builder of the Uploader client (Admin > Forms), or `null` for no form. Its fields come from the attributes of a Databox workspace (entity lists included), the values Databox reads itself, and custom keys. The texts are translated in the form itself: there is one form per target, for every language.
- `data`: a JSON object merged into the data of every commit (Admin > Target data), or `null`.

The data sent with a commit is a flat object: Databox reads each key as the `slug` of an attribute definition of the workspace (optionally suffixed by a locale: `title:fr`), and a few reserved keys (see below). The values of an entity attribute are entity UUIDs. The commit records the language of the user who sent it (`formLocale`).

A target without form accepts no value but the keys handled by the uploader itself (starting with `__`).

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

The API validates the definition when the form is saved (`App\Form\Builder\FormDefinition`), then the submitted data against it (`App\Form\Builder\BuilderFormHandler`). Only the keys of the form are stored with the commit, and hidden fields always get their default value: a user cannot write an attribute the form does not offer. Entity and tag values must exist in Databox.

### Endpoints

| Endpoint | Access |
|---|---|
| `GET /targets/{id}/form`: the definition, `null` without form | whoever can read the target |
| `PUT /targets/{id}/form`, body `{"form": <definition> \| null}`: answers the definition; `422` with violations at `form.<path>` when it is invalid | form editors (`form_schema` permission) |
| `GET /targets/{id}/data`, `PUT /targets/{id}/data`, body `{"data": {…} \| null}` (an empty object is `null`) | target data editors (`target_params` permission) |

`GET /targets` and `GET /targets/{id}` tell whether a target has a form (`hasForm`) and data (`hasData`), without their content.

The permissions are global ACEs (on every object of the type) of the `form_schema` and `target_params` object types, managed in the admin (Permissions > Form schema permissions / Target params permissions); admins always have them. `GET /me` exposes them as `permissions.form_schema` and `permissions.target_data`.

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

## Reserved keys

- `__notify`: the user asks for an email notification when the upload is processed (built-in field "Notify me when done").
- `collection_destination`: the collection the assets are stored in; defaults to the default destination of the target.

### Databox reserved keys

- `databox_name`: the name of the asset (`databox_story_name` for the story).
- `databox_tags`: the tags of the asset (array of tag UUIDs).
- `databox_is_story`: whether the files are grouped in a story (boolean).

## Target data

The data of the target is merged into the data of every commit, over the values entered by the user: a key of the target data always wins. Use it for values the user must not see nor change:

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

## Migration from LiForm

Before version 5, a target could have several form schemas (one per locale, with a `localeMode`), in the [LiForm](https://limenius.github.io/liform-react/#/) JSON schema format or in the builder one, and its data in a separate "target params" record. The migration `Version20261111120000` moves them onto the target:

- the target params become `Target.data`;
- the schemas of a target are merged into one form. The base schema is the builder one if any, else the one for every locale, else the most recent one: its texts become the texts for every language (`_`). The labels, help texts, placeholders and choice labels of the other schemas are added as translations under their locale (matched by field key, and by value for the choices), without replacing a text already there;
- a LiForm schema is converted to the builder format without Databox, so without workspace: the reserved keys (`databox_name`, `databox_story_name`, `databox_is_story`, `__notify`) become built-in fields, every other key a custom field with the same key — the data sent to Databox does not change. To benefit from the Databox attribute fields (entity lists, translations, checks), pick the workspace in the form builder and replace the custom fields by the matching attributes;
- what the builder cannot express is left out, with a warning of the migration naming the target and the key: `databox_tags` (tags need a workspace: add the field again in the builder), `oneOf`, nested objects, arrays of objects or of booleans, keys that are not attribute slugs, choice lists without any value (the choices without value are dropped);
- the `localeMode` is gone: the form locale of a commit is always the language of the user;
- the ACEs set on one schema or one params record are deleted, the global ones (`form_schema`, `target_params`) are kept.

The conversion cannot be reverted.

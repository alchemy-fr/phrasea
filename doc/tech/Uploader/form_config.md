# Form Configuration

Uploader forms are based on the [LiForm format](https://limenius.github.io/liform-react/#/). This allows for flexible, JSON-schema-driven forms that can be customized for different upload scenarios.

## Reserved Keywords

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

### Databox Reserved Keywords

- `databox_title`: Sets the title of the asset.
- `databox_tags`: Sets the tags for the asset (array of tag UUIDs).
- `databox_is_story`: Indicates if the asset is a story (boolean).

## Hard-Coded Form Data (Bulk Data)

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

## Databox Example

A typical databox form schema might look like this:

```json
{
    "type": "object",
    "required": [
        "databox_title"
    ],
    "properties": {
        "databox_title": {
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

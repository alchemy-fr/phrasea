# Upload API

## LiForm schema example

```json
{
    "type": "object",
    "required": [],
    "properties": {
        "collection_destination": {
            "enum": [
                "1",
                "2",
                "3"
            ],
            "enum_titles": [
                "Test Collection",
                "Public Collection",
                "Private Collection"
            ],
            "type": "string",
            "title": "Collection"
        },
        "metadata-1": {
            "type": "string",
            "title": "Title"
        },
        "metadata-4": {
            "type": "string",
            "title": "Description"
        },
        "__notify": {
            "title": "Notify me when done!",
            "type": "boolean"
        }
    }
}
```

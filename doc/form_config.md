# Form configuration

Form are based on LiForm format.

See [LiForm documentation](https://limenius.github.io/liform-react/#/)

## Hard coded form data

Uploader can define custom form data for a specific client.
To do so we've extended the LiForm schema with a `contextData` node:

```json
{
  "contextData": {
    "key": "value",
    "key_2": 42
  },
  "properties": {
    "...": {}
  },
  "required":["..."]
}
```

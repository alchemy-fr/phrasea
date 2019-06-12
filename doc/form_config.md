# Form configuration

Form are based on LiForm format.

See [LiForm documentation](https://limenius.github.io/liform-react/#/)

## Hard coded form data (Bulk data)

Uploader can define custom form data for a specific client.
In the Bulk data section of the upload (you must be an admin), you can edit the data JSON that will be applied to every assets:

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

# Expose service

Payload (draft):

```json
{
  "id": "123",
  "expires_at": "",
  "password": "s3cr3t",
  "files": [
    {
      "id": "123",
      "type": "image/jpeg",
      "file": "https://host/foo.jpg",
      "thumbnail": "https://host/thumb_foo.jpg",
      "meta": [
        {"label": "Title", "value": "Foo", "locale": "fr_FR", "type": "string" },
        {"label": "Title", "value": "Foo" },
        {"label": "Creation date", "value": "2019-05-01T12:00:00Z", "type": "datetime" }
      ]
    }
  ],
  "download_url": "https://host/foo.zip"
}
```

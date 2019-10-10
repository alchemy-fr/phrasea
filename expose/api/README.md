# Expose service

## Reference

Payload, defines the publication (draft):

```json
{
  "id": "123",
  "begins_at": null,
  "expires_at": null,
  "layouts": "download | gallery | lightbox",
  "theme": "my_theme1",
  "theme_options": {
    "background_color": "#FF0000"
  },
  "security": {
    "protection_method": "password | authentication | url_token",
    "password": "s3cr3t",
    "required_roles": []
  },
  "download_url": "https://site.com/package.zip",
  "files": [
    {
      "id": "123",
      "date": "2019-01-011T00:00:00Z",
      "type": "image/jpeg",
      "download_url": "https://host/foo.jpg",
      "video_thumbnail_url": "...",
      "preview_url": "https://host/thumb_foo.jpg",
      "thumbnail_url": "https://host/thumb_foo.jpg",
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

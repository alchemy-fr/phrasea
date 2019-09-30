# Upload Configuration

```json
{
  "uploader": {
    "max_upload_file_size": 25165824,
    "max_upload_commit_size": 419430400,
    "max_upload_file_count": 3,
    "client": {
      "logo": {
        "src": "https://www.phraseanet.com/wp-content/uploads/2014/05/PICTO_PHRASEANET.png",
        "margin": "2px 10px"
      }
    },
    "admin": {
      "logo": {
        "src": "https://www.phraseanet.com/wp-content/uploads/2014/05/PICTO_PHRASEANET.png",
        "with": "80px"
      }
    }
  }
}
```

- `max_upload_file_size`: Maximum allowed size (in bytes) for each file

- `max_upload_commit_size`: Maximum allowed total size (in bytes) for an upload batch

- `max_upload_file_count`: Maximum allowed files for an upload batch

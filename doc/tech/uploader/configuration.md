# Uploader Configuration

## Example Configuration

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
        "width": "80px"
      }
    }
  }
}
```

## Configuration Options

- **max_upload_file_size**: Maximum allowed size (in bytes) for each uploaded file. Example: `25165824` (24 MB).
- **max_upload_commit_size**: Maximum allowed total size (in bytes) for a single upload batch. Example: `419430400` (400 MB).
- **max_upload_file_count**: Maximum number of files allowed per upload batch. Example: `3`.

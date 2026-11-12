# Allowed file types and file serving

Files are uploaded by browsers straight to the storage, then served from it through signed URLs. Two rules keep a file from becoming a script running on the storage origin (stored XSS): the upload checks the couple formed by the declared MIME type and the file extension, and every signed URL forces the response headers from the type known by the application.

## Allowed file types (upload)

`DATABOX_ALLOWED_FILE_TYPES` and `UPLOADER_ALLOWED_FILE_TYPES` (`ALLOWED_FILE_TYPES` inside the containers, `alchemy_storage.upload.allowed_types` in the Symfony configuration) list MIME types, each with the extensions allowed **for this type**:

```dotenv
DATABOX_ALLOWED_FILE_TYPES='image/*(.jpg,.jpeg,.png),application/pdf(.pdf),audio/*(.mp3,.wav),application/x-indesign(.indd),text/csv'
```

- An upload is accepted when one entry matches its MIME type **and** lists its extension: `text/html` with a `.jpg` name, or `image/jpeg` with a `.pdf` name, are refused (`400`).
- `*` only stands for the subtype (`image/*`). A main type wildcard (`*/*`, `*/jpeg`) is refused: every upload then fails with a configuration error (`500`, logged). List the actual types instead (`application/x-indesign(.indd)`).
- An entry without extensions (`text/csv`) accepts the extensions registered for the uploaded type in the Symfony MIME database.
- Entries are separated by commas; the extensions of an entry are written with or without their dot, in any case.
- A file without extension, or whose type is neither declared nor guessable from its extension, is refused.
- An empty value accepts every file.

The check applies to multipart uploads (`POST /uploads`), to the presigned `PUT` URLs (expose `upload` mode) and to the files sent in the request (expose `file` mode, with the type detected from the content). The clients read the same variable to filter the file picker.

Browsers do not always know the type of a file: the databox and uploader clients fall back to the type of the extension (`.indd` → `application/x-indesign`). A client sending `application/octet-stream` needs an `application/octet-stream(.ext)` entry: such files are always served as attachments (see below).

The bytes of the file are not inspected: the type is the one declared by the client. The protection against malicious content is the forced response headers below.

## Response headers of signed URLs (read)

`Alchemy\StorageBundle\Storage\UrlSigner::getSignedUrl($path, $options)` (and the CloudFront generator) always force `Content-Type`, and `Content-Disposition` when needed, from the MIME type known by the application (`type` option, the one stored in the database, never the one stored on the object):

| Known type                                                            | `inline: true` requested               | Otherwise                                         |
|-----------------------------------------------------------------------|----------------------------------------|---------------------------------------------------|
| `image/*` except `image/svg+xml`, `video/*`, `audio/*`, `application/pdf` | `Content-Type: <type>`, displayed inline | `Content-Type: <type>`, `attachment`              |
| anything else: SVG, HTML, XML, text, unknown or missing type          | `application/octet-stream`, `attachment` | `application/octet-stream`, `attachment`          |

`download: true` always produces an attachment. The list lives in `Alchemy\StorageBundle\Storage\InlineContentTypePolicy`.

Consequence: SVG, HTML, text… originals and renditions are downloaded instead of being displayed by the browser. Image renditions of such files (thumbnails, previews) are still displayed.

With S3/MinIO, the headers are the `response-content-type` and `response-content-disposition` overrides of the presigned `GetObject` request: they are part of the signature and cannot be removed from the URL. For CloudFront, see [CloudFront](./02_cloudfront.md#forced-response-headers).

## Recommendations

These settings live in the infrastructure, outside Phrasea:

- **Serve the storage on its own registrable domain** (e.g. `phrasea-files.com` for an application on `phrasea.com`), not on a subdomain of the applications (the default `minio.${PHRASEA_DOMAIN}`): a file served by mistake would then share neither cookies nor same-site status with the applications.
- **Add security headers** on the responses of the storage or of its CDN:
  - `X-Content-Type-Options: nosniff`, so that browsers do not guess a type other than the forced one;
  - `Content-Security-Policy: sandbox; default-src 'none'`, so that a document opened from the storage can neither run scripts nor load anything. Images, videos and PDF opened directly are not affected; if an application displays files in an `<iframe>` or `<object>` (PDF viewer), check it still works before enabling it.

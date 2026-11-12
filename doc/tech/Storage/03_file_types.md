# Allowed file types and file serving

Files are uploaded by browsers straight to the storage, then served from it through signed URLs. Three rules keep a file from becoming a script running on the storage origin (stored XSS), or from being handed to a media tool as something else: the upload checks the couple formed by the declared MIME type and the file extension, the content of the file is checked against the declared type, and every signed URL forces the response headers from the type known by the application.

## Allowed file types (upload)

`DATABOX_ALLOWED_FILE_TYPES`, `UPLOADER_ALLOWED_FILE_TYPES` and `EXPOSE_ALLOWED_FILE_TYPES` (`ALLOWED_FILE_TYPES` inside the API, worker and client containers, `alchemy_storage.upload.allowed_types` in the Symfony configuration) list MIME types, each with the extensions allowed **for this type**:

```dotenv
DATABOX_ALLOWED_FILE_TYPES='image/*(.jpg,.jpeg,.png),application/pdf(.pdf),audio/*(.mp3,.wav),application/x-indesign(.indd),text/csv'
```

- An upload is accepted when one entry matches its MIME type **and** lists its extension: `text/html` with a `.jpg` name, or `image/jpeg` with a `.pdf` name, are refused (`400`).
- `*` only stands for the subtype (`image/*`). A main type wildcard (`*/*`, `*/jpeg`) is refused: every upload then fails with a configuration error (`500`, logged). List the actual types instead (`application/x-indesign(.indd)`).
- An entry without extensions (`text/csv`) accepts the extensions registered for the uploaded type in the Symfony MIME database.
- Entries are separated by commas; the extensions of an entry are written with or without their dot, in any case.
- A file without extension, or whose type is neither declared nor guessable from its extension, is refused.
- An empty value accepts every file.

The check applies to every way a file enters an application:

- multipart uploads (`POST /uploads`) and presigned `PUT` URLs (expose `upload` mode), on the declared type;
- files sent in the request (`file` field of databox assets and renditions, uploader `POST /assets`, expose `file` mode, Phraseanet renditions), on the type of the content (see below);
- databox files given by URL (`sourceFile`, CSV import, webhooks…), on the declared type or the type of the extension when the file is created, then on the type of the content when it is imported;
- files coming from the uploader into databox, and files the uploader downloads from a URL.

The clients read the same variable to filter the file picker. `EXPOSE_ALLOWED_FILE_TYPES` covers what Databox publishes: its own allowed types plus the output formats of the renditions (`webm`, `ogg`, `apng`, `tiff`…).

Browsers do not always know the type of a file: the databox and uploader clients fall back to the type of the extension (`.indd` → `application/x-indesign`). A client sending `application/octet-stream` needs an `application/octet-stream(.ext)` entry: such files are always served as attachments (see below).

## Content check

The type declared by the client is kept as the type of the file, but the first bytes of the content (64 KiB) are inspected with libmagic (`Alchemy\StorageBundle\Upload\ContentTypeDetector`):

- **Forbidden formats**, whatever the declared type: HLS playlists (`#EXTM3U`) and ffconcat scripts (`ffconcat version`), even after a BOM or blank lines. ffmpeg would read the files or URLs they list.
- **Families**: the detected type must belong to the family of the declared one: image (including SVG, Photoshop, PostScript), audio/video (one family: libmagic cannot tell an audio-only MP4 or OGG from a video), document (PDF, RTF, text, CSV, Office, OpenDocument), or other. A JPEG declared `video/mp4`, or an HTML page declared `image/jpeg`, is refused. A file declared without type or as `application/octet-stream` claims nothing and is not compared.
- **Containers**: libmagic sometimes only knows the container of a format. These detected types are accepted for a more precise declared type: `application/zip` for OOXML and OpenDocument, `application/x-ole-storage`, `application/CDFV2` and `application/vnd.ms-office` for legacy Office documents, `text/xml` for SVG, `application/pdf` for Illustrator, `text/plain` for CSV, and `application/octet-stream` (nothing recognized) for raw HEVC/H.264 streams and for any declared type outside the image, audio/video and document families (InDesign…). What libmagic returns for each allowed format is recorded in `ContentTypeDetectorTest`.
- **Allowed types**: the detected type (the declared one when only its container or nothing was recognized) must be allowed for the file name. An SVG named `.jpg` is refused when `image/svg+xml` is not allowed for `.jpg`.

Where it runs:

- Files received by an API (direct uploads, uploader, expose, Phraseanet, imports by URL): a refused file is not stored, the request answers `400`, the job or the message fails with the reason.
- Databox asset source files, whatever their origin (multipart upload, URL, uploader, new version): the `check_content` job of the asset ingest workflow, run after the import of the source and before every integration. It stores the detected type on the file (`file.detected_type`). A refused file is marked as such (`file.content_rejected`): its analysis fails with a message giving both types (`Declared type "image/jpeg", content is "text/html"`), the asset is quarantined, its URL is not served, and the job fails, so that no rendition or analysis runs. Later analyses do not lift the refusal. Bypassing the quarantine (`POST /assets/{id}/quarantine-bypass`, granted by the `bypassQuarantine` capability) does, and the next ingest keeps that decision.

Files already stored before this check are not inspected again; running the asset ingest workflow again checks their content.

Legitimate files declared with a wrong type by a client are refused too.

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

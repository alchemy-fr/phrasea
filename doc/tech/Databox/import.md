# Import of assets (JSON / NDJSON)

Databox creates assets from an **instructions file**: one entry per asset, whose files
(source and renditions) are downloaded from URLs. The file is sent:

- from the client: "Import assets…" in the menu of a workspace or of a collection; the
  imports are followed in the **Imports** tab of the workspace management;
- with the API: `POST /asset-imports`;
- to the webhook of an **Asset import** integration (`core.asset_import`), e.g. from
  an external system.

## Formats

- **NDJSON** (`.ndjson`, `.jsonl`): one entry (a JSON object) per line, blank lines
  ignored. The file is read line by line: there is no size limit. Prefer it for large
  imports.
- **JSON** (`.json`): a list of entries, or an object `{"assets": [...]}`. The file is
  decoded at once: above `ASSET_IMPORT_JSON_MAX_SIZE` bytes (50 MB by default), the import
  fails and asks for NDJSON.

The line of an entry (reported with its error) is its line in the NDJSON file, or its
position from 1 in the JSON list.

## Entry

```json
{
  "key": "ext-123",
  "externalId": "ext-123",
  "name": "Sunset",
  "collection": "<collection ID or /collections/<ID>>",
  "path": "Sport/Football",
  "tags": ["<tag ID or exact name>"],
  "privacy": 2,
  "attributes": {"title": "Sunset", "keywords": ["sea", "sky"], "description:fr": "Coucher de soleil"},
  "sourceUrl": "https://cdn.example.com/a.jpg",
  "sourceName": "a.jpg",
  "sourceType": "image/jpeg",
  "previewUrl": "https://cdn.example.com/a-preview.jpg",
  "thumbnailUrl": "https://cdn.example.com/a-thumb.jpg",
  "renditions": {"<definition key, name or ID>": "https://cdn.example.com/a-print.jpg"}
}
```

Every field is optional, but an entry needs at least one of `sourceUrl`, `previewUrl`,
`thumbnailUrl`, `renditions` or `name`. Unknown fields are ignored.

- `key`: the unique key of the asset in the workspace. **An entry whose key matches an
  existing asset is skipped** (counted as `skipped`), never updated: importing the same
  file twice creates nothing the second time.
- Destination: the default collection of the import (or the workspace root), unless the
  entry sets `collection` (an existing collection of the workspace) or `path`. Both
  cannot be used together.
- `path`: collection names separated by `/`, from the default destination. The missing
  collections are created (as with `POST /collections`, so the initiator must be allowed
  to), once for all the entries of the file.
- `privacy`: an integer (`0` secret … `5` public) or a label (`private_in_workspace`,
  `public`…); the privacy of the import by default.
- `tags`: existing tags of the workspace, by ID or exact name.
- `attributes`: by attribute definition slug, with an optional `:<locale>` suffix; a list
  gives several values to a multiple attribute.
- `sourceUrl`, `sourceName`, `sourceType`: the source file, its original name and MIME type.
- `previewUrl`, `thumbnailUrl`: the file of the first rendition definition used as preview
  (resp. thumbnail) of the workspace; `renditions`: files by rendition definition (its
  `key`, e.g. `preview`, then its name, e.g. `Preview`, then its ID).

The files are always **imported** into the storage (`importFile`), never kept as URL
references: the source by the `import_source` job of the ingest workflow, the renditions
by an `ImportFile` message. The given renditions are **substituted**: the ingest workflow
does not build them again.

## Processing

1. The instructions file is stored on the main storage and an `AssetImport` is created
   (`pending`).
2. A `DispatchAssetImport` message reads the file (`dispatching`): each entry is checked
   (structure, `collection`/`path`), its `path` resolved, then an `ImportAssetEntry`
   message is sent for it. Once the whole file is read, the total is known (`processing`).
   An unreadable file (invalid JSON, JSON too large) fails the import (`failed`, `error`).
3. Each `ImportAssetEntry` creates its asset with the mappers of `POST /assets`, as the
   initiator of the import (the user who created it, or the owner of the integration):
   same validation, same rights. An entry that fails is recorded with its line, key and
   message (`failed`); it never fails the import.
4. When every entry is counted (`created` + `skipped` + `failed` = `total`), the import is
   `completed`.

## API

```http
POST /asset-imports
{
  "workspace": "/workspaces/<ID>",
  "collection": "/collections/<ID>",
  "format": "ndjson",
  "privacy": 2,
  "originalName": "export.ndjson",
  "data": "{\"name\": \"Sunset\", \"sourceUrl\": \"https://cdn.example.com/a.jpg\"}\n"
}
```

Give the file either inline (`data`) or uploaded to the storage beforehand (`multipart`:
`{uploadId, parts}`, see the multipart uploads). The user must be allowed to create assets
in the destination (`collection`, or the workspace).

- `GET /asset-imports?workspace=<ID>`: the imports of the workspace, newest first
  (`page`, `limit`). All of them to whoever can create assets in the workspace, their own
  imports to the others.
- `GET /asset-imports/<ID>`: `status` (`pending`, `dispatching`, `processing`,
  `completed`, `failed`), `total`, `created`, `skipped`, `failed`, `error`, `endedAt`…
- `GET /asset-imports/<ID>/errors`: the failed entries by line (`line`, `key`, `message`).
- `DELETE /asset-imports/<ID>`: deletes the tracking and the instructions file, never the
  created assets. The entries of a running import are then dropped.

## Webhook

Create an **Asset import** integration (`core.asset_import`) on the workspace. Its
configuration:

- `securityKey`: generated at creation (and renewed when the integration is duplicated);
- `collectionId`: the default destination (the workspace root otherwise);
- `privacy`: the default privacy of the entries.

Its "Webhook URL" and "Security Key" are shown in the integration information. The format
is given by the `Content-Type`: `application/x-ndjson` (or `application/jsonl`,
`application/jsonlines`) for NDJSON, `application/json` for JSON.

```bash
curl -X POST "https://api-databox.phrasea.local/integrations/asset-import/<integration ID>/imports" \
  -H "Authorization: ApiKey <security key>" \
  -H "Content-Type: application/x-ndjson" \
  --data-binary @export.ndjson
# 202 {"id": "<import ID>"}
```

| Response | Cause |
| --- | --- |
| 202 | The import is created, owned by the owner of the integration |
| 400 | Empty body |
| 401 | Missing `Authorization: ApiKey <key>` header |
| 403 | Wrong key |
| 404 | Unknown, disabled, or not an Asset import integration |
| 415 | Unsupported `Content-Type` |

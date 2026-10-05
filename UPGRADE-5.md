# UPGRADE FROM 4.x to 5.0

## Multipart uploads are planned by the API

Clients no longer decide the part size of a multipart upload nor ask the API
for a presigned URL before each part. `POST /uploads` now answers with the part
size (`chunkSize`) and the presigned PUT URL of every part (`urls`, keyed by
part number, valid for 3 hours). `POST /uploads/{id}/parts` with `{"from": n}`
renews the URLs from part `n` (expired URLs, resumed uploads).

`POST /uploads/{id}/part` still works for clients of the previous version but is
deprecated and will be removed.

## Collections and stories are ordered

Assets now hold a rank inside each collection and story they belong to
(`collection_asset.position`, seeded by the migration from the creation order),
and the `asset` index gets a new nested `collectionPositions` field.

Re-populate the `asset` index right after running the migrations: until then the
index has no mapping for `collectionPositions`, so sorting by `@position` (story
carousel, `GET /collections/{id}/assets`, `GET /assets/{id}/story-assets`) fails
or serves an incomplete order.

```bash
bin/console fos:elastica:populate --index=asset
```

`@position` sorting requires the search to be narrowed down with `collection`
(exact membership) or `story`: `parent`, which spans the sub-collections, is
rejected.

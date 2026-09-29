# UPGRADE FROM 4.x to 5.0

## Multipart uploads are planned by the API

Clients no longer decide the part size of a multipart upload nor ask the API
for a presigned URL before each part. `POST /uploads` now answers with the part
size (`chunkSize`) and the presigned PUT URL of every part (`urls`, keyed by
part number, valid for 3 hours). `POST /uploads/{id}/parts` with `{"from": n}`
renews the URLs from part `n` (expired URLs, resumed uploads).

`POST /uploads/{id}/part` still works for clients of the previous version but is
deprecated and will be removed.

# Delegated AssetExport reads

An `AssetExport` is owned by the authenticated user who creates it. A normal
`GET /asset-exports/{id}` remains owner-only. To deliver an approved export to
a different requester, its owner may supply `delegateUserId` (a user UUID) and
`delegationHash` (lowercase SHA-256 hex) in `POST /asset-exports`, or later use
the owner-only `PATCH /asset-exports/{id}/delegation` with the same fields.
The latter does not restart or alter the export job.

The named requester must authenticate and send the 64-character secret in
`X-Phrasea-Export-Grant` on `GET /asset-exports/{id}`. Databox hashes the
presented secret and compares it to the stored hash. Neither the secret nor
the hash is returned by the API. Other users, including holders of the secret,
remain forbidden. Existing exports without a delegation remain owner-only.

Order derives a different secret for each order part from its delivery secret,
order ID, part key and requester ID. It does not store or send that secret to
the browser. Order checks its own proof, tenant/site/workspace/requester scope,
expiration and revocation before invoking Databox. Databox does not replace
those business checks: the delegated GET only grants access to this one export
for the named authenticated requester and the bearer of its secret. Operators
must protect the Order delivery secret and coordinate its rotation with active
exports and delivery proofs.

---
title: Share links
---

# Share links

A share link gives access to one publication to whoever holds it, without changing the
settings of the publication. It is a random token of 64 characters, opened by the permalink
`<EXPOSE_CLIENT_URL>/s/{token}`.

## Scope

An active share link (neither expired nor revoked) opens its publication even when:

- the publication is protected by a password or by authentication: the visitor stays anonymous,
  no password is asked, no wrong password is counted;
- the publication is disabled, not started yet or expired.

It does **not** cover:

- the sub-publications: each one is shared on its own, they keep their own rules (a disabled
  child stays hidden, a child protected by the password of its parent asks for it);
- the download settings and the download terms: the holder of a link is a visitor, not an
  editor.

## Management

The links of a publication are managed by its operators (admin, owner, or `OPERATOR`
permission), from the expose client: the _Share_ button of the publication page and of its edit
screen.

| Method   | Path                              |                                                                            |
| -------- | --------------------------------- | -------------------------------------------------------------------------- |
| `GET`    | `/publications/{id}/shares`       | the links of a publication, the most recent first                          |
| `POST`   | `/publications/{id}/shares`       | `{"name": "…", "expiresAt": "…"}`, both optional (no `expiresAt` = no end) |
| `GET`    | `/publication-shares/{id}`        |                                                                            |
| `PATCH`  | `/publication-shares/{id}`        | rename, extend, remove the end (`"expiresAt": null`); refused once revoked |
| `POST`   | `/publication-shares/{id}/revoke` | for good; the link stays listed as revoked                                 |
| `DELETE` | `/publication-shares/{id}`        |                                                                            |

A new expiration must be in the future. The token never changes.

## Access with a token

`GET /publication-shares/resolve/{token}` is public: it answers the publication opened by an
active link (`{"publication": {"id", "slug", "title"}, "expiresAt"}`), and the same 404 for an
unknown, expired or revoked token.

The permalink page of the client resolves the token, keeps it in a cookie of its publication,
`shareTokens_<id>`, and `shareTokens_<slug>` when the publication has a slug, so that a client
URL by slug or by id finds it (`SameSite=None; Secure` in HTTPS, so that it works in an iframe;
the 5 most recent tokens of the publication), and redirects to the publication. From then on,
only the requests of the client about that publication (its page, its assets, the view logs, the
downloads) carry its tokens in the `X-Share-Tokens` header, comma separated: the tokens of the
other publications are never sent with them. The embed page of an asset (`/embed/{assetId}`)
does not know the publication before the response: when the asset is not authorized and tokens
are kept for its publication, it asks for the asset again with them. The API reads 20 tokens at
most, of 64 characters at most, and ignores the others.

Responses to a request carrying `X-Share-Tokens` are never cached publicly (`Vary` lists the
header).

## Known limits

- The signed URLs already given to a link holder (downloads, archive, WebVTT, `?jwt=`) stay
  valid until they expire (`URI_JWT_TTL`, 24 hours by default) after the link is revoked.
- Each publication opened by a link adds one or two cookies to the domain of the client (sent
  to its Next.js server too), without any cap on their number.
- The client reserves the first path segment `s`, the API does not: the client URLs of a
  publication whose slug is `s` (`/s`, `/s/{asset}`) do not open it, its slug must be changed.

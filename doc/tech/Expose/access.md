---
title: Access
---

# Access

Who may read a publication, and how a visitor gets in. The rules live in
`expose/api/src/Security/Voter/PublicationVoter.php`.

## Permissions

- **Administrators**: the users with `ROLE_ADMIN` (Keycloak role `admin` or `expose-admin`, see
  [Permissions](../Authentication/permissions.md)) and the tokens carrying the `publish` scope.
  They may do anything.
- **Owner**: the user who created the publication (`ownerId`), who may edit, operate and delete
  it.
- **ACL**: the permissions granted to users and groups on a publication, or on every publication,
  from the back office ("Permissions" menu).

| Action                                                    | Granted to                                                                             |
| --------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| Read a visible publication                                | anyone, then subject to its security method                                           |
| Read a hidden publication (disabled, out of its dates)    | administrators, owner, `EDIT`, holders of a share link                                 |
| Pass the `authentication` method                          | `VIEW`                                                                                 |
| Create a publication                                      | administrators, `CREATE` on the publications                                           |
| Edit (settings, assets, cover…)                           | administrators, owner, `EDIT`                                                          |
| Manage the share links, change the profile                | administrators, owner, `OPERATOR`                                                      |
| Delete                                                    | administrators, owner, `DELETE`                                                        |

An editor reads every detail whatever the security method, and is subject to none of the
download settings (see [Downloads](./downloads.md)).

The publication tells the client what the user may do in `capabilities`:
`{"edit": …, "delete": …, "operator": …}`. The client shows the "Edit", "Share" and "Delete"
buttons from them.

The profiles have their own permissions (`PublicationProfileVoter`): administrators, owner, and
`VIEW`, `EDIT`, `DELETE`, `CREATE` ACL on the profiles. A profile can only be assigned by a user
who can read it.

## Security methods

The security method of a publication is the one of its **security container**: the publication
itself when it (or its profile) defines a method, else the closest ancestor that does. The
publication returns the id of its container in `securityContainerId`: one password or one
authorization opens the whole branch below it.

### Password

The client asks for the password, then sends every password typed so far in the `X-Passwords`
header of its requests: base64 of a JSON object mapping the id of a security container to its
password.

```text
X-Passwords: base64({"<securityContainerId>": "<password>", …})
```

The client keeps them in the `passwds` cookie, for a year (`SameSite=None; Secure` in HTTPS, so
that it works in an iframe).

- The password is stored hashed and never returned: the publication only says `hasPassword`. A
  write without a password keeps the current one.
- Wrong passwords are limited to **10 per 15 minutes**, by client IP and security container
  (`config/packages/rate_limiter.yaml`); behind a proxy, `TRUSTED_PROXIES` must be set for the
  IP to be the client's. Once the limit is reached, even the right password is refused until the
  window slides.
- `authorizationError`: `missing_password`, `invalid_password` or `too_many_attempts`.

### Authentication

The visitor must be signed in (`Authorization: Bearer` with a Keycloak access token) **and** hold
the `VIEW` permission on the publication.

- `authorizationError`: `missing_access_token` (anonymous) or `not_allowed` (signed in, without
  the permission).
- The client sends an anonymous visitor straight to Keycloak, unless they just signed out or the
  page is embedded (sign-in window then, see [Embedding](./iframe.md)). A signed-in user who is
  not allowed gets an "Access denied" screen.

### Signed URLs

A URL signed with `?jwt=` (see [Downloads](./downloads.md#signed-urls)) passes the security
method: it was given to a reader already authorized.

## Reduced response

Without access to the details (`READ_DETAILS`), `GET /publications/{id or slug}` answers `200`
with a reduced publication, `"authorized": false`:

- always: `id`, `slug`, `authorized`, `authorizationError`, `securityContainerId`,
  `securityMethod`, `capabilities`;
- when the publication is publicly listed, also its title, description, translations, cover
  (thumbnail only), and the fields of the lists.

An asset of such a publication (`GET /assets/{id}`) is reduced to its id and its reduced
publication.

## Terms of use

When `terms.enabled` is set, the client shows the terms (their text, or a link to their URL)
before anything else: the content of the publication is not rendered until they are accepted.
The acceptance is kept in a cookie of the browser, `terms_p_{publicationId}`, for a year.
Nothing is checked by the API: the terms of use are a notice, not a protection. The download
terms are enforced by the API, see [Downloads](./downloads.md#download-terms).

## Share links

A share link opens one publication, disabled or protected, without password nor account. See
[Share links](./share-links.md).

## CORS

Only `EXPOSE_CLIENT_URL` is allowed (`config/packages/nelmio_cors.yaml`), without credentials:
the client authenticates with headers (`Authorization`, `X-Passwords`, `X-Share-Tokens`), never
with cookies. `/admin` answers no CORS header. The WebVTT files are open to any origin.

## HTTP cache

The anonymous reads of the publications are cached by the proxy cache of `expose-api-nginx`
(`expose/api/docker/nginx/tpl/default.conf`) and by the browsers
(`src/Http/Cache/PublicHttpCachePolicy.php`):

- cached: `GET /publications/{id or slug}`, `GET /assets/{id}` and the WebVTT files, without query
  string, of a publication that is visible and has no security method, read without
  `Authorization`, `X-Passwords` nor `X-Share-Tokens`;
- `Cache-Control: public, max-age=600, s-maxage=600`, shortened to the expiration of the
  publication or of an ancestor;
- `Vary: Authorization, X-Passwords, X-Share-Tokens`: nginx bypasses its cache when one of them is
  sent;
- the other responses are not publicly cacheable (the WebVTT files of a protected publication are
  `private, no-store`).

When a publication, a profile or an asset changes, `EntityHttpCacheListener` has
`ProxyCachePurger` purge the URLs concerned (the publication by id and by slug, its parent and
descendants, their assets and the WebVTT files), in every variant nginx may keep (`Accept` header,
with and without the client origin). The stylesheet (`style.{hash}.css`) is cached by the browsers for 90 days, its URL
changing with its content.

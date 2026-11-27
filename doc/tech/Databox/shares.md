---
title: 'Shares'
---

# Shares

A share is a **public link** to one or several assets of a workspace: anyone holding the link
views and downloads their shared renditions, without an account. An asset set can have
several links, each with its own audience, dates and renditions.

## Model

`App\Entity\Core\Share`:

| Field                  | Meaning                                                                  |
|------------------------|--------------------------------------------------------------------------|
| `name`                 | Optional; the client calls it "Name / audience".                         |
| `assets`               | At least one, all of the same workspace.                                 |
| `renditionDefinitions` | The shared renditions; none selected means every shareable one.          |
| `startsAt`, `expiresAt`| Optional validity window.                                                 |
| `enabled`              | A disabled link is refused. Default `true`.                              |
| `token`                | 64 random characters, set at creation and never changed.                 |
| `passwordHash`         | Optional password asked to visitors, hashed (`password_hash()`); never output. |
| `ownerId`              | The creator.                                                             |

The password is written as `password` (4 to 255 characters, 400 otherwise; `null` or `""`
removes it, left out of a `PATCH` it is kept); the API only outputs `hasPassword`. Its changes
are logged masked in the [audit log](./audit-log.md).

The link opened by visitors is the client page `/s/{id}/{token}`.

### What a link delivers

- **Assets**: the assets of the share that are neither trashed nor in [quarantine](./quarantine.md).
  The others stay in the share but are not served.
- **Renditions**: the renditions whose definition has a **public rendition policy** (see
  [access control](./access-control.md#rendition-policies)), within the selection of the link
  if any, not hidden by an [asset policy](./asset-policies.md), and stored in the main
  storage. A selection only narrows: a rendition of a private policy is never shared, and a
  policy made private later stops being delivered. This depends only on the share, never on
  the visitor.
- **Attachments**: a shared rendition (the main one, else the preview, else the first) of the
  attachments of the delivered assets, as long as the attached asset is delivered too and the
  share owner may still read it (`App\Service\Share\ShareAttachmentResolver`).
- **Terms**: the current [terms](./workspace-terms.md) of the workspace, and its logo.

Alternate URLs of the source files are not given to visitors.

## Rights

`App\Security\Voter\ShareVoter`:

- creating a share requires `READ` and `SHARE` on every asset (`SHARE`: workspace owner, the
  `SHARE` bit on the asset, or "Share assets" on its workspace or collections);
- reading it: its owner, a user allowed to share all its assets, or anyone with a **valid
  token** — the share is enabled, started, not expired, and the token matches — and, for a
  share protected by a password, a valid **access token** (see below);
- editing or deleting it: its owner, or a user allowed to share all its assets.

Assets added to an existing share must be shareable by the user.

### Password

A share protected by a password (`App\Service\Share\ShareAccessManager`):

- `POST /s/{id}/unlock?token=…` with `{"password": "…"}` exchanges the password for an
  **access token** valid 24 hours: `{"access": "…", "expiresAt": "…"}`. The access token
  signs the share, its expiration and the current password hash with `kernel.secret`:
  changing or removing the password revokes the access tokens already handed out.
- `GET /shares/{id}/public?token=…&access=…` then opens the share. Without a valid access
  token, it answers 403 `{"error": "share_password_required"}`.
- Wrong passwords are limited by client IP and share (rate limiter `share_password`: 10 per
  15 minutes, sliding window, Redis pool `rate_limiter.cache`): beyond, `unlock` answers 429
  `{"error": "too_many_attempts"}` with `Retry-After`, even with the right password. Each
  counted wrong password answers 403 `{"error": "invalid_password"}` and is logged in the
  [audit log](./audit-log.md) (`share_password_failed`); the refused attempts are not.
- `unlock` answers 404 for an unknown share or token, a disabled, not started or expired
  share, or a share without password: nothing is revealed to whoever does not hold the link.
- The owner and the users allowed to share all its assets read it without the password.
- A protected share gives **no permalink**: `GET /s/{id}/a/…` answers 404, whoever asks,
  and its `alternateUrls[].url` and `attachments[].url` are temporary signed URLs of the
  storage. Only its page is shared: no Matomo content impression is tracked for it, and the
  [share export](./automation-share-export.md) exports no rendition URL.

## API

| Method and path                                   | Security          | Effect |
|---------------------------------------------------|-------------------|--------|
| `GET /shares?assetId=…`                           | `READ`            | The shares of an asset the user may read (they hold the token). |
| `POST /shares`                                    | `CREATE`          | `name`, `assets`, `renditionDefinitions`, `startsAt`, `expiresAt`, `enabled`, `password`. |
| `GET`, `PATCH /shares/{id}`                       | `READ`, `EDIT`    | |
| `DELETE /shares/{id}`                             | `DELETE`          | Revokes the link. |
| `GET /shares/{id}/public?token=…[&access=…]`      | valid token       | The share as visitors see it: delivered assets, renditions, attachments, terms, logo. 403 `share_password_required` for a protected share without valid `access`. |
| `POST /s/{id}/unlock?token=…`                     | valid token       | `{"password"}` → `{"access", "expiresAt"}` of a protected share; 403 `invalid_password`, 429 `too_many_attempts`, 404 otherwise. |
| `GET /s/{id}/a/{asset}/r/{rendition}?token=…`     | valid token       | Redirects to a shared rendition (`{rendition}` is a definition id); 404 otherwise, and for a protected share. |
| `GET /s/{id}/a/{attachment}?token=…`              | valid token       | Redirects to an attachment; 404 otherwise, and for a protected share. |

When `MATOMO_URL` is set, rendition views are tracked as Matomo content impressions; a
tracking failure never fails the response.

A link is revoked by deleting it; `enabled`, `startsAt` and `expiresAt` suspend it.

## Client

The asset action **Share** (for one or several assets of a workspace the user may share)
opens the share dialog (`features/share/ShareDialog.tsx`):

- "Create a public link": a single link to these assets, without name nor dates; unchecking
  it revokes the link.
- "Advanced: multiple links with audience and dates": "Create new share link", with "Name /
  audience", "Starts at", "Expires at", "Protect with a password" and "Shared renditions"
  ("Only the checked renditions can be viewed and downloaded from this link."). A protected
  link shows a "Password" badge; editing it offers "Change" and "Remove". A protected link is
  never the "public link" of the simple mode.
- Each link offers "Copy link", "Embed code", social networks and email, "Revoke", and,
  per asset, "Copy URL" and "Remove from this link".
- "Embed code" gives an `img` tag for a single image, otherwise an `iframe` of the link with
  `?embed=1`, which shows the players only.
- A link protected by a password offers no rendition URL to copy and no `img` tag: only its
  page, embedded as an `iframe` that asks the password.

The public page (`app/s/[id]/[token]`) shows a single asset directly, or a gallery with a
viewer (`?asset=<id>`), "Download all…" with a choice of renditions, the "Attached files",
and, first, a blocking "Terms & Conditions" dialog when the workspace has terms. It is not
indexed by search engines and has its own Open Graph preview. An invalid link shows "This
link is not valid or has expired".

A link protected by a password first shows "Protected link" (`SharePasswordForm.tsx`),
without anything of the share, in the `?embed=1` page too. The access token it gets is kept
for the browser tab in `sessionStorage` (`share.access.{id}`, `features/share/shareAccess.ts`)
until it expires or is refused. Such a link has no Open Graph preview of its content.

## Other entry points

- The `share_export` automation action creates shares (see
  [Automation: share export](./automation-share-export.md) and [automations](./automations.md)).
- The [MCP server](./mcp.md) lists and creates shares (`App\Mcp\Tool\ShareTools`).
- The EasyAdmin page **Shares** lists them, with "Password protected".

Deleting assets warns about the shares holding them (see [asset lifecycle](./asset-lifecycle.md#trash)).

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
| `ownerId`              | The creator.                                                             |

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
  token** — the share is enabled, started, not expired, and the token matches;
- editing or deleting it: its owner, or a user allowed to share all its assets.

Assets added to an existing share must be shareable by the user.

## API

| Method and path                                   | Security          | Effect |
|---------------------------------------------------|-------------------|--------|
| `GET /shares?assetId=…`                           | `READ`            | The shares of an asset the user may read (they hold the token). |
| `POST /shares`                                    | `CREATE`          | `name`, `assets`, `renditionDefinitions`, `startsAt`, `expiresAt`, `enabled`. |
| `GET`, `PATCH /shares/{id}`                       | `READ`, `EDIT`    | |
| `DELETE /shares/{id}`                             | `DELETE`          | Revokes the link. |
| `GET /shares/{id}/public?token=…`                 | valid token       | The share as visitors see it: delivered assets, renditions, attachments, terms, logo. |
| `GET /s/{id}/a/{asset}/r/{rendition}?token=…`     | valid token       | Redirects to a shared rendition (`{rendition}` is a definition id); 404 otherwise. |
| `GET /s/{id}/a/{attachment}?token=…`              | valid token       | Redirects to an attachment; 404 otherwise. |

When `MATOMO_URL` is set, rendition views are tracked as Matomo content impressions; a
tracking failure never fails the response.

A link is revoked by deleting it; `enabled`, `startsAt` and `expiresAt` suspend it.

## Client

The asset action **Share** (for one or several assets of a workspace the user may share)
opens the share dialog (`features/share/ShareDialog.tsx`):

- "Create a public link": a single link to these assets, without name nor dates; unchecking
  it revokes the link.
- "Advanced: multiple links with audience and dates": "Create new share link", with "Name /
  audience", "Starts at", "Expires at" and "Shared renditions" ("Only the checked renditions
  can be viewed and downloaded from this link.").
- Each link offers "Copy link", "Embed code", social networks and email, "Revoke", and,
  per asset, "Copy URL" and "Remove from this link".
- "Embed code" gives an `img` tag for a single image, otherwise an `iframe` of the link with
  `?embed=1`, which shows the players only.

The public page (`app/s/[id]/[token]`) shows a single asset directly, or a gallery with a
viewer (`?asset=<id>`), "Download all…" with a choice of renditions, the "Attached files",
and, first, a blocking "Terms & Conditions" dialog when the workspace has terms. It is not
indexed by search engines and has its own Open Graph preview. An invalid link shows "This
link is not valid or has expired".

## Other entry points

- The `share_export` automation action creates shares (see
  [Automation: share export](./automation-share-export.md) and [automations](./automations.md)).
- The [MCP server](./mcp.md) lists and creates shares (`App\Mcp\Tool\ShareTools`).
- The EasyAdmin page **Shares** lists them.

Deleting assets warns about the shares holding them (see [asset lifecycle](./asset-lifecycle.md#trash)).

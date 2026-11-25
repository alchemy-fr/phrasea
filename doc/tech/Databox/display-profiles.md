---
title: 'Display profiles'
---

# Display profiles

A display profile is a set of display preferences that a user selects, and can share: the
attributes shown on assets, the content of the grid cards, the pinned search filters, and
the synced preferences (layout, facets, theme…). Who may share profiles and how a default
profile applies are described in [instance permissions](./permissions.md).

## Model

`App\Entity\Profile\Profile` (`/profiles`, ACL type `profile`): `name`, `description`,
`public`, `defaultProfile`, `ownerId`, its items, and `data` (`ProfileData`), the synced
preferences of the client: `layout`, `display`, `displayBatchEdit`, `facets`,
`sidebarSections`, `assetPanelTabs`, `theme`.

`App\Entity\Profile\ProfileItem` is an entry of a section, ordered by `position`:

| Section        | Value | Content                                                      |
|----------------|-------|--------------------------------------------------------------|
| Attributes     | 0     | The attributes shown on an asset, with dividers and spacers. |
| Facets         | 1     | Attribute items only; the client keeps facets in `data`.     |
| Grid           | 2     | The attributes shown on the grid cards.                      |
| Filters        | 3     | The pinned search filters; attribute items only.             |

An item is an attribute definition (`type` 0), a built-in attribute (1, `key`), a divider (2,
`key` being its title) or a spacer (3). Its `options`:

- every section: `displayEmpty`, `format`;
- Grid: `placement` (`region` `over` the thumbnail, anchors `tc`, `ml`, `cc`, `mr`, `bl`,
  `bc`, or `below` it, anchors `l`, `c`, `r`), `variant` (rich, chip, text), `color`,
  `size` (small, medium, large), `showLabel`;
- Filters: `widget` (`select`, `radio`, `inline`), `trueLabel`, `falseLabel`, `reversed`
  (offer false first) for booleans, and `defaultQuery`, the AQL default value.

The items of definitions the user cannot read are left out of the output.

## Behaviors

- **Selecting a profile** stores its id in the `profile` user preference and replaces the
  synced preferences with its `data`. "Default display profile" (no profile) falls back to
  the default profile of the user, if any.
- **Auto-sync** ("Auto-sync preferences") pushes the changes of the synced preferences to the
  current profile when the user may edit it; otherwise "Save current preferences to profile"
  does it on demand.
- **Pinning** an attribute from the asset view adds it to the Attributes section; without a
  current profile, the user's first profile is created and selected
  (`POST /profiles/default/items`).
- **Pinned filters** show in the search; their default value applies to every search that
  does not set the field, the home page and a reset search included, until the user picks
  another value or clears it. Only searchable attributes and built-in attributes can be
  filters.
- **Hover preview.** The preview shown on hover lists the attributes of the Attributes
  section of the current profile, or every attribute when none is pinned. It is set by the
  display options: "Display preview on hover", "Display file in preview", "Display attributes
  in preview", "Preview size".

## API

| Method and path                       | Security |
|---------------------------------------|----------|
| `GET /profiles`                       | the profiles owned, shared with the user, or public |
| `GET /profiles/user-default`          | authenticated (see [default display profiles](./permissions.md#default-display-profiles)) |
| `POST /profiles`                      | authenticated |
| `GET`, `PATCH`, `DELETE /profiles/{id}` | `READ`, `EDIT`, `DELETE`; making it public takes `SHARE` |
| `GET /profiles/{id}/items`            | `READ` |
| `POST /profiles/{id}/items`           | `EDIT`; `{items}` |
| `PUT /profiles/{id}/items/{itemId}`   | `EDIT` |
| `POST /profiles/{id}/remove`          | `EDIT`; `{items}` (ids) |
| `POST /profiles/{id}/sort`            | `EDIT`; the item ids in order |
| `POST /profiles/default/items`        | authenticated |

`App\Security\Voter\ProfileVoter`: `READ` to a public profile, its owner and `VIEW`; `EDIT`
and `DELETE` to the owner and the matching bit; `SHARE` and `EDIT_PERMISSIONS` to the owner
holding the "Share own profiles" capability, and `OWNER`.

## Client

- **Top bar menu** "Display profile": "Switch profile", "Default display profile", "Auto-sync
  preferences", "Save current preferences to profile", "New profile…", "Manage profiles…".
- **Management** (`/profiles/{id}/manage/{tab}`), tabs in this order:
  1. "General": name, description, owner, visibility;
  2. "Asset attributes": the Attributes section, with "Divider", "Spacer", "Show even if
     empty" and "Format";
  3. "Grid card": per attribute, "Display as" ("Rich", "Chip", "Text"), "Size", "Chip color",
     "Show label", and its zone, "Over the thumbnail" or "Below the thumbnail";
  4. "Search filters": per filter, its "Default value" and, for booleans, the "Value picker"
     ("Dropdown menu", "Radio buttons", "Radio buttons in the search bar"), the labels of
     `true` and `false`, and `Offer "false" first`;
  5. "Permissions": "Every user can select a public profile.", the "Default profile" switch,
     and the permissions.

Profiles the user may not edit are read-only.

## Administration

The EasyAdmin pages **Profiles** and **Profile Items** list them.

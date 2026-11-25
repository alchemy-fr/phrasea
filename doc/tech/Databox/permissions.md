---
title: 'Instance and entity list permissions'
---

# Instance permissions, default profiles and entity lists

This page covers the permissions that do not come from the ACL of a workspace, a collection
or an asset (see [Access control](./access-control.md) for those): the instance
capabilities, the default display profiles (see also [Display profiles](./display-profiles.md))
and the rights on a single entity list (see also [Entity lists](./entity-lists.md)).

## Instance permissions

Some Databox capabilities do not belong to any workspace: creating a workspace,
sharing one's display profiles and one's baskets, publishing one's saved searches, making
display profiles default ones. They are
granted at the **instance level**, to users or groups, by a **metadata** of an access
control entry (ACE) set on a whole object type: an ACE with an object type and **no
object id**.

| Capability                 | Object type    | Metadata | Effect                                                                                                         |
|----------------------------|----------------|----------|----------------------------------------------------------------------------------------------------------------|
| Create a workspace         | `workspace`    | `5`      | `POST /workspaces`; the creator owns the workspace (only admins choose another owner).                         |
| Share own profiles         | `profile`      | `6`      | The owner of a profile may make it public and edit its ACL (`share` and `editPermissions` capabilities).       |
| Share own baskets          | `basket`       | `7`      | The owner of a basket may share it and edit its ACL (`share` and `editPermissions` capabilities).              |
| Publish own saved searches | `saved_search` | `8`      | The owner of a saved search may make it Private or Public, and edit its ACL (`publish` and `editPermissions`). |
| Assign default profiles    | `profile`      | `9`      | Set or unset `defaultProfile` on a profile it may edit (see below).                                            |

The source of truth is `App\Security\InstancePermission` and
`App\Security\Voter\DataboxExtraPermissionInterface` (API), and
`features/permissions/instancePermissions.ts` (client).

### Defaults

**Admins only.** Admins have every capability (`AdminVoter`); nobody else has any until it
is granted. Creating baskets, saved searches and display profiles stays open to every
authenticated user (including the implicit creation of the default profile,
`POST /profiles/default/items`).

Admins do not list the baskets of others, though: `GET /baskets` only returns their own
baskets and the ones shared with them (user or group), like for any user. `AdminVoter` still
opens any basket to them by its id, and the administration (EasyAdmin) lists them all.

What already exists is kept: profiles made public, baskets shared and searches published
before a user lost the capability stay as they are. The owner of a public profile may still
rename it or edit its items, or make it private, but not make it public again; likewise,
the owner of a Public search may still rename it, or make it Secret, but not publish it
again.

### Granting

In the Databox client, as an admin: settings menu → **Instance permissions**. One row per
user or group (plus "All users"), one column per capability.

The **Global permissions** page of the administration (EasyAdmin) only edits the mask of
these ACEs: it neither shows nor grants the capabilities, and keeps them when it writes.

The current user's capabilities are answered by `GET /capabilities`:

```json
{"createWorkspace": false, "shareProfile": true, "shareBasket": true, "publishSavedSearch": false, "assignDefaultProfile": false}
```

The ACEs without object id are read and written through `/permissions/ace(s)` by admins only.

### Warning

An ACE without object id applies to **every object of the type**: any bit of its mask
(`VIEW`, `EDIT`, `SHARE`, `OWNER`…) grants the permission on **all** the baskets, saved
searches, profiles or workspaces of the instance. This is why the capabilities are
metadata, not bits. The client editor keeps the mask and the other metadata of these ACEs
untouched, and flags the rows which carry some.

The metadata checks on an object (`hasMetadata()`) also read the ACEs of its type: the
capability values must differ from the other metadata of `DataboxExtraPermissionInterface`
(`PERM_EDIT_PERMISSIONS`, `PERM_QUARANTINE`…). A capability set on the ACE of one object
is not a capability.

## Default display profiles

A display profile flagged `defaultProfile` is applied to **its readers** (owner, users and
groups of its ACL, everyone when it is public) while they have not chosen a profile. No
population is stored: who it applies to is who reads it. Anonymous visitors are not
concerned.

- **Fallback only.** The default applies while the user has not chosen a profile: the
  `profile` preference is absent or `null`. An explicit choice wins; choosing "Default
  display profile" again (profile `null`) goes back to the default. Nothing is written to
  the preferences of the user: the client applies the synced preferences of the default
  (layout, display…) to the ones the user has not set, in the tab only.
- **Resolution** (`ProfileRepository::findUserDefault()`), when the user reads several
  default profiles: a profile shared with the user (owned or by ACL) wins over a public one,
  then the oldest (`createdAt`, then id).
- **Rights.** Changing the flag takes `EDIT` on the profile (`PATCH /profiles/{id}`) and the
  capability; sending its current value takes nothing more.

API: `GET /profiles/user-default`, the default profile of the current user (`404` without
any). Not to be confused with `POST /profiles/default/items`, which adds to the first
profile the user owns.

In the Databox client: profile management → **Permissions** tab, "Default profile" switch
(holders of the capability). The administration (EasyAdmin) shows and filters the flag
under Searches → Profiles.

## Entity list permissions

By default, an entity list follows its workspace: its readers read it (and propose values
when the list accepts new ones), its editors manage it. An editor of the workspace may also
give some users or groups rights on **one** list, from the **Permissions** section of the
list form (workspace management → Entities), through ACEs of object type `entity_list`:

| Permission         | Mask       | Effect                                                                                   |
|--------------------|------------|------------------------------------------------------------------------------------------|
| Manage the list    | `OPERATOR` | Edit the list properties (`PATCH /entity-lists/{id}`) and everything "Manage values" allows. |
| Manage values      | `EDIT`     | Create, edit, delete, approve/reject and merge the values; images, import, export, clear.  |

- These rights **add to the workspace membership**: they count only for a user who reads the
  workspace of the list. The lists and values listed to a user do not change.
- Creating or deleting a list, and editing its ACEs, stay with the workspace editors.
- The creator of a list gets no right from it (`ownerId`): only the workspace rights and
  the ACEs count.
- In the client, these users manage the list on its own page, `/entity-lists/{id}/manage`,
  reached from the exploration of the list or the "Manage list" button of the attribute
  editor; the "Properties" button is shown to its operators.

The source of truth is `App\Security\Voter\EntityListVoter` (`EDIT` = list properties,
`EDIT_VALUES` = values). `GET /entity-lists` and `GET /entity-lists/{id}` answer the
`capabilities` of the user on each list (`edit`, `editValues`, `delete`, `editPermissions`);
a list nested in another resource (an attribute definition) comes without them. The list
ACEs follow the workspace templates and duplications.

# Instance permissions

Some Databox capabilities do not belong to any workspace: creating a workspace,
sharing one's display profiles and one's baskets, publishing one's saved searches. They are
granted at the **instance level**, to users or groups, by a **metadata** of an access
control entry (ACE) set on a whole object type: an ACE with an object type and **no
object id**.

| Capability                 | Object type    | Metadata | Effect                                                                                                         |
|----------------------------|----------------|----------|----------------------------------------------------------------------------------------------------------------|
| Create a workspace         | `workspace`    | `5`      | `POST /workspaces`; the creator owns the workspace (only admins choose another owner).                         |
| Share own profiles         | `profile`      | `6`      | The owner of a profile may make it public and edit its ACL (`share` and `editPermissions` capabilities).       |
| Share own baskets          | `basket`       | `7`      | The owner of a basket may share it and edit its ACL (`share` and `editPermissions` capabilities).              |
| Publish own saved searches | `saved_search` | `8`      | The owner of a saved search may make it Private or Public, and edit its ACL (`publish` and `editPermissions`). |

The source of truth is `App\Security\InstancePermission` and
`App\Security\Voter\DataboxExtraPermissionInterface` (API), and
`features/permissions/instancePermissions.ts` (client).

## Defaults

**Admins only.** Admins have every capability (`AdminVoter`); nobody else has any until it
is granted. Creating baskets, saved searches and display profiles stays open to every
authenticated user (including the implicit creation of the default profile,
`POST /profiles/default/items`).

What already exists is kept: profiles made public, baskets shared and searches published
before a user lost the capability stay as they are. The owner of a public profile may still
rename it or edit its items, or make it private, but not make it public again; likewise,
the owner of a Public search may still rename it, or make it Secret, but not publish it
again.

## Granting

In the Databox client, as an admin: settings menu → **Instance permissions**. One row per
user or group (plus "All users"), one column per capability.

The **Global permissions** page of the administration (EasyAdmin) only edits the mask of
these ACEs: it neither shows nor grants the capabilities, and keeps them when it writes.

The current user's capabilities are answered by `GET /capabilities`:

```json
{"createWorkspace": false, "shareProfile": true, "shareBasket": true, "publishSavedSearch": false}
```

The ACEs without object id are read and written through `/permissions/ace(s)` by admins only.

## Warning

An ACE without object id applies to **every object of the type**: any bit of its mask
(`VIEW`, `EDIT`, `SHARE`, `OWNER`…) grants the permission on **all** the baskets, saved
searches, profiles or workspaces of the instance. This is why the capabilities are
metadata, not bits. The client editor keeps the mask and the other metadata of these ACEs
untouched, and flags the rows which carry some.

The metadata checks on an object (`hasMetadata()`) also read the ACEs of its type: the
capability values must differ from the other metadata of `DataboxExtraPermissionInterface`
(`PERM_EDIT_PERMISSIONS`, `PERM_QUARANTINE`…). A capability set on the ACE of one object
is not a capability.

# Entity list permissions

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

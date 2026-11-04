# Instance permissions

Some Databox capabilities do not belong to any workspace: creating a workspace or a
display profile, sharing one's baskets, publishing one's saved searches. They are
granted at the **instance level**, to users or groups, by a **metadata** of an access
control entry (ACE) set on a whole object type: an ACE with an object type and **no
object id**.

| Capability                 | Object type    | Metadata | Effect                                                                                                         |
|----------------------------|----------------|----------|----------------------------------------------------------------------------------------------------------------|
| Create a workspace         | `workspace`    | `5`      | `POST /workspaces`; the creator owns the workspace (only admins choose another owner).                         |
| Create a profile           | `profile`      | `6`      | `POST /profiles`, and the implicit creation of the default profile (`POST /profiles/default/items`).           |
| Share own baskets          | `basket`       | `7`      | The owner of a basket may share it and edit its ACL (`share` and `editPermissions` capabilities).              |
| Publish own saved searches | `saved_search` | `8`      | The owner of a saved search may make it Private or Public, and edit its ACL (`publish` and `editPermissions`). |

The source of truth is `App\Security\InstancePermission` and
`App\Security\Voter\DataboxExtraPermissionInterface` (API), and
`features/permissions/instancePermissions.ts` (client).

## Defaults

**Admins only.** Admins have every capability (`AdminVoter`); nobody else has any until it
is granted. Creating baskets and saved searches stays open to every authenticated user.

What already exists is kept: baskets shared and searches published before a user lost the
capability stay as they are. The owner of a Public search may still rename it, or make it
Secret, but not publish it again.

## Granting

In the Databox client, as an admin: settings menu → **Instance permissions**. One row per
user or group (plus "All users"), one column per capability.

The **Global permissions** page of the administration (EasyAdmin) only edits the mask of
these ACEs: it neither shows nor grants the capabilities, and keeps them when it writes.

The current user's capabilities are answered by `GET /capabilities`:

```json
{"createWorkspace": false, "createProfile": true, "shareBasket": true, "publishSavedSearch": false}
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

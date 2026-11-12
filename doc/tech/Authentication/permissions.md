# Permissions

By default, Phrasea offers 2 privileged roles:

- **Super Admin** which can do anything
- **Admin** which can do some things on some services


To be an **Admin**, you can:
- be a user with the `admin` role (defined in Keycloak)
- be a user with the `<service>-admin` role (defined in Keycloak). This role will limit the user to be an admin only in this `<service>`

In Databox, some capabilities are granted outside any workspace (creating a workspace or a profile, sharing baskets, publishing saved searches): see [Instance permissions](../Databox/permissions.md).

## Keycloak groups

Users can manage the Keycloak groups without the Keycloak console, from every client (Databox, Expose, Uploader, Dashboard): **Manage groups…** in the user menu creates, renames and deletes groups (top level groups only, subgroups are not created) and adds or removes their members. In Databox, the group picker (permissions, filter rules, email reports…) also offers **Create group "…"** with the typed name.

- The right is the Keycloak one: the `manage-users` role of the `realm-management` client. The entry is shown, and the API (`/permissions/groups…` of `auth-bundle`, mounted by the Databox, Expose and Uploader APIs; the Dashboard goes through the Databox API) accepts the calls, only when the token of the user carries it. `realm-admin` includes it.
- The realm role **`group-admin`** composes `view-users` and `manage-users` of `realm-management` (set by the configurator at install, and again by its `synchronize` step at each upgrade): grant it to let a user who is not an admin manage the groups. Note that `manage-users` also lets that user edit the users in the Keycloak console. Every `admin` gets it, `admin` being composed of `group-admin`.
- The API calls the Keycloak admin API with **the token of the user**, never with a service account: Keycloak applies the user's own roles. While an admin impersonates another user, nothing is allowed.
- A membership change applies to a signed-in user once their token is refreshed (the `groups` claim of the token is read at each request), within the lifetime of the access token.
- Deleting a group does not remove the permissions granted to it in Databox: they no longer apply to anybody, as after a deletion from the Keycloak console.

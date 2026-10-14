# Permissions

By default, Phrasea offers 2 privileged roles:

- **Super Admin** which can do anything
- **Admin** which can do some things on some services


To be an **Admin**, you can:
- be a user with the `admin` role (defined in Keycloak)
- be a user with the `<service>-admin` role (defined in Keycloak). This role will limit the user to be an admin only in this `<service>`

## Application roles

Besides the admin roles, the configurator creates realm roles that unlock a single feature of an application. They are composite children of the `<service>-admin` role (so admins have them) and can be granted to any user from Keycloak.

| Role | Service | Grants |
| --- | --- | --- |
| `edit_faces` | databox | Access to the Faces page and naming the faces detected by the face recognition integration, within the assets the user can read |

An application maps a realm role to a Symfony role through the `alchemy_auth.role_mapping` configuration of its API (e.g. `edit_faces: ROLE_EDIT_FACES` in `databox/api/config/packages/alchemy_auth.yaml`); unmapped roles never reach the application.

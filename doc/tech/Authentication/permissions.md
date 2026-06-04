# Permissions

By default, Phrasea offers 2 privileged roles:

- **Super Admin** which can do anything
- **Admin** which can do some things on some services


To be an **Admin**, you can:
- be a user with the `admin` role (defined in Keycloak)
- be a user with the `<service>-admin` role (defined in Keycloak). This role will limit the user to be an admin only in this `<service>`

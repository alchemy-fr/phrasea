---
status: pending review
title: Keycloak Authentication Integration in Phrasea
---

# Keycloak Authentication Integration in Phrasea

> **Status:** _Work In Progress_  
> This documentation page is currently being drafted and may be updated frequently.

## Overview

Phrasea relies on [Keycloak](https://www.keycloak.org/documentation) with [OpenID Connect](https://openid.net/connect/) for authentication management across its various services.  
During installation, a dedicated realm named **Phrasea** is created, containing individual clients for each Phrasea service.

### What Keycloak Provides

Keycloak handles users and groups, Single Sign-On across the Phrasea applications, federation with external identity providers (Active Directory, SAML, OpenID Connect), multi-factor authentication, session lifetime and revocation, and login auditing. See the [Keycloak official documentation](https://www.keycloak.org/documentation) for details on these features.

### Configuration

* When deploying the stack, Phrasea runs a configurator that sets up a realm and clients dedicated to this installation. These clients are used by the various Phrasea services.  
* A root Keycloak account and a master admin account for the application are also initialized.  
* The password of this admin account (`DEFAULT_ADMIN_PASSWORD`) is temporary: it must be changed at first login, unless `KEYCLOAK_ADMIN_PASSWORD_IS_DEFINITIVE` is enabled.  
* Interconnections with the client's IdP are declared manually at a later stage.

## Roles and Scopes

You can start by reading this explanation of the difference between Scopes and Roles [here](./keycloak-scopes-vs-roles).

### Roles

Roles in Phrasea are used to define sets of permissions that can be assigned to users or service accounts. These roles are configured within the Phrasea realm in Keycloak and are referenced throughout the application to control access to specific features and modules.

- **Application Roles:**  
  Application roles are mapped to the different modules and functionalities within Phrasea. For example, roles may include `admin`, `editor`, `viewer`, or more granular roles specific to certain modules.
- **Role Assignment:**  
  Roles are assigned to users or clients during provisioning, either manually via the Keycloak admin console or automatically through migration scripts and configuration commands (see files in `src/Configurator/Vendor/Keycloak/` and migration logic in `src/Configurator/Vendor/Keycloak/Migrations/`).

### Scopes

Scopes in Phrasea are used to further restrict or define the context in which roles and permissions apply. Scopes are typically associated with OAuth2 and OpenID Connect flows, allowing clients to request specific levels of access.

- **Custom Scopes:**  
  Custom scopes can be defined for each client in the Phrasea realm, enabling fine-grained control over what data and actions are accessible during authentication and authorization.
- **Usage in Clients:**  
  Each client (representing a Phrasea service) can request specific scopes during authentication, ensuring that only the necessary permissions are granted for its operation.

#### Common Scopes

Scopes common to all services:

| Scope             | Description                                                                                   |
|-------------------|-----------------------------------------------------------------------------------------------|
| acr               | OpenID Connect scope for add acr (authentication context class reference) to the token        |
| address           | OpenID Connect built-in scope: address                                                        |
| basic             | OpenID Connect scope for add all basic claims to the token                                    |
| email             | OpenID Connect built-in scope: email                                                          |
| microprofile-jwt  | Microprofile - JWT built-in scope                                                             |
| offline_access    | OpenID Connect built-in scope: offline_access                                                 |
| openid            | —                                                                                             |
| phone             | OpenID Connect built-in scope: phone                                                          |
| profile           | OpenID Connect built-in scope: profile                                                        |
| roles             | OpenID Connect scope for add user roles to the access token                                   |
| service_account   | Specific scope for a client enabled for service accounts                                      |
| web-origins       | OpenID Connect scope for add allowed web origins to the access token                          |

#### Databox Scopes

Databox scopes follow the `<resource>:<action>` pattern.

Actions:

| Action     |
|------------|
| `create`   |
| `list`     |
| `read`     |
| `edit`     |
| `delete`   |
| `operator` |
| `owner`    |

Resources:

| Resource               |
|------------------------|
| `asset-data-template`  |
| `asset`                |
| `attribute-definition` |
| `attribute-entity`     |
| `attribute-list`       |
| `attribute-policy`     |
| `basket`               |
| `collection`           |
| `entity-list`          |
| `integration`          |
| `rendition-definition` |
| `rendition-policy`     |
| `rendition-rule`       |
| `workspace`            |

For example, `asset:read` allows reading assets and `collection:edit` allows editing collections.

#### Expose Scopes

| Scope   | Description       |
|---------|-------------------|
| publish | publish in Expose |

#### Uploader Scopes

| Scope       | Description             |
|-------------|-------------------------|
| commit:list | commit:list in Uploader |

## Implementation Details

- **Environment Variables:**  
  The integration relies on [environment variables](../../tech/Configuration/env_var#authentication-and-identity-provider-settings) for Keycloak endpoints, client secrets and realm configuration.

## References

- [Keycloak Official Documentation](https://www.keycloak.org/documentation)

---

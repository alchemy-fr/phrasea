---
status: WIP
title: Keycloak Authentication Integration in Phrasea
---

> **Status:** _Work In Progress_  
> This documentation page is currently being drafted and may be updated frequently.


# Keycloak Authentication Integration in Phrasea

> **Status:** _Work In Progress_  
> This documentation page is currently being drafted and may be updated frequently.

## Overview

Phrasea relies on [Keycloak](https://www.keycloak.org/documentation) with [OpenID Connect](https://openid.net/connect/) for authentication management across its various  services. 
During installation, a dedicated realm named **Phrasea** is created, containing individual clients for each Phrasea services.

### Authentication and Authorization

* Keycloak supports user authentication via standard protocols like OAuth2, OpenID Connect, and SAML. This facilitates integration with web, mobile applications, and APIs.  
* Keycloak allows role-based authorization management, offering granular user permission management, which can be aligned with roles defined in the Phrasea database (administrators, editors, viewers, etc.).

### User and Group Management

* Keycloak manages users and groups, allowing segmentation based on needs. Each user can be assigned to one or more groups, and permissions can be attributed to these groups.  
* User information (such as email addresses, usernames, and roles) can be synchronized with Phrasea, ensuring a smooth and centralized user experience.

### Single Sign-On (SSO)

* Keycloak provides SSO, allowing users to log in once to access all Phrasea-related applications, both internal and external. This improves user experience and simplifies session management.  
* User sessions can be managed centrally, and Keycloak also allows session revocation if needed (e.g., when a user leaves the organization).

### Federation and Integration with External IdPs

* Keycloak can connect to external identity providers (IdP) such as Microsoft Active Directory, or other SAML and OpenID Connect compatible providers. This allows using existing identities without creating new accounts for Phrasea.  
* Federation simplifies authentication for organizations already using other IAM systems.

### Advanced Security Features

* Multi-factor authentication (MFA): Keycloak supports two-factor authentication, enhancing security by requiring a second verification (such as a mobile app or token).  
* Session and token management: Keycloak allows configuring session and token lifetimes and can force token revocation in case of compromise.  
* Audit and Logs: Keycloak provides logging capabilities to track logins, authentication failures, and other security events, which is essential for compliance requirements.

### Configuration

* When deploying the stack, Phrasea runs a configurator that sets up a realm and clients dedicated to this installation. These clients are used by the various Phrasea services.  
* A root Keycloak account and a master admin account for the application are also initialized.  
* Interconnections with the client's IdP are declared manually at a later stage.  
* Phrasea can be configured with OAuth2 or OpenID Connect for user authentication, centralizing identity management and facilitating integration with third-party providers.


## Roles and Scopes

You can start by reading this explanation of the difference between Scopes and Roles [here](/user/keycloak/keycloak-scopes-vs-roles).

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

common scope to all service

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

| Scope                                  | Description                                      |
|----------------------------------------|--------------------------------------------------|
| asset-data-template:create             | asset-data-template:create in Databox            |
| asset-data-template:delete             | asset-data-template:delete in Databox            |
| asset-data-template:edit               | asset-data-template:edit in Databox              |
| asset-data-template:list               | asset-data-template:list in Databox              |
| asset-data-template:operator           | asset-data-template:operator in Databox          |
| asset-data-template:owner              | asset-data-template:owner in Databox             |
| asset-data-template:read               | asset-data-template:read in Databox              |
| asset:create                           | asset:create in Databox                          |
| asset:delete                           | asset:delete in Databox                          |
| asset:edit                             | asset:edit in Databox                            |
| asset:list                             | asset:list in Databox                            |
| asset:operator                         | asset:operator in Databox                        |
| asset:owner                            | asset:owner in Databox                           |
| asset:read                             | asset:read in Databox                            |
| attribute-definition:create            | attribute-definition:create in Databox           |
| attribute-definition:delete            | attribute-definition:delete in Databox           |
| attribute-definition:edit              | attribute-definition:edit in Databox             |
| attribute-definition:list              | attribute-definition:list in Databox             |
| attribute-definition:operator          | attribute-definition:operator in Databox         |
| attribute-definition:owner             | attribute-definition:owner in Databox            |
| attribute-definition:read              | attribute-definition:read in Databox             |
| attribute-entity:create                | attribute-entity:create in Databox               |
| attribute-entity:delete                | attribute-entity:delete in Databox               |
| attribute-entity:edit                  | attribute-entity:edit in Databox                 |
| attribute-entity:list                  | attribute-entity:list in Databox                 |
| attribute-entity:operator              | attribute-entity:operator in Databox             |
| attribute-entity:owner                 | attribute-entity:owner in Databox                |
| attribute-entity:read                  | attribute-entity:read in Databox                 |
| attribute-list:create                  | attribute-list:create in Databox                 |
| attribute-list:delete                  | attribute-list:delete in Databox                 |
| attribute-list:edit                    | attribute-list:edit in Databox                   |
| attribute-list:list                    | attribute-list:list in Databox                   |
| attribute-list:operator                | attribute-list:operator in Databox               |
| attribute-list:owner                   | attribute-list:owner in Databox                  |
| attribute-list:read                    | attribute-list:read in Databox                   |
| attribute-policy:create                | attribute-policy:create in Databox               |
| attribute-policy:delete                | attribute-policy:delete in Databox               |
| attribute-policy:edit                  | attribute-policy:edit in Databox                 |
| attribute-policy:list                  | attribute-policy:list in Databox                 |
| attribute-policy:operator              | attribute-policy:operator in Databox             |
| attribute-policy:owner                 | attribute-policy:owner in Databox                |
| attribute-policy:read                  | attribute-policy:read in Databox                 |
| basket:create                          | basket:create in Databox                         |
| basket:delete                          | basket:delete in Databox                         |
| basket:edit                            | basket:edit in Databox                           |
| basket:list                            | basket:list in Databox                           |
| basket:operator                        | basket:operator in Databox                       |
| basket:owner                           | basket:owner in Databox                          |
| basket:read                            | basket:read in Databox                           |
| collection:create                      | collection:create in Databox                     |
| collection:delete                      | collection:delete in Databox                     |
| collection:edit                        | collection:edit in Databox                       |
| collection:list                        | collection:list in Databox                       |
| collection:operator                    | collection:operator in Databox                   |
| collection:owner                       | collection:owner in Databox                      |
| collection:read                        | collection:read in Databox                       |
| email                                  | OpenID Connect built-in scope: email             |
| entity-list:create                     | entity-list:create in Databox                    |
| entity-list:delete                     | entity-list:delete in Databox                    |
| entity-list:edit                       | entity-list:edit in Databox                      |
| entity-list:list                       | entity-list:list in Databox                      |
| entity-list:operator                   | entity-list:operator in Databox                  |
| entity-list:owner                      | entity-list:owner in Databox                     |
| entity-list:read                       | entity-list:read in Databox                      |
| integration:create                     | integration:create in Databox                    |
| integration:delete                     | integration:delete in Databox                    |
| integration:edit                       | integration:edit in Databox                      |
| integration:list                       | integration:list in Databox                      |
| integration:operator                   | integration:operator in Databox                  |
| integration:owner                      | integration:owner in Databox                     |
| integration:read                       | integration:read in Databox                      |
| rendition-definition:create            | rendition-definition:create in Databox           |
| rendition-definition:delete            | rendition-definition:delete in Databox           |
| rendition-definition:edit              | rendition-definition:edit in Databox             |
| rendition-definition:list              | rendition-definition:list in Databox             |
| rendition-definition:operator          | rendition-definition:operator in Databox         |
| rendition-definition:owner             | rendition-definition:owner in Databox            |
| rendition-definition:read              | rendition-definition:read in Databox             |
| rendition-policy:create                | rendition-policy:create in Databox               |
| rendition-policy:delete                | rendition-policy:delete in Databox               |
| rendition-policy:edit                  | rendition-policy:edit in Databox                 |
| rendition-policy:list                  | rendition-policy:list in Databox                 |
| rendition-policy:operator              | rendition-policy:operator in Databox             |
| rendition-policy:owner                 | rendition-policy:owner in Databox                |
| rendition-policy:read                  | rendition-policy:read in Databox                 |
| rendition-rule:create                  | rendition-rule:create in Databox                 |
| rendition-rule:delete                  | rendition-rule:delete in Databox                 |
| rendition-rule:edit                    | rendition-rule:edit in Databox                   |
| rendition-rule:list                    | rendition-rule:list in Databox                   |
| rendition-rule:operator                | rendition-rule:operator in Databox               |
| rendition-rule:owner                   | rendition-rule:owner in Databox                  |
| rendition-rule:read                    | rendition-rule:read in Databox                   |
| rendition:create                       | rendition:create in Databox                      |
| rendition:delete                       | rendition:delete in Databox                      |
| rendition:edit                         | rendition:edit in Databox                        |
| rendition:list                         | rendition:list in Databox                        |
| rendition:operator                     | rendition:operator in Databox                    |
| rendition:owner                        | rendition:owner in Databox                       |
| rendition:read                         | rendition:read in Databox                        |
| workspace:create                       | workspace:create in Databox                      |
| workspace:delete                       | workspace:delete in Databox                      |
| workspace:edit                         | workspace:edit in Databox                        |
| workspace:list                         | workspace:list in Databox                        |
| workspace:operator                     | workspace:operator in Databox                    |
| workspace:owner                        | workspace:owner in Databox                       |
| workspace:read                         | workspace:read in Databox                        |

#### Expose Scopes

| Scope   | Description       |
|---------|-------------------|
| publish	| publish in Expose |

#### Uploader Scopes

| Scope       | Description             |
|-------------|-------------------------|
| commit:list	| commit:list in Uploader |

Optionnel
commit:list in Uploader

## Implementation Details

- **Environment Variables:**  
  The integration may rely on [environment variables](/tech/Configuration/env_var#authentication-and-identity-provider-settings) for Keycloak endpoints, client secrets, and realm configuration documentation generator.

## References

- [Keycloak Official Documentation](https://www.keycloak.org/documentation)

---
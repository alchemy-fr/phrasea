---
status: WIP
title: Intégration de l’authentification Keycloak dans Phrasea
---

> **Statut :** _Travail en cours_  
> Cette page de documentation est en cours de rédaction et peut être fréquemment mise à jour.

# Intégration de l’authentification Keycloak dans Phrasea

> **Statut :** _Travail en cours_  
> Cette page de documentation est en cours de rédaction et peut être fréquemment mise à jour.

## Vue d’ensemble

Phrasea s’appuie sur [Keycloak](https://www.keycloak.org/documentation) avec [OpenID Connect](https://openid.net/connect/) pour la gestion de l’authentification sur ses différents services.  
Lors de l’installation, un realm dédié nommé **Phrasea** est créé, contenant des clients individuels pour chaque service Phrasea.

### Authentification et autorisation

* Keycloak prend en charge l’authentification des utilisateurs via des protocoles standards comme OAuth2, OpenID Connect et SAML. Cela facilite l’intégration avec les applications web, mobiles et les API.  
* Keycloak permet une gestion des autorisations basée sur les rôles, offrant une gestion fine des droits utilisateurs, alignée sur les rôles définis dans la base Phrasea (administrateurs, éditeurs, lecteurs, etc.).

### Gestion des utilisateurs et des groupes

* Keycloak gère les utilisateurs et les groupes, permettant une segmentation selon les besoins. Chaque utilisateur peut être rattaché à un ou plusieurs groupes, et les droits peuvent être attribués à ces groupes.  
* Les informations utilisateurs (email, nom d’utilisateur, rôles) peuvent être synchronisées avec Phrasea, assurant une expérience fluide et centralisée.

### Single Sign-On (SSO)

* Keycloak propose le SSO, permettant aux utilisateurs de se connecter une seule fois pour accéder à toutes les applications liées à Phrasea, internes ou externes. Cela améliore l’expérience utilisateur et simplifie la gestion des sessions.  
* Les sessions utilisateurs peuvent être gérées de façon centralisée, et Keycloak permet aussi la révocation des sessions si besoin (ex : départ d’un utilisateur).

### Fédération et intégration avec des IdP externes

* Keycloak peut se connecter à des fournisseurs d’identité externes (IdP) comme Microsoft Active Directory, ou tout autre fournisseur compatible SAML ou OpenID Connect. Cela permet d’utiliser les identités existantes sans créer de nouveaux comptes pour Phrasea.  
* La fédération simplifie l’authentification pour les organisations utilisant déjà d’autres systèmes IAM.

### Fonctionnalités de sécurité avancées

* Authentification multi-facteurs (MFA) : Keycloak prend en charge l’authentification à deux facteurs, renforçant la sécurité en demandant une vérification supplémentaire (application mobile, token, etc.).  
* Gestion des sessions et des tokens : Keycloak permet de configurer la durée de vie des sessions et des tokens, et peut forcer la révocation en cas de compromission.  
* Audit et logs : Keycloak fournit des capacités de journalisation pour suivre les connexions, échecs d’authentification et autres événements de sécurité, essentiel pour la conformité.

### Configuration

* Lors du déploiement de la stack, Phrasea exécute un configurateur qui initialise un realm et des clients dédiés à cette installation. Ces clients sont utilisés par les différents services Phrasea.  
* Un compte root Keycloak et un compte administrateur principal pour l’application sont également initialisés.  
* Les interconnexions avec l’IdP du client sont déclarées manuellement ultérieurement.  
* Phrasea peut être configuré avec OAuth2 ou OpenID Connect pour l’authentification des utilisateurs, centralisant la gestion des identités et facilitant l’intégration avec des fournisseurs tiers.

## Rôles et Scopes

Vous pouvez commencer par lire cette explication sur la différence entre Scopes et Rôles [ici](/user/keycloak/keycloak-scopes-vs-roles).

### Rôles

Les rôles dans Phrasea servent à définir des ensembles de permissions qui peuvent être attribués aux utilisateurs ou aux comptes de service.  
Ces rôles sont configurés dans le realm Phrasea de Keycloak et sont référencés dans l’application pour contrôler l’accès à certaines fonctionnalités et modules.

- **Rôles applicatifs :**  
  Les rôles applicatifs sont associés aux différents modules et fonctionnalités de Phrasea. Par exemple, les rôles peuvent inclure `admin`, `editor`, `viewer` ou des rôles plus granulaires spécifiques à certains modules.
- **Attribution des rôles :**  
  Les rôles sont attribués aux utilisateurs ou aux clients lors du provisioning, soit manuellement via la console d’administration Keycloak, soit automatiquement via des scripts de migration et des commandes de configuration (voir les fichiers dans `src/Configurator/Vendor/Keycloak/` et la logique de migration dans `src/Configurator/Vendor/Keycloak/Migrations/`).

### Scopes

Les scopes dans Phrasea servent à restreindre ou à définir le contexte dans lequel les rôles et permissions s’appliquent.  
Les scopes sont généralement associés aux flux OAuth2 et OpenID Connect, permettant aux clients de demander des niveaux d’accès spécifiques.

- **Scopes personnalisés :**  
  Des scopes personnalisés peuvent être définis pour chaque client dans le realm Phrasea, permettant un contrôle fin sur les données et actions accessibles lors de l’authentification et de l’autorisation.
- **Utilisation dans les clients :**  
  Chaque client (représentant un service Phrasea) peut demander des scopes spécifiques lors de l’authentification, garantissant que seules les permissions nécessaires sont accordées pour son fonctionnement.

#### Scopes communs

Scope commun à tous les services

| Scope             | Description                                                                                   |
|-------------------|-----------------------------------------------------------------------------------------------|
| acr               | Scope OpenID Connect pour ajouter acr (authentication context class reference) au token        |
| address           | Scope OpenID Connect intégré : adresse                                                        |
| basic             | Scope OpenID Connect pour ajouter toutes les claims de base au token                          |
| email             | Scope OpenID Connect intégré : email                                                          |
| microprofile-jwt  | Scope intégré Microprofile - JWT                                                              |
| offline_access    | Scope OpenID Connect intégré : offline_access                                                 |
| openid            | —                                                                                             |
| phone             | Scope OpenID Connect intégré : téléphone                                                      |
| profile           | Scope OpenID Connect intégré : profil                                                         |
| roles             | Scope OpenID Connect pour ajouter les rôles utilisateur au token d’accès                      |
| service_account   | Scope spécifique pour un client activé pour les comptes de service                            |
| web-origins       | Scope OpenID Connect pour ajouter les origines web autorisées au token d’accès                |

#### Scopes Databox

| Scope                                  | Description                                      |
|----------------------------------------|--------------------------------------------------|
| asset-data-template:create             | asset-data-template:create dans Databox          |
| asset-data-template:delete             | asset-data-template:delete dans Databox          |
| asset-data-template:edit               | asset-data-template:edit dans Databox            |
| asset-data-template:list               | asset-data-template:list dans Databox            |
| asset-data-template:operator           | asset-data-template:operator dans Databox        |
| asset-data-template:owner              | asset-data-template:owner dans Databox           |
| asset-data-template:read               | asset-data-template:read dans Databox            |
| asset:create                           | asset:create dans Databox                        |
| asset:delete                           | asset:delete dans Databox                        |
| asset:edit                             | asset:edit dans Databox                          |
| asset:list                             | asset:list dans Databox                          |
| asset:operator                         | asset:operator dans Databox                      |
| asset:owner                            | asset:owner dans Databox                         |
| asset:read                             | asset:read dans Databox                          |
| attribute-definition:create            | attribute-definition:create dans Databox         |
| attribute-definition:delete            | attribute-definition:delete dans Databox         |
| attribute-definition:edit              | attribute-definition:edit dans Databox           |
| attribute-definition:list              | attribute-definition:list dans Databox           |
| attribute-definition:operator          | attribute-definition:operator dans Databox       |
| attribute-definition:owner             | attribute-definition:owner dans Databox          |
| attribute-definition:read              | attribute-definition:read dans Databox           |
| attribute-entity:create                | attribute-entity:create dans Databox             |
| attribute-entity:delete                | attribute-entity:delete dans Databox             |
| attribute-entity:edit                  | attribute-entity:edit dans Databox               |
| attribute-entity:list                  | attribute-entity:list dans Databox               |
| attribute-entity:operator              | attribute-entity:operator dans Databox           |
| attribute-entity:owner                 | attribute-entity:owner dans Databox              |
| attribute-entity:read                  | attribute-entity:read dans Databox               |
| attribute-list:create                  | attribute-list:create dans Databox               |
| attribute-list:delete                  | attribute-list:delete dans Databox               |
| attribute-list:edit                    | attribute-list:edit dans Databox                 |
| attribute-list:list                    | attribute-list:list dans Databox                 |
| attribute-list:operator                | attribute-list:operator dans Databox             |
| attribute-list:owner                   | attribute-list:owner dans Databox                |
| attribute-list:read                    | attribute-list:read dans Databox                 |
| attribute-policy:create                | attribute-policy:create dans Databox             |
| attribute-policy:delete                | attribute-policy:delete dans Databox             |
| attribute-policy:edit                  | attribute-policy:edit dans Databox               |
| attribute-policy:list                  | attribute-policy:list dans Databox               |
| attribute-policy:operator              | attribute-policy:operator dans Databox           |
| attribute-policy:owner                 | attribute-policy:owner dans Databox              |
| attribute-policy:read                  | attribute-policy:read dans Databox               |
| basket:create                          | basket:create dans Databox                       |
| basket:delete                          | basket:delete dans Databox                       |
| basket:edit                            | basket:edit dans Databox                         |
| basket:list                            | basket:list dans Databox                         |
| basket:operator                        | basket:operator dans Databox                     |
| basket:owner                           | basket:owner dans Databox                        |
| basket:read                            | basket:read dans Databox                         |
| collection:create                      | collection:create dans Databox                   |
| collection:delete                      | collection:delete dans Databox                   |
| collection:edit                        | collection:edit dans Databox                     |
| collection:list                        | collection:list dans Databox                     |
| collection:operator                    | collection:operator dans Databox                 |
| collection:owner                       | collection:owner dans Databox                    |
| collection:read                        | collection:read dans Databox                     |
| email                                  | Scope OpenID Connect intégré : email             |
| entity-list:create                     | entity-list:create dans Databox                  |
| entity-list:delete                     | entity-list:delete dans Databox                  |
| entity-list:edit                       | entity-list:edit dans Databox                    |
| entity-list:list                       | entity-list:list dans Databox                    |
| entity-list:operator                   | entity-list:operator dans Databox                |
| entity-list:owner                      | entity-list:owner dans Databox                   |
| entity-list:read                       | entity-list:read dans Databox                    |
| integration:create                     | integration:create dans Databox                  |
| integration:delete                     | integration:delete dans Databox                  |
| integration:edit                       | integration:edit dans Databox                    |
| integration:list                       | integration:list dans Databox                    |
| integration:operator                   | integration:operator dans Databox                |
| integration:owner                      | integration:owner dans Databox                   |
| integration:read                       | integration:read dans Databox                    |
| rendition-definition:create            | rendition-definition:create dans Databox         |
| rendition-definition:delete            | rendition-definition:delete dans Databox         |
| rendition-definition:edit              | rendition-definition:edit dans Databox           |
| rendition-definition:list              | rendition-definition:list dans Databox           |
| rendition-definition:operator          | rendition-definition:operator dans Databox       |
| rendition-definition:owner             | rendition-definition:owner dans Databox          |
| rendition-definition:read              | rendition-definition:read dans Databox           |
| rendition-policy:create                | rendition-policy:create dans Databox             |
| rendition-policy:delete                | rendition-policy:delete dans Databox             |
| rendition-policy:edit                  | rendition-policy:edit dans Databox               |
| rendition-policy:list                  | rendition-policy:list dans Databox               |
| rendition-policy:operator              | rendition-policy:operator dans Databox           |
| rendition-policy:owner                 | rendition-policy:owner dans Databox              |
| rendition-policy:read                  | rendition-policy:read dans Databox               |
| rendition-rule:create                  | rendition-rule:create dans Databox               |
| rendition-rule:delete                  | rendition-rule:delete dans Databox               |
| rendition-rule:edit                    | rendition-rule:edit dans Databox                 |
| rendition-rule:list                    | rendition-rule:list dans Databox                 |
| rendition-rule:operator                | rendition-rule:operator dans Databox             |
| rendition-rule:owner                   | rendition-rule:owner dans Databox                |
| rendition-rule:read                    | rendition-rule:read dans Databox                 |
| rendition:create                       | rendition:create dans Databox                    |
| rendition:delete                       | rendition:delete dans Databox                    |
| rendition:edit                         | rendition:edit dans Databox                      |
| rendition:list                         | rendition:list dans Databox                      |
| rendition:operator                     | rendition:operator dans Databox                  |
| rendition:owner                        | rendition:owner dans Databox                     |
| rendition:read                         | rendition:read dans Databox                      |
| workspace:create                       | workspace:create dans Databox                    |
| workspace:delete                       | workspace:delete dans Databox                    |
| workspace:edit                         | workspace:edit dans Databox                      |
| workspace:list                         | workspace:list dans Databox                      |
| workspace:operator                     | workspace:operator dans Databox                  |
| workspace:owner                        | workspace:owner dans Databox                     |
| workspace:read                         | workspace:read dans Databox                      |

#### Scopes Expose

| Scope   | Description       |
|---------|-------------------|
| publish	| publish dans Expose |

#### Scopes Uploader

| Scope       | Description             |
|-------------|-------------------------|
| commit:list	| commit:list dans Uploader |

## Détails d’implémentation

- **Variables d’environnement :**  
  L’intégration peut s’appuyer sur des [variables d’environnement](/tech/Configuration/env_var#authentication-and-identity-provider-settings) pour les endpoints Keycloak, les secrets clients et la configuration du realm pour le générateur de documentation.

## Références

- [Documentation officielle Keycloak](https://www.keycloak.org/documentation)

---
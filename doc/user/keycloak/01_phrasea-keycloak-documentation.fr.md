---
status: en attente de relecture
title: Intégration de l’authentification Keycloak dans Phrasea
---

# Intégration de l’authentification Keycloak dans Phrasea

> **Statut :** _Travail en cours_  
> Cette page de documentation est en cours de rédaction et peut être fréquemment mise à jour.

## Vue d’ensemble

Phrasea s’appuie sur [Keycloak](https://www.keycloak.org/documentation) avec [OpenID Connect](https://openid.net/connect/) pour la gestion de l’authentification sur ses différents services.  
Lors de l’installation, un realm dédié nommé **Phrasea** est créé, contenant des clients individuels pour chaque service Phrasea.

### Ce qu’apporte Keycloak

Keycloak gère les utilisateurs et les groupes, le Single Sign-On entre les applications Phrasea, la fédération avec des fournisseurs d’identité externes (Active Directory, SAML, OpenID Connect), l’authentification multi-facteurs, la durée de vie et la révocation des sessions, ainsi que l’audit des connexions. Le détail de ces fonctionnalités se trouve dans la [documentation officielle Keycloak](https://www.keycloak.org/documentation).

### Configuration

* Lors du déploiement de la stack, Phrasea exécute un configurateur qui initialise un realm et des clients dédiés à cette installation. Ces clients sont utilisés par les différents services Phrasea.  
* Un compte root Keycloak et un compte administrateur principal pour l’application sont également initialisés.  
* Le mot de passe de ce compte administrateur (`DEFAULT_ADMIN_PASSWORD`) est temporaire : il doit être changé à la première connexion, sauf si `KEYCLOAK_ADMIN_PASSWORD_IS_DEFINITIVE` est activé.  
* Les interconnexions avec l’IdP du client sont déclarées manuellement ultérieurement.

## Rôles et Scopes

Vous pouvez commencer par lire cette explication sur la différence entre Scopes et Rôles [ici](./keycloak-scopes-vs-roles).

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

Scopes communs à tous les services :

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

Les scopes Databox suivent la forme `<ressource>:<action>`.

Actions :

| Action     |
|------------|
| `create`   |
| `list`     |
| `read`     |
| `edit`     |
| `delete`   |
| `operator` |
| `owner`    |

Ressources :

| Ressource              |
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

Par exemple, `asset:read` autorise la lecture des assets et `collection:edit` la modification des collections.

#### Scopes Expose

| Scope   | Description         |
|---------|---------------------|
| publish | publish dans Expose |

#### Scopes Uploader

| Scope       | Description               |
|-------------|---------------------------|
| commit:list | commit:list dans Uploader |

## Détails d’implémentation

- **Variables d’environnement :**  
  L’intégration s’appuie sur des [variables d’environnement](../../tech/Configuration/env_var#authentication-and-identity-provider-settings) pour les endpoints Keycloak, les secrets des clients et la configuration du realm.

## Références

- [Documentation officielle Keycloak](https://www.keycloak.org/documentation)

---

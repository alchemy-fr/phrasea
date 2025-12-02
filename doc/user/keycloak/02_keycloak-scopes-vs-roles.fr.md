---
status: En attente de relecture
title: Scopes vs Rôles
---

> **Statut :** _Travail en cours_  
> Cette page de documentation est en cours de rédaction et peut être fréquemment mise à jour.

# Keycloak : Rôles vs Scopes

## 1. Analogie dans la vie réelle

### Rôle
Imagine que tu travailles dans une entreprise :
- Un **rôle** correspond à un **poste** (ex : « Manager », « Développeur », « Comptable »).
  - Un rôle définit **ce que tu as le droit de faire** selon ta fonction.
  - Exemple : Un « Manager » peut valider les demandes de congés, un « Développeur » peut accéder au code source.

### Scope
Maintenant, imagine que tu veux accéder à un service externe (ex : une application de notes de frais) :
- Un **scope** correspond à **ce que l’application est autorisée à savoir ou à faire** avec ton identité.
  - Exemple : Quand tu te connectes à l’application Databox avec ton compte, tu peux autoriser l’application à :
    - **« Lire ton profil »** (scope : `profile`)
    - **« Créer des assets »** (scope : `asset:write`)
  - Le scope ne définit pas **qui tu es**, mais **ce que l’application peut faire avec tes données**.

---

## 2. Différences techniques dans Keycloak

| **Aspect**      | **Rôle**                                      | **Scope**                                      |
|-----------------|-----------------------------------------------|------------------------------------------------|
| **Définition**  | Assigné à un utilisateur ou à un groupe.      | Définit les **permissions** demandées par une application. |
| **Usage**       | Contrôle l’accès aux ressources **internes** (ex : accès au dashboard admin). | Contrôle les **autorisations déléguées** à une application tierce (ex : accès en lecture/écriture). |
| **Exemple**     | `admin`, `editor`, `user`                     | `openid`, `profile`, `email`, `asset:read`     |
| **Portée**      | Lié à l’identité de l’utilisateur.            | Lié à la **demande d’accès** d’une application.|

---

## 3. Les rôles et scopes sont-ils spécifiques à Keycloak ?
Non, ces concepts existent dans la plupart des **protocoles d’autorisation modernes** (OAuth 2.0, OpenID Connect).

### Où les trouve-t-on ?
- **OAuth 2.0/OpenID Connect** : Les scopes sont standardisés (ex : `openid`, `profile`, `email`).
- **Autres fournisseurs** :
  - **Auth0** : Utilise rôles et scopes de la même manière.
  - **Okta** : Similaire, avec des rôles pour les permissions internes et des scopes pour les autorisations déléguées.
  - **Azure AD** : Parle de « application roles » et utilise des scopes pour les permissions d’API.
  - **Google OAuth** : Utilise les scopes pour limiter l’accès aux données (ex : `https://www.googleapis.com/auth/drive.readonly`).

---

## 4. Tableau récapitulatif

| **Concept**  | **Rôle**                              | **Scope**                                 |
|--------------|---------------------------------------|-------------------------------------------|
| **Analogie** | Ton poste dans l’entreprise.          | Ce que tu autorises une appli à faire avec ton compte. |
| **Keycloak** | Assigné via la console d’administration. | Défini dans les clients OAuth.            |
| **Standard** | Non standardisé (spécifique au fournisseur). | Standardisé (OAuth 2.0).                  |

---

## 5. Cas d’usage pratique
- **Rôle** : « Jennifer est `admin` dans le realm Keycloak → elle peut gérer les utilisateurs »
- **Scope** : « L’application mobile demande les scopes `basket:list`, `basket:read` → elle peut lister et lire les paniers de l’utilisateur, mais pas les modifier. »
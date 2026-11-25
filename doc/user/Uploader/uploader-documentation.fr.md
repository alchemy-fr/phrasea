---
title: Documentation du service Uploader
status: En attente de relecture
---

# Uploader client et administrateur

## Vocabulaire

- **Target** : Destination des fichiers : un workspace Databox, une collection Phraseanet ou une application tierce, avec son formulaire et les groupes autorisés à l’utiliser.  
- **Cibles** : Menu de l’Uploader client qui modifie les réglages de chaque target, dans trois onglets :  
  - **Propriétés** : Nom, URL, autorisation, destination et groupes autorisés de la target (administrateurs).  
  - **Formulaire** : Construit le formulaire d’indexation rempli par l’utilisateur (constructeur de formulaire).  
  - **Données** : Données d’indexation supplémentaires de la target, non accessibles à l’utilisateur.  
- **Asset** : Fichier téléversé.  
- **Commit** : Ensemble regroupant les assets et les informations saisies dans le formulaire de la target (au format JSON).  
- **Lien d’envoi** : URL secrète permettant à une personne sans compte d’envoyer des fichiers vers une target, dans certaines limites.  
- **Multipart upload** : Les fichiers envoyés sont découpés en parties (chunks) et rassemblés côté serveur une fois l’upload terminé.

## Concepts de base

- Le service Uploader de Phrasea est constitué d’une API, d’une interface utilisateur nommée “Uploader client”, d’un “worker” et d’un “scheduler” (nettoyage quotidien). Ces composants s’appuient sur un stockage S3, une base de données, des serveurs de cache et un bus de messages. L’API et le worker peuvent être scalés selon les besoins.  
- L’Uploader contient des Targets.  
- À chaque Target est associé un formulaire, constitué de différents widgets.  
  - Le formulaire permet à l’utilisateur qui dépose des fichiers de saisir des informations relatives au lot de fichiers envoyé.  
  - La définition du formulaire s’effectue dans un éditeur en ligne.  
- L’envoi de fichiers via une Target est soumis à des droits sur des groupes.  
- Une destination peut être définie pour chaque Target.  
  - Une Target peut être :  
    - Une collection Phrasea  
    - Une collection Phraseanet  
    - Une application tierce  
- L’Uploader n’est pas un stockage : une fois les fichiers reçus par la destination, ils sont supprimés de l’Uploader.

- Par défaut, la base de comptes est celle du realm “Phrasea” dans Keycloak.  
  - Le service Uploader utilise un client OAuth dans Keycloak pour l’authentification.

## Cinématique d’ajout de fichier avec l’interface Uploader client

Cette section décrit les étapes principales et les actions déclenchées lors d’un ajout de fichiers avec l’Uploader client vers Databox.

### Interaction entre l’Uploader et Phrasea Databox

L’enchaînement des requêtes est décrit dans la [documentation technique](../../tech/Uploader/request_flow.md).

- Un utilisateur s’authentifie.  
- Des Targets lui sont présentées, selon son groupe d’appartenance.  
  - L’utilisateur sélectionne une Target.  
  - L’utilisateur sélectionne des fichiers via :
    - Sélecteur de fichiers
    - Glisser-déposer

    ou importe un fichier depuis son URL.

  - L’utilisateur passe à l’écran suivant pour compléter le formulaire.

- L’upload des fichiers commence en tâche de fond.  
  - L’utilisateur complète et valide le formulaire.  
    - Une fois tous les fichiers reçus par le service Uploader, la destination définie dans la Target est notifiée qu’un lot de fichiers est disponible :  
      - Phrasea Databox  
      - Phraseanet  
      - Application tierce pouvant déclarer un endpoint écoutant les notifications émises

    - Le worker Databox commence la création d’un asset par fichier inclus dans le lot et télécharge le fichier depuis le stockage Uploader configuré dans la stack.  
    - Les informations issues du formulaire servent à renseigner les attributs du lot de fichiers envoyé.

> *Systèmes de fichiers supportés par le service Uploader et exigences minimales*

L’Uploader est compatible avec un stockage S3. Pour un fonctionnement optimal, le système de fichiers doit supporter certaines fonctionnalités (voir la documentation correspondante).  
Les systèmes testés et validés sont AWS S3 et Minio (voir la documentation de la stack, attention aux conteneurs utilisés en tant que primary datastore).  
Pour une utilisation optimale, l’Uploader supporte :  
- L’upload multipart, permettant de transmettre des fichiers volumineux  
- Les capacités techniques, comme la taille maximale acceptée, dépendent du système de fichiers utilisé

## Envoyer des fichiers

### Choisir une target

La page d’accueil liste les targets que l’utilisateur peut utiliser : « Où voulez-vous envoyer vos fichiers ? ». S’il n’y en a qu’une, elle s’ouvre directement. Un utilisateur sans aucune target voit un message le lui indiquant.

### Étapes

La page d’envoi guide l’utilisateur par étapes numérotées :

1. **Fichiers** : glisser des fichiers sur la page, ou cliquer pour les sélectionner (« Ajouter des fichiers »). Les limites sont affichées sous la zone de dépôt : taille par fichier, nombre de fichiers et taille totale par envoi. Les types de fichiers acceptés sont listés derrière l’icône d’information. Un fichier d’un type non accepté, ou trop volumineux, est refusé avec un message ; un fichier déjà sélectionné est écarté. Quand la stack n’autorise qu’un fichier par envoi, la page passe en mode fichier unique : un nouveau fichier remplace le précédent.
2. **Informations** : le formulaire de la target, si elle en a un. Les fichiers s’envoient déjà pendant que l’utilisateur le remplit. « Valider et envoyer » le valide.
3. **Finalisation** (ou **Envoi** sans formulaire) : l’envoi se termine dès que tous les fichiers sont arrivés. « Modifier les informations » ramène au formulaire.
4. **Terminé** : le nombre de fichiers envoyés et la target. « Envoyer d’autres fichiers » lance un nouvel envoi vers la même target, « Choisir une autre destination » ramène à la liste.

« Annuler » arrête l’envoi : les fichiers déjà envoyés et les informations saisies sont perdus. Quitter la page pendant un envoi demande une confirmation.

### Échecs et reprise

- Un fichier en échec est réessayé automatiquement. S’il échoue encore, il est affiché avec son erreur, sans bloquer les autres : « Réessayer » le renvoie, « Retirer » l’écarte.  
- Quand la connexion est perdue, l’envoi l’attend et reprend tout seul.  
- Après un rechargement de la page, déposer à nouveau le même fichier reprend son envoi là où il s’était arrêté.  
- Quand l’envoi ne peut pas être finalisé, une erreur s’affiche : « Réessayer », ou « Modifier le formulaire » pour corriger les informations.

### Être notifié

Quand le formulaire de la target propose « Me prévenir à la fin du traitement », l’utilisateur est notifié par email une fois les fichiers traités par la destination.

## Importer un fichier depuis une URL

Sous la zone de dépôt, « importez un fichier depuis une URL » ouvre la page « Importer depuis une URL » :

- saisir l’URL du fichier et remplir le formulaire de la target, puis « Importer » ;
- l’Uploader récupère lui-même le fichier, puis l’envoie à la target comme tout autre envoi : la page peut être quittée aussitôt ;
- « Importer un autre fichier » recommence.

Le fichier doit être public : une adresse du réseau interne est refusée. Il est récupéré en 10 minutes au plus et soumis aux mêmes limites que les autres fichiers (taille, types acceptés). Le nombre d’imports est limité par heure et par utilisateur. Cette page n’est pas disponible via un lien d’envoi.

## Liens d’envoi

Un lien d’envoi permet à une personne sans compte (un photographe, un partenaire…) d’envoyer des fichiers vers une target. Ce qui est envoyé avec est attribué à l’utilisateur qui l’a créé.

### Gérer les liens

Les utilisateurs autorisés à créer des liens voient le menu « Liens d’envoi ». Il liste les liens de l’utilisateur (tous les liens, pour un administrateur) avec leur destination, leur statut, les fichiers et la taille envoyés, leur expiration et leur dernier envoi.

- **Nouveau lien** : choisir la target, un nom (à qui le lien est destiné), une date d’expiration (une semaine par défaut, dans le maximum de la stack), et éventuellement un nombre maximal de fichiers et une taille totale maximale, sur tous les envois faits avec le lien. « Créer le lien » affiche le lien à copier.  
- **Copier le lien** : copie à nouveau son URL.  
- **Modifier**, **Désactiver** / **Activer**, **Supprimer** : un lien supprimé cesse définitivement de fonctionner ; ce qui a été envoyé avec reste.  
- **Nouvelle URL** : remplace l’URL du lien. L’ancienne URL cesse aussitôt de fonctionner.

Le statut d’un lien est « Actif », « Désactivé », « Expiré » ou « Limite atteinte ». Un lien cesse aussi de fonctionner quand son créateur ne peut plus créer de liens ou envoyer vers sa target.

### Envoyer avec un lien

La personne qui reçoit le lien l’ouvre dans un navigateur, sans se connecter. La page affiche la target et ce qui reste sur le lien (fichiers, taille), puis les mêmes étapes qu’un envoi connecté. « Me prévenir à la fin du traitement » n’est pas proposé : personne n’est derrière le lien pour être notifié.

Au prochain envoi avec le même lien, depuis le même navigateur, « Reprendre mes informations précédentes » remplit le formulaire avec ce qui a été saisi la dernière fois ; « Oublier mes informations précédentes » l’efface. Ces informations restent dans le navigateur : les autres détenteurs du lien ne les voient jamais.

Quand le lien est invalide, désactivé, expiré ou n’est plus valable, la page l’indique et invite à demander un nouveau lien à la personne qui l’a envoyé.

## Menus et réglages

- Menu **Paramètres** : thème (clair, sombre ou système, et le thème de l’organisation), langue (anglais, français, allemand, espagnol), et le consentement aux cookies quand Matomo le demande.  
- **Tous les services** : retour au tableau de bord des services Phrasea, quand la stack l’affiche.  
- Menu **utilisateur** : mon compte (Keycloak), déconnexion, « Gérer les groupes… » pour les utilisateurs autorisés à gérer des groupes, et « Débogage de l’utilisateur » sur une stack de développement.

## Paramétrage

### Uploader et Phrasea Databox

Paramétrage d’une Target pour Phrasea

#### Paramétrage Databox

Dans l’application Databox client, sur un workspace, définir une nouvelle intégration “Uploader” :

- L’intégration est propre au workspace et doit être définie pour chacun d’eux.  
- Sa configuration :

```yaml
# URL de base de l’API Uploader
baseUrl:
# ID de la collection cible (optionnel)
collectionId:         ~
# Clé de sécurité pour authentifier les requêtes Uploader
securityKey:    
```

La clé de sécurité est générée si elle est laissée vide. L’intégration affiche ensuite les valeurs à reporter dans l’Uploader : la “Target URL”, l’“Authorization Scheme” (`ApiKey`) et la “Security Key”.

#### Paramétrage Uploader admin

Le paramétrage côté Uploader se fait par un administrateur :

- Ajouter une Target : menu « Cibles » > « Nouvelle cible » dans l’Uploader client, ou Uploads > Targets dans l’admin.  
- Paramètres à saisir, obtenus de l’intégration “Uploader” Databox :  
  - “Target URL” : le point de terminaison côté “Phrasea” où seront notifiés les ajouts de fichiers  
  - Authorization key : la clé de sécurité de l’intégration  
  - Authorization Scheme : `ApiKey`, la valeur par défaut  
- Puis, dans l’onglet « Formulaire » de la target, choisir le workspace Databox pour proposer ses attributs dans le formulaire.

### Uploader et Phraseanet

Paramétrage de la Target.  
En mode Push, le fonctionnement de l’Uploader avec Phraseanet est identique dans la mécanique ; seuls quelques paramètres changent :

- “Target URL” : le point de terminaison enqueue de Phraseanet, `https://<phraseanet>/api/v1/upload/enqueue/`  
- Authorization Scheme : `OAuth`  
- Authorization key : un jeton de l’API Phraseanet  
- Destination par défaut : l’identifiant de la collection Phraseanet

En mode Pull, la target n’a pas d’URL : Phraseanet vient lui-même chercher les envois, depuis l’“URL du mode pull” affichée par la target dans l’admin. Voir la [documentation technique](../../tech/Uploader/02_setup.md#target-for-phraseanet).

## Paramétrage d’un formulaire de Target

### Cibles

Dans l’interface client Uploader : menu « Cibles ». La liste indique, pour chaque target, si elle a un formulaire et des données. Cliquer sur une Target pour la modifier : sa page a un onglet « Propriétés », un onglet « Formulaire » et un onglet « Données », chacun avec son propre bouton « Enregistrer ». Changer d’onglet conserve les modifications non enregistrées. Le fil d’Ariane au-dessus du titre ramène à la liste.

Chaque onglet demande sa propre permission : l’onglet Propriétés est réservé aux administrateurs, l’onglet Formulaire demande la permission d’édition des formulaires et l’onglet Données celle d’édition des données de la target. Un utilisateur qui n’a que l’une d’elles ne voit que l’onglet correspondant.

Les targets désactivées et masquées se gèrent uniquement dans l’admin.

### Nouvelle cible

Un administrateur crée une target depuis la liste, avec le bouton « Nouvelle cible ». La boîte de dialogue demande les mêmes propriétés que l’onglet Propriétés ; seul le nom est obligatoire. « Créer la cible » l’enregistre et ouvre sa page, où ajouter ensuite son formulaire et ses données.

### Onglet Propriétés

L’onglet « Propriétés » règle les propriétés de la target :

- **Nom** : obligatoire.  
- **Identifiant** : facultatif et unique ; lettres minuscules, chiffres, « - » et « _ », commençant par une lettre (100 caractères au plus).  
- **Description**.  
- **URL de la cible** : où les envois sont poussés, en https. Vide pour le mode pull : la destination vient chercher les envois.  
- **Schéma d’autorisation** et **Clé d’autorisation** : en mode push uniquement. Le schéma vaut « ApiKey » par défaut (« OAuth » pour Phraseanet).  
- **Destination par défaut** : l’identifiant d’une collection Databox ou d’une collection Phraseanet.  
- **Groupes autorisés** : vide pour autoriser tous les utilisateurs.

La clé d’autorisation n’est jamais affichée : laisser le champ vide la conserve, et la case « Supprimer la clé » la retire. Elle est envoyée à l’URL de la cible : si cette URL mène à un autre hôte (schéma, hôte ou port), la clé est supprimée à l’enregistrement, sauf si une nouvelle clé est saisie en même temps.

### Onglet Formulaire

Documentation et exemple de [formulaire](../../tech/Uploader/form_config.md)

Chaque target a un seul formulaire, pour toutes les langues : ses libellés, aides et placeholders sont traduits dans le formulaire lui-même. L’onglet Formulaire permet de construire le formulaire en glissant les attributs d’un workspace Databox (dont les listes d’entités), des valeurs Databox (nom, story, « Me prévenir à la fin du traitement »), des champs personnalisés et des sections, puis d’en voir le rendu. « Supprimer le formulaire » le retire : la target n’accepte alors plus de données d’indexation de l’utilisateur.

Les formulaires décrits en JSON (LiForm) avant la version 5 ont été convertis vers le constructeur lors de la mise à jour ; voir la [documentation technique](../../tech/Uploader/form_config.md#migration-from-liform) pour ce que garde la conversion.

### Onglet Données

L’onglet « Données » de l’éditeur de cibles permet de passer, sous forme d’objet JSON, des données supplémentaires ajoutées à chaque envoi vers la target pour décrire les assets. Le vider retire les données de la target.

Les données définies dans l’onglet « Données » prévalent sur les informations saisies dans le formulaire. Il est donc inutile de déclarer des valeurs d’attribut dans le formulaire si celles-ci sont déjà référencées dans les données de la target.

## Notifications

L’émetteur peut être notifié via l’Uploader : quand le formulaire de la target a le champ « Me prévenir à la fin du traitement » et que l’émetteur le coche, l’Uploader le notifie une fois les fichiers acquittés par la destination. Comme les autres modules, le module “Uploader” envoie ces notifications via le notifier bundle : par email, les notifications in-app étant enregistrées sans qu’aucune interface ne les affiche encore.

## FAQ

**Pourquoi un service externe ?**

- Cela permet d’isoler l’ajout de fichiers dans une interface dédiée  
  - Sans donner accès à l’intégralité du DAM  
  - Une interface réduite à l’essentiel pour les utilisateurs  
  - Peut fonctionner en mode “Pull” : l’application consommatrice interroge l’Uploader depuis une zone sécurisée à la fréquence de son choix (Databox n’a pas de mode “Pull” : son intégration Uploader ne reçoit que les envois poussés par l’Uploader)

**Pourquoi un formulaire et des données de la target ?**

- Le formulaire est rempli par l’utilisateur qui dépose les assets  
- Les données de la target permettent d’ajouter des valeurs au lot d’assets sans passer par le formulaire ; ces valeurs ne sont pas accessibles à l’utilisateur déposant les assets

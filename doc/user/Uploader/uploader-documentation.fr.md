---
title: documentation du service Uploader
slug: documentation-service-uploader
---

# Uploader client et administrateur 

# Vocabulaire:

- **Target** : Une destination pour les fichiers  
- **Form-editor** : Ensemble de widgets disponibles avec la lib liform, permettant la mise en place d’un formulaire d’indexation accessible à l'utilisateur.  
- **Target parameter Editor**: Donnée d’indexation supplémentaire, inaccessible à l’utilisateur.  
- **Asset** : Un fichier téléversé.   
- **Commit** : dataset regroupant les assets et les informations saisies dans le formulaire de la target (sous forme de json)    
- **Multipart upload**: lors de l’upload sont découpés en partie (chunk) et rassemblés côté serveur une fois l’upload fini.


# Concepts de base 

- Le service uploader de Phrasea est constitué d’une API et une interface homme machine nommé “Uploader client” et d’un “worker”, c’est trois composant s’appuie sur un file system et une base de données ainsi que des serveur de cache et un bus de message, les composants peuvent être scaler en fonction des besoins.  
- l’Uploader contient des Targets.   
- A chaque Target est attachée un formulaire, constitué de différents widgets   
  - Le formulaire permet à l’utilisateur déversant des fichiers de saisir des infos en rapport avec le lot de fichiers envoyé.  
  - la définition du formulaire s’effectue dans un éditeur en ligne   
-  L’envoi de fichier au moyen d’une target est régi par des droits sur des groupes  
-  Une destination peut être définie pour chaque target    
  - Une target peut être:   
    - Une collection Phrasea.  
      - la collection   
    - Une  collection Phraseanet.   
    - Une application tierce.

- Par défaut, la base de comptes est celle du royaume “Phrasea” dans un Keycloak  
  - Le service Uploader passe au travers du client i dans Keycloak au travers d’un client Oauth. 


 

# 

# Cinématique ajout de fichier avec l’interface uploader client vers.

Cette section décrit les étapes majeures et des actions déclenchées lors d’un ajout de fichiers avec l’Uploader client vers Databox.

#  Interaction entre l’Uploader et Phrasea Databox.

![uploade sequence](/tech/Uploader/sequence.png)

- un utilisateur s’authentifie.  
- des targets lui sont présentées.   
  - en fonction de son groupe d’appartenance.  
  - l’utilisateur sélectionne une target.  
  - l’utilisateur sélectionne des fichiers au moyen de  
    - file selector system.  
    - drag and drop.  
    - paste from clipboard (image type only).  
    - le contenu d’un fichier sur une URL.

  \- l’utilisateur passe à l'écran suivant pour une complétion du formulaire.

- l’upload des fichiers commence en tâche de fond   
  - l'utilisateur complète et valide le formulaire   
    -  une fois toutes les fichiers reçus par le service uploader, la destination définie dans la target est notifié lui indiquant qu’un lot des fichiers est à sa disposition:   
      - Phrasea Databox   
      - Phraseanet   
      - Application tierce pouvant déclarer un endpoint écoutant les notifications émises par  

			le worker databox commence la création d’un asset par fichier inclus dans le lot et télécharge le fichier depuis le stockage uploader paramétré dans la stack \*  
Les informations issues du formulaire servent à renseigner les attributs du lot de fichier envoyé. 

   
\* file system supporté par le service uploader et minimal requirement

l’uploader est compatible avec un storage S3 mais pour un fonctionnement optimum le file system doit supporter ( faire renvoi sur les bons links a minima)  
pour l’instant les systèmes testés et éprouvés sont AWS S3 et Minio (faire un renvoi sur la doc de la stack modération à apporter sur les container primary datastore)  
dans une utilisation optimum l’uploader supporte:   
\- l’upload multi part permettant de transmettre des fichiers de grande taille.  
\- les capacités techniques comme la taille maximum acceptée sont héritées des capacités du file system.

# Paramétrage

# Uploader et Phrasea Databox

Paramétrage d’une target pour Phrasea   
 

### Paramétrage Databox

 Dans l’application databox client sur un workspace   
   
![][image1]

Définir une nouvelle intégration “Uploader”

- l’intégration est propre au workspace et doit-être définie pour chacun d’eux.

### Paramétrage Uploader admin. 

Paramétrage côté Uploader réalisé au moyen de l’interface d’administration 

- ajouter une Target dans upload \> target


  
![][image2]  
 

- paramètres à saisir, obtenus lors de la création de l’intégration “Uploader” databox    
  - “Target URL”  le point de terminaison côté “Phrasea ” où seront notifiés les ajouts de fichiers.   
  - Authorization key : une clef d’authentification émise lors de la création de l’intégration   
  - Authorization Scheme: description du mécanisme d'authentification à utiliser pour les échanges uploader \>databox 

![][image3]

```json
# The Uploader API base URL
baseUrl:
# The collection target optional
collectionId:         ~
# The security key to authenticate Uploader requests
securityKey:    

```

## 

## Uploader et Phraseanet

Paramétrage de la target.  
En mode Push, le fonctionnement de l’Uploader avec Phraseanet est identique dans la mécanique, quelques paramètres changent mais .

## Paramétrage d’un formulaire de target

### Form-editor

Dans l’interface client uploader ou depuis Uploader admin/form-editor   
documentation et exemple de [formulaire](https://github.com/alchemy-fr/phrasea/blob/master/doc/tech/Uploader/form_config.md)  
   
Cliquer sur une target 

l’éditeur de formulaire permet alors de décrire  le formulaire en json et voir le rendu instantané

exemple:

![][image4] todo

### Target parameter Editor

le “target parameters editor” permet de passer des paramètres supplémentaires inclus dans une section du json pour décrire l’asset.

le “target parameters editor” prévalent sur les informations saisies dans le form-editor, il est donc inutile de déclarer des valeurs d'attribut   
dans le formulaire de saisie si celui ci est référencé dans le target parameters.

![][image5] todo

# Notifications possibles 

Il est possible de notifier l'émetteur de l’Uploader, comme les autres modules, le module “Uploader” intègrent Novu pour la gestion des notifications. 

Coté databox 

# FAQ:

Pourquoi un service externe ?

- cela permet d’isoler l’ajout de fichier dans une interface   
  - Sans donner accès à l'intégralité du Dam  
  - une interface réduite à l’essentiel pour les utilisateurs   
  - Peut fonctionner en mode “Pull”, l’application de consommatrice interroge l’uploader depuis une zone sécurisée à la fréquence de sont choix (le mode “Pull” n’est pas encore disponible pour Phrasea).  
    

  


Pourquoi Form-editor et Target-editor ?

- le Form-editor permet la construire un formulaire utilisable par l’utilisateur  
- Le target parameters permet d’ajouter des valeurs au lot d’ assets sans passer par le formulaire et ne sont pas accessible à l'utilisateur déversant les assets.


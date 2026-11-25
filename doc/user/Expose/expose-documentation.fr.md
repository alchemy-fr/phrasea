---
title: Guide utilisateur d'Expose
status: En attente de relecture
---
> **Statut :** _En attente de relecture_  
> Cette page de documentation attend une relecture et peut être mise à jour.

# Guide utilisateur d'Expose

## Qu'est-ce qu'Expose

Expose est le service de publication de Phrasea : il présente des ensembles d'assets sur le web,
sous forme de galeries ou de pages de téléchargement, à tout le monde ou aux personnes de votre
choix. Les assets viennent de Databox : vous publiez un panier, ou une recherche enregistrée
publie ses résultats pour vous. Expose permet ensuite aux éditeurs d'une publication de régler
son affichage et qui peut la voir.

Ce guide s'adresse à deux lecteurs : le **visiteur**, qui ouvre une publication, et l'**éditeur**,
qui publie et gère les publications. Les administrateurs de la plateforme trouveront la
documentation technique dans [Expose](../../tech/Expose/01_intro.md).

### Vocabulaire

- **Publication** : un ensemble d'assets avec un titre, une description et des réglages, ouvert
  par sa propre adresse web.
- **Sous-publication** : une publication placée sous une autre (sa parente). Une
  sous-publication est masquée quand sa parente l'est, et protégée par le mot de passe de sa
  parente sauf si elle a ses propres réglages d'accès.
- **Asset** : un fichier d'une publication (image, vidéo, audio, document), avec son titre et sa
  description.
- **Rendu** : une autre version du fichier d'un asset (aperçu, vignette…), proposée au
  téléchargement dans certaines mises en page.
- **Profil** : un ensemble de réglages partagés par plusieurs publications. Une publication qui
  utilise un profil en prend tous les réglages, sauf ceux qu'elle remplace.
- **Mise en page** : la façon d'afficher les assets d'une publication (grille, galerie, liste de
  téléchargement).
- **Slug** : le nom court d'une publication dans son adresse web, utilisé à la place de son
  identifiant.
- **Lien de partage** : un lien privé qui ouvre une publication à quiconque le détient, même
  quand elle est désactivée ou protégée.
- **Éditeur** : un utilisateur autorisé à modifier une publication. **Opérateur** : un éditeur
  autorisé en plus à gérer ses liens de partage et son profil.

## Visiteurs

### Ouvrir une publication

Une publication s'ouvre par le lien qui vous a été donné :

- sa propre adresse, avec son slug (`https://expose.example.com/ma-galerie`) ou son
  identifiant ;
- l'adresse d'un de ses assets, qui ouvre la publication avec cet asset affiché ;
- un **lien de partage** (`https://expose.example.com/s/…`), qui ouvre la publication même
  quand elle est protégée ou non publique. Si le lien a expiré ou a été révoqué, la page indique
  **Lien invalide**.

La page d'accueil d'Expose liste les publications listées publiquement, sauf si les
administrateurs l'ont désactivée. Un utilisateur connecté y trouve aussi les publications qu'il
peut modifier.

### Mises en page

Chaque publication a l'une de ces trois mises en page :

- **Grille** : une mosaïque de vignettes. Un clic sur une vignette ouvre l'asset dans une
  visionneuse plein écran.
- **Galerie** : un asset à la fois dans un grand lecteur, avec les boutons **Précédent** et
  **Suivant**, sa position, et le bandeau des vignettes en dessous.
- **Téléchargement** : une liste de fichiers. Chaque asset affiche sa vignette, son titre, sa
  description et sa taille, avec un bouton **Télécharger l'original** et un bouton
  **Télécharger …** pour chacun de ses rendus.

Quand une publication n'a pas encore d'asset, la page indique **Aucun asset disponible**.

### Naviguer

- Chaque asset a sa propre adresse : vous pouvez la copier depuis la barre d'adresse pour
  désigner cet asset.
- Dans la visionneuse et dans la galerie, les touches **←** et **→** affichent l'asset précédent
  et le suivant (la liste boucle), et **Échap** ferme la visionneuse (dans la galerie, elle
  revient au premier asset).
- Le bouton **Retour** du navigateur ferme la visionneuse : passer d'un asset au suivant n'ajoute
  pas d'étape à l'historique.
- Dans la grille, d'autres vignettes se chargent à mesure que vous faites défiler la page.

Le titre, la description et la date de la publication s'affichent en haut, et le titre, la
description et le bouton **Télécharger** de l'asset affiché à côté du lecteur. Quand une
publication ou un asset a une traduction dans la langue de l'interface, il s'affiche dans cette
langue.

### Lecteurs

- **Images** : zoom avant et arrière (boutons, molette de la souris ou pincement), déplacement de
  l'image agrandie, rotation, **Ajuster à l'écran** ou **Taille réelle**.
- **Vidéos** : le lecteur vidéo, avec les **Sous-titres** de la vidéo quand elle en a, ceux de
  votre langue en premier.
- **Audio** : un lecteur qui affiche la forme d'onde du son, avec **Lecture** / **Pause**.
- **Documents PDF** : les pages du document, avec **Page précédente** / **Page suivante**, le
  numéro de page, le zoom, **Page entière** et **Ajuster à la largeur**.

Les autres fichiers sont représentés par l'icône de leur type.

### Sous-publications

Quand une publication a des sous-publications (ou en est une), le tiroir de navigation, ouvert
par le bouton de menu de la barre du haut, affiche sa parente, la publication elle-même et ses
sous-publications. Un fil d'Ariane au-dessus du titre ramène aux publications parentes. Seules
les sous-publications que vous avez le droit de voir sont listées.

### Publications protégées

Une publication peut être réservée :

- **Mot de passe** : la page affiche **Publication protégée** et demande le mot de passe ;
  saisissez-le et cliquez sur **Entrer**. Le mot de passe est mémorisé par votre navigateur, et
  ouvre aussi les sous-publications protégées par le même mot de passe. Après trop de mots de
  passe incorrects, la page indique **Trop de mots de passe incorrects. Réessayez dans quelques
  minutes.**
- **Connexion** : la page affiche **Connexion requise**. Vous êtes redirigé vers la page de
  connexion, puis ramené à la publication. Si votre compte n'est pas autorisé à la voir, la page
  indique **Accès refusé** ; **Se connecter avec un autre compte** permet de changer de compte.
- Une publication désactivée, pas encore ouverte ou expirée n'est pas accessible : la page
  indique **Accès interdit** (ou **Authentification requise** quand vous n'êtes pas connecté).
  Seuls ses éditeurs et les détenteurs d'un lien de partage peuvent l'ouvrir.

### Conditions

Certaines publications demandent d'accepter leurs **Conditions d'utilisation** avant d'afficher
quoi que ce soit : lisez-les (ou suivez le lien **Conditions**), puis cliquez sur **Accepter**.
Votre acceptation est mémorisée par votre navigateur pendant un an.

### Téléchargements

Quand la publication le permet, chaque asset a un bouton **Télécharger** (et la mise en page
**Téléchargement** un bouton par fichier). Selon les réglages de la publication :

- le fichier est téléchargé tout de suite ;
- les **Conditions de téléchargement** s'affichent d'abord : cliquez sur **Accepter et
  télécharger** (une fois pour la publication) ;
- **Téléchargement par e-mail** : saisissez votre adresse e-mail et cliquez sur **Demander le
  téléchargement**. Vous recevez un lien par e-mail, valable 3 jours.

Une publication de plusieurs assets peut proposer **Télécharger l'archive**, un fichier ZIP de
tous ses originaux. L'archive est préparée à la demande : une fenêtre indique **L'archive est en
cours de préparation. Son téléchargement commencera dès qu'elle sera prête.** Si cela dure trop,
cliquez sur **Recevoir par e-mail** pour recevoir son lien par e-mail à la place.

Quand le téléchargement est désactivé, aucun bouton de téléchargement n'est affiché.

### Thème, langue et cookies

Le menu **Paramètres** de la barre du haut propose :

- **Thème** : **Clair**, **Sombre** ou **Système**, un choix de thèmes de couleurs, et le thème
  de votre organisation quand elle en définit un ;
- **Langue** : anglais, français ou allemand ;
- **Cookies** : votre choix sur les cookies de mesure d'audience (Matomo). Sans votre
  acceptation, l'audience est mesurée sans cookie.

## Éditeurs

### Publier un panier depuis Databox

Les publications se créent depuis Databox, pas depuis Expose.

1. Dans Databox, ouvrez le panier : depuis l'onglet **Paniers** du panneau latéral, le menu du
   panier puis **Modifier**, ou le bouton **Intégrations** de la vue du panier.
2. Dans la fenêtre **Gérer le panier**, allez dans l'onglet **Intégrations**.
3. La première fois, cliquez sur **Autoriser** sous l'intégration Expose et connectez-vous :
   Databox publiera avec votre compte Expose.
4. Cliquez sur **Nouvelle publication**, remplissez le formulaire, puis cliquez sur **Créer et
   synchroniser** :
   - **Titre**, **Slug** (facultatif) et **Description** ;
   - **Publication parente (id)** et **Profil de publication (id)** : les identifiants d'une
     publication ou d'un profil existants, facultatifs ;
   - **Activé** : si la publication est visible tout de suite ;
   - **Publier les fichiers entiers (ignorer les régions d'intérêt et les extraits)** : sinon,
     les fichiers sont découpés selon la région d'intérêt définie sur les assets. Ce choix ne
     peut plus être modifié ensuite.
5. Databox envoie les assets du panier, avec leur aperçu, leur vignette et une description
   construite à partir de leurs attributs. La progression s'affiche (**Synchronisation en
   cours…**, puis **Synchronisation terminée !**).

Dès lors, chaque modification du panier (assets ajoutés ou retirés) est envoyée à la
publication. Les assets qui ne peuvent pas être publiés sont listés (**… assets non publiés**)
avec la raison : assets que vous ne pouvez pas lire, supprimés, en attente d'analyse ou en
quarantaine, ou refusés par Expose.

Sous la publication, dans le même onglet :

- l'icône de flèche ouvre la publication dans Expose ;
- **Forcer la synchronisation** renvoie le panier ;
- **Arrêter** met fin à la synchronisation. Cochez **Supprimer aussi la publication dans
  Expose** pour la supprimer ; sinon la publication et ses assets restent sur Expose, mais les
  modifications du panier ne lui sont plus envoyées.

### Publier automatiquement une recherche enregistrée

Une automatisation d'une recherche enregistrée peut publier ses résultats : dans les
**Automatisations** de la recherche enregistrée, créez une automatisation (**Nouvelle
automatisation**), puis **Ajouter une action** et choisissez **Publier dans Expose** :

- **Intégration Expose**, puis **Publication** : une publication existante dont vous êtes
  éditeur, ou une nouvelle (**Créer une publication**) ;
- **Publier les fichiers entiers** ;
- **Refléter les résultats** : les assets qui ne sont plus dans les résultats sont retirés de la
  publication. Seulement avec la portée **Tous les résultats**.

L'automatisation ne touche qu'aux assets qu'elle a publiés elle-même.

### Retrouver ses publications

Une fois connecté à Expose, l'entrée **Publications** de la barre du haut liste les publications
que vous pouvez modifier, sous-publications comprises, avec un champ de recherche. Les badges
**Désactivée** et **Non listée** signalent les publications qui ne sont pas visibles, ou pas sur
la page d'accueil. Chaque ligne peut être ouverte, modifiée ou supprimée.

Sur la page d'une publication, un bouton **Modifier** s'affiche pour ses éditeurs, et un bouton
**Partager** pour ses opérateurs. Une publication désactivée affiche un avertissement à ses
éditeurs : **Cette publication est actuellement désactivée. Seuls les administrateurs peuvent la
voir.**

### Le formulaire de publication

L'écran **Modifier** d'une publication comporte :

- **Titre** et **Slug** (utilisé dans l'adresse de la publication à la place de son
  identifiant) ;
- **Publication parente** : pour en faire une sous-publication ;
- **Profil**, avec un lien **Modifier le profil** ;
- **Couverture** : voir plus bas.

Puis les réglages, en cinq sections :

- **Visibilité** :
  - **Activée** : si la publication est visible ;
  - **Listée publiquement** : affichée sur la page d'accueil, pour tout le monde ;
  - **Début** / **Fin** : la période pendant laquelle elle est visible.
- **Téléchargement** :
  - **Téléchargement activé** ;
  - **Téléchargement par e-mail** : le visiteur saisit une adresse e-mail et y reçoit le lien de
    téléchargement ;
  - **Inclure les conditions de téléchargement dans l'archive**.
- **Mise en page** : **Grille**, **Galerie** ou **Téléchargement**. **Carte** est marquée
  **Bientôt** et ne peut pas être choisie.
- **Sécurité**, **Accès** :
  - **Public** : toute personne ayant le lien ;
  - **Authentification** : les utilisateurs connectés autorisés à voir la publication ;
  - **Mot de passe** : quiconque connaît le mot de passe, pour la publication et ses
    sous-publications. Le mot de passe n'est plus jamais affiché : laissez le champ vide pour le
    conserver.
- **Conditions** :
  - **Conditions d'utilisation** : affichées avant la publication ;
  - **Conditions de téléchargement** : affichées avant le premier téléchargement.

  Chacune avec **Texte des conditions** (le HTML est accepté) et **URL des conditions**.

Cliquez sur **Enregistrer la publication**.

### Profils et remplacement des réglages

Un profil regroupe des réglages partagés par les publications qui l'utilisent. L'entrée
**Profils** de la barre du haut les liste (**Créer un profil**, modifier, supprimer). Son
formulaire comporte un **Nom** et les mêmes réglages qu'une publication.

Sur une publication qui a un profil, chaque réglage affiche la valeur du profil (**Valeur du
profil :**) tant que vous n'activez pas **Remplacer le réglage** pour lui. Modifier un réglage
du profil modifie toutes les publications qui ne le remplacent pas. Choisir **Public** sur une
publication remplace un profil protégé.

Un profil utilisé par des publications ne peut pas être supprimé (**Un profil utilisé par des
publications ne peut pas être supprimé.**). Seuls les opérateurs d'une publication peuvent
changer son profil.

### Couverture

La couverture est l'image de la publication sur la page d'accueil et dans les aperçus de lien
des réseaux sociaux et des messageries. Cliquez sur **Choisir l'image de couverture** dans le
formulaire, puis choisissez l'un des assets de la publication, ou **Aucune couverture** : le
premier asset est alors utilisé.

### Liens de partage

Un lien de partage ouvre la publication à quiconque le détient, même désactivée ou protégée,
jusqu'à son expiration ou sa révocation. Ses sous-publications ne sont pas partagées.

1. Cliquez sur **Partager** sur la page ou sur l'écran de modification de la publication.
2. **Créer un lien**, avec un **Nom** facultatif (destinataire, objet…) et une expiration
   facultative (**Expire le** ; sans elle, le lien fonctionne jusqu'à sa révocation).
3. **Copier le lien** et envoyez-le.

Chaque lien peut être renommé ou prolongé, révoqué (**Révoquer**, définitivement : il n'ouvre plus la
publication) ou supprimé de la liste. Le détenteur d'un lien est un visiteur : les réglages et
les conditions de téléchargement s'appliquent à lui.

### Supprimer

**Supprimer** (dans la liste, ou sur la page d'accueil) supprime la publication, ses assets et
ses sous-publications. Cette action est irréversible. Les assets restent dans Databox. Si la
publication était synchronisée avec un panier, arrêtez aussi la synchronisation dans Databox.

### Qui peut faire quoi

| Qui                                        | Peut                                                                                                 |
| ------------------------------------------ | ---------------------------------------------------------------------------------------------------- |
| Tout le monde                              | voir les publications activées, pendant leur période, sous réserve de leur mot de passe ou connexion |
| Détenteur d'un lien de partage             | voir cette publication, quels que soient son état et sa protection                                  |
| Utilisateur connecté ayant le droit de voir | voir les publications réservées aux utilisateurs connectés                                         |
| Éditeur                                    | modifier la publication, la voir désactivée, télécharger ses fichiers quels que soient ses réglages  |
| Opérateur                                  | gérer en plus ses liens de partage et changer son profil                                             |
| Créateur de la publication, administrateur | tout, y compris la suppression                                                                       |

Les droits sont accordés par les administrateurs d'Expose.

### Ce qui ne se modifie pas dans Expose

Certaines choses ne sont pas modifiables dans l'interface d'Expose :

- la **description** et les **traductions** d'une publication, sa **CSS**, son **logo** et sa
  **date** ;
- les **assets** d'une publication (titre, description, ordre, fichiers) : ils viennent de
  Databox ;
- le **thème** d'une publication : chaque visiteur choisit son propre thème ;
- la mise en page **Carte**, pas encore disponible.

Les assets changent en modifiant le panier (ou la recherche enregistrée) dans Databox. Les autres
réglages sont modifiés par les administrateurs d'Expose.

## Intégrer une publication ou un média dans un site

Une publication, ou l'un de ses assets seul, peut s'afficher dans une page d'un autre site web
avec une `iframe`. Les administrateurs d'Expose doivent d'abord autoriser ce site. Aucun bouton
ne génère le code : demandez-leur l'adresse à intégrer, ou consultez la
[documentation technique](../../tech/Expose/iframe.md).

Dans une page intégrée, la connexion ouvre une fenêtre séparée : autorisez les fenêtres
surgissantes du site.

## Questions et limites

- **J'ai révoqué un lien de partage, mais un téléchargement fonctionne encore.** Les liens de
  téléchargement déjà donnés restent valables jusqu'à leur expiration (24 heures, 3 jours pour
  les liens envoyés par e-mail).
- **Ma publication ne s'ouvre pas par son slug.** Les slugs `s`, `auth`, `embed`, `api`,
  `profiles` et `publications` sont réservés par Expose : choisissez-en un autre.
- **Et la mise en page Carte ?** Elle n'est pas encore disponible : une publication réglée sur
  **Carte** s'affiche en grille.
- **Le visiteur a accepté les conditions sur un autre ordinateur.** L'acceptation est mémorisée
  par chaque navigateur : les conditions s'affichent de nouveau sur un autre.
- **L'archive ne contient pas un nouvel asset.** L'archive est reconstruite quand la publication
  change : demandez-la de nouveau.
- **Pas de bouton d'archive.** L'archive est proposée quand le téléchargement est activé et que
  la publication a plus d'un asset.

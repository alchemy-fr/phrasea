---
title: 'Guide utilisateur de Databox'
---

# Guide utilisateur de Databox

Databox est l'application de gestion des médias de Phrasea : elle stocke vos assets (photos, vidéos, fichiers audio, documents PDF et autres fichiers), les décrit par des attributs, et permet de les rechercher, de les modifier, de les partager, de les exporter et de les publier.

Ce guide décrit le client web de Databox pour ses utilisateurs. Ce que vous voyez dépend de vos permissions : une action que vous n'avez pas le droit d'effectuer est masquée ou grisée. Les écrans d'administration (réglages des espaces de travail, attributs, rendus, permissions, intégrations, workflows…) sont décrits dans la documentation technique : voir [Administration](../../tech/Databox/administration.md).

## L'interface

La fenêtre de Databox se compose d'une barre supérieure, d'un panneau latéral à gauche et de la zone des résultats.

### Barre supérieure

De gauche à droite, la barre supérieure contient :

- le bouton qui affiche ou masque le panneau latéral ;
- le logo, qui ramène à la recherche ;
- le chemin de l'écran en cours lorsqu'un écran est ouvert par-dessus la recherche (par exemple, l'éditeur par lot) ;
- **Tous les services**, si votre organisation l'a configuré : la liste des autres applications de Phrasea ;
- **Notifications** (icône de cloche), voir [Suivi et notifications](#suivi-et-notifications) ;
- le menu des réglages (icône d'engrenage), voir [Réglages](#réglages) ;
- votre menu utilisateur : **Mon compte** et **Se déconnecter**. Si vous n'êtes pas connecté, le bouton **Se connecter** le remplace.

Deux boutons ronds flottent au-dessus de la fenêtre : **Ajouter des assets** (en bas à droite de l'écran de recherche) ouvre la fenêtre de téléversement, et **Signaler un problème** (en bas à gauche), si votre organisation l'a activé, envoie un signalement à l'équipe de support.

### Panneau latéral

Le panneau latéral comporte trois onglets :

- **Facettes** : les valeurs présentes dans les résultats en cours, pour affiner la recherche (voir [Facettes](#facettes)).
- **Navigation** : les sections **Stories épinglées** (lorsque vous avez épinglé au moins une story), **Recherches enregistrées** et **Espaces de travail et collections**.
- **Paniers** : vos paniers (voir [Paniers](#paniers)).

Dans l'onglet **Navigation**, cliquez sur le titre d'une section pour la replier ou la déplier. Pour changer l'ordre des sections, faites glisser la poignée qui apparaît au survol du titre d'une section (ou donnez-lui le focus et utilisez les touches ↑ et ↓).

Faites glisser le bord du panneau pour le redimensionner.

### Zone des résultats

Au-dessus des résultats, une barre d'outils affiche :

- à gauche, la case qui sélectionne ou désélectionne tous les assets, le bouton du panier (voir [Le panier courant](#le-panier-courant)) et, lorsque des assets sont sélectionnés, les boutons d'action (voir [Actions sur les assets](#actions-sur-les-assets)) ;
- à droite, le nombre de résultats (ou d'assets sélectionnés) et le bouton **Paramètres d'affichage**.

### Paramètres d'affichage

Le bouton **Paramètres d'affichage** ouvre les options suivantes :

- **Disposition** : **Grille**, **Mosaïque**, **Liste** ou **Carte** (la carte n'est proposée que si votre organisation l'a configurée, voir [Vue carte](#vue-carte)).
- **Taille des vignettes** : grille, mosaïque et liste.
- **Ajustement des vignettes** : **Image entière** ou **Remplir** (grille et liste).
- **Afficher l'aperçu au survol** : affiche un aperçu plus grand de l'asset lorsque le pointeur s'arrête sur la pastille du type de fichier, en bas à droite d'une vignette (cliquez sur la pastille pour garder l'aperçu ouvert). Avec cette option, vous choisissez aussi **Afficher le fichier dans l'aperçu**, **Afficher les attributs dans l'aperçu**, **Taille de l'aperçu** et **Ratio fichier / attributs**.
- **Lire les médias au survol** : lit les vidéos et les fichiers audio sur les vignettes.
- **Lecture automatique des médias dans l'aperçu**.

Ces réglages sont enregistrés dans vos préférences, et dans votre profil d'affichage lorsqu'il est synchronisé (voir [Profils d'affichage et filtres épinglés](#profils-daffichage-et-filtres-épinglés)).

## Espaces de travail et collections

### Définitions

**Espace de travail** : un espace qui contient des collections et des assets. Ses réglages (langues, attributs, listes d'entités, rendus, permissions…) s'appliquent à tout ce qu'il contient. Un asset appartient toujours à un seul espace de travail.

**Collection** : un dossier d'assets dans un espace de travail. Les collections sont hiérarchiques : une collection peut contenir des sous-collections. Chaque collection a sa propre confidentialité et ses propres permissions.

Un asset a une collection de référence, et peut aussi figurer dans d'autres collections du même espace de travail sous forme de raccourci (voir [Déplacer et copier](#déplacer-et-copier)).

### Parcourir l'arborescence

La section **Espaces de travail et collections** de l'onglet **Navigation** affiche les espaces de travail et les collections auxquels vous avez accès. Cliquez sur la flèche d'un espace de travail ou d'une collection pour le déplier.

- Cliquez sur le nom d'un espace de travail ou d'une collection pour afficher ses assets : la recherche est limitée à celui-ci.
- Utilisez le champ **Rechercher des collections…** pour retrouver une collection par son nom.
- Une icône de dossier pleine signale une collection qui n'est pas publique ; une petite icône de groupe, une collection partagée ; un nom barré, une collection dans la corbeille.

En bas de la section, **Corbeille** affiche les assets supprimés (voir [Suppression et corbeille](#suppression-et-corbeille)) et **Quarantaine** ouvre les assets en attente de vérification (voir [Quarantaine](#quarantaine)).

Le bouton ⋮ qui apparaît au survol d'un espace de travail, ou un clic droit sur celui-ci, ouvre son menu : **Épingler**, **Ajouter une collection**, **Ajouter un asset**, **Importer des assets…** et **Gérer l'espace de travail**. Le menu d'une collection contient **Épingler**, **Notifications**, **Ajouter un asset**, **Importer des assets…**, **Créer une sous-collection**, **Modifier** et **Supprimer** (ou **Restaurer** pour une collection dans la corbeille).

### Créer une collection

1. Ouvrez le menu de l'espace de travail et choisissez **Ajouter une collection**, ou ouvrez le menu d'une collection et choisissez **Créer une sous-collection**.
2. Dans la fenêtre **Nouvelle collection**, saisissez le nom et choisissez la confidentialité.
3. Cliquez sur **Créer**.

Vous pouvez aussi créer une collection à la volée depuis le sélecteur de destination des fenêtres de téléversement, de déplacement, de copie et d'enregistrement : cliquez sur **Nouvelle collection** sous l'espace de travail ou la collection où vous la voulez, saisissez le nom et appuyez sur Entrée.

### Gérer une collection

Choisissez **Modifier** dans le menu d'une collection pour ouvrir la fenêtre **Gérer la collection**. Ses onglets sont :

- **Infos** : identifiant, propriétaire, dates, confidentialité, espace de travail et chemin.
- **Modifier** : le nom et la confidentialité. Le nom peut être traduit : l'onglet **Par défaut** contient le nom principal, et chaque langue de l'espace de travail a son propre onglet.
- **Notifications** : les événements de la collection que vous suivez (voir [Suivi et notifications](#suivi-et-notifications)).
- **Permissions** : les utilisateurs et les groupes autorisés sur la collection, et les permissions héritées de son parent.
- **Opérations** : **Déplacer la collection**, pour choisir son nouveau parent dans le même espace de travail, et la **Zone de danger**, pour la supprimer.

Vous pouvez aussi déplacer une collection en la faisant glisser sur une autre collection ou sur un espace de travail (pour la placer à la racine).

Une collection supprimée part dans la corbeille avec ses assets : saisissez son nom pour confirmer. Pour la restaurer, choisissez **Restaurer** dans son menu.

### Épingler un espace de travail ou une collection

**Épingler**, dans le menu d'un espace de travail ou d'une collection, crée une recherche enregistrée limitée à celui-ci, listée dans **Recherches enregistrées** : un clic vous y ramène ensuite.

## Ajouter des assets

### Ouvrir la fenêtre de téléversement

La fenêtre **Ajouter des assets** s'ouvre :

- avec le bouton **Ajouter des assets** (icône +) en bas à droite de l'écran de recherche ;
- avec **Ajouter un asset** dans le menu d'un espace de travail ou d'une collection, qui le présélectionne comme destination ;
- en déposant des fichiers de votre ordinateur n'importe où sur l'écran de recherche ;
- en collant un fichier ou une image (⌘V sur macOS, Ctrl+V ailleurs) lorsque le curseur n'est pas dans un champ de texte.

Lorsque la recherche est limitée à un espace de travail ou à une collection, celui-ci est présélectionné comme destination.

### Téléverser des fichiers

1. Dans l'onglet **Fichiers**, déposez vos fichiers ou cliquez pour parcourir votre ordinateur. **Ajouter d'autres fichiers** en ajoute d'autres, et la croix d'un fichier le retire.
2. Sous **Destination**, choisissez l'espace de travail ou la collection. Vous pouvez créer une collection à la volée avec **Nouvelle collection**.
3. Choisissez la **Confidentialité** (voir [Confidentialité](#confidentialité)).
4. Renseignez les **Attributs** si vous le souhaitez (voir [Pré-indexer les attributs](#pré-indexer-les-attributs)).
5. Cliquez sur le bouton **Téléverser**, qui indique le nombre de fichiers.

La fenêtre se ferme aussitôt et les fichiers sont envoyés en arrière-plan : un message de progression s'affiche en bas de la fenêtre (voir [Téléversements en attente](#téléversements-en-attente)).

L'option **Silencieux (pas de notification, pas de webhook)** crée les assets sans prévenir les utilisateurs qui suivent la destination et sans appeler les webhooks de l'espace de travail.

### Ajouter des fichiers depuis des URL

Dans l'onglet **URLs**, saisissez une URL par ligne (commençant par `http://` ou `https://`). Avec **Importer les fichiers** coché (par défaut), les fichiers sont copiés dans Databox ; décoché, les assets référencent les URL distantes.

### Créer un asset sans fichier

Dans l'onglet **Sans fichier**, saisissez le **Titre de l'asset** et cliquez sur **Créer l'asset**. L'asset est créé sans fichier : vous pourrez en ajouter un plus tard depuis sa visionneuse, avec **Ajouter un fichier source**.

### Téléverser sous forme de story

Cochez **Créer une story avec ces fichiers** pour regrouper les fichiers téléversés dans une nouvelle story (voir [Stories](#stories)) :

1. Saisissez le **Titre de la story**.
2. Choisissez la **Couverture de la story** parmi les fichiers, ou cliquez sur **Utiliser comme couverture de la story** à côté d'un fichier.
3. Renseignez si besoin les attributs de la story.

Dans l'onglet **Sans fichier**, **Créer une story** crée une story vide, à laquelle vous pourrez ajouter des assets plus tard.

### Confidentialité

La confidentialité détermine qui peut voir un asset ou une collection :

- **Secret** : seuls le propriétaire et les utilisateurs auxquels l'accès a été accordé.
- **Privé (les utilisateurs peuvent demander l'accès)** : les utilisateurs voient qu'il existe, mais pas son contenu, sauf si l'accès leur a été accordé.
- **Public** : son contenu est ouvert.

Pour **Privé** et **Public**, **Visible uniquement par les utilisateurs de l'espace de travail** limite cela aux utilisateurs de l'espace de travail. Pour **Public**, **L'utilisateur doit être authentifié** n'ouvre le contenu qu'aux utilisateurs connectés ; sans cette option, les visiteurs anonymes le voient aussi.

Au téléversement, **Non défini (hériter)** (par défaut) applique la confidentialité de la destination. Une collection ou un asset est toujours au moins aussi ouvert que sa collection parente : les niveaux plus restrictifs que le parent sont désactivés, et un message rappelle la confidentialité héritée.

### Pré-indexer les attributs

La section **Attributs** de la fenêtre de téléversement affiche les attributs de l'espace de travail de destination : les valeurs saisies s'appliquent à tous les fichiers téléversés. Les attributs traduisibles ont un onglet par langue de l'espace de travail, pour renseigner plusieurs langues à la fois.

### Modèles de valeurs

Un modèle de valeurs mémorise des valeurs d'attributs, et si besoin une confidentialité, pour les réutiliser lors des téléversements suivants.

Pour créer un modèle :

1. Renseignez les attributs et la confidentialité dans la fenêtre de téléversement.
2. Ouvrez **Enregistrer comme modèle** et saisissez le **Nom du modèle**.
3. Choisissez ses options :
    - **Mémoriser la collection** : le modèle n'est proposé que pour la collection de destination ; **Y compris les sous-collections** le propose aussi dans ses sous-collections. Sans cette option, **Dans toutes les collections** le propose dans tout l'espace de travail.
    - **Attributs** et **Confidentialité** : ce que le modèle conserve.
    - **Public** : partage le modèle avec les autres utilisateurs.
    - **Remplacer le modèle appliqué** : lorsqu'un modèle est appliqué, le met à jour au lieu d'en créer un nouveau.
4. Cliquez sur **Enregistrer le modèle**.

Lors des téléversements suivants, le champ **Modèles de valeurs** affiche les modèles disponibles pour la destination : choisissez un ou plusieurs modèles dans **Appliquer un modèle…** pour remplir le formulaire.

### Téléversements en attente

Pendant un téléversement, un message en bas de la fenêtre affiche la progression. Cliquez dessus pour ouvrir **Téléversements en attente** : la progression de chaque fichier, les fichiers envoyés et ceux en échec. La croix d'un fichier annule son envoi, et **Effacer les terminés** retire de la liste les envois terminés.

### Importer depuis un fichier JSON ou NDJSON

Pour créer de nombreux assets d'un coup à partir d'un fichier qui les décrit (URL des sources, collections, attributs…) :

1. Choisissez **Importer des assets…** dans le menu d'un espace de travail ou d'une collection.
2. Déposez le fichier JSON ou NDJSON dans **Fichier d'instructions** : un aperçu indique le nombre d'entrées et celles qui seront rejetées. **Format du fichier** décrit les champs attendus.
3. Vérifiez le **Format**, la **Destination** et la **Confidentialité**.
4. Cliquez sur **Importer**.

L'import se déroule en arrière-plan. Suivez sa progression avec **Voir les imports**, ou dans l'onglet **Imports** de **Gérer l'espace de travail**, qui liste les imports avec leur statut, leurs compteurs et leurs entrées rejetées.

### Phrasea Uploader

En plus de Databox, des fichiers peuvent être envoyés avec Phrasea Uploader, une application dédiée au téléversement (formulaires, liens de dépôt pour des personnes sans compte…) : voir le [guide utilisateur d'Uploader](../Uploader/uploader-documentation.fr.md).

## Rechercher

### Recherche textuelle et suggestions

Saisissez un ou plusieurs mots dans le champ **Rechercher des assets…** et appuyez sur Entrée (ou cliquez sur **Rechercher**). Les mots trouvés dans les attributs sont surlignés dans les résultats. La croix du champ efface le texte.

Pendant la saisie, des suggestions apparaissent (utilisez ↑ et ↓, puis Entrée, ou cliquez) :

- les assets, collections et espaces de travail dont le nom correspond : une collection ou un espace de travail limite la recherche à celui-ci ;
- **Filtrer par** un champ, pour un mot unique qui correspond au nom d'un attribut ou d'un champ intégré (saisissez `@` pour lister les champs intégrés) : le champ s'écrit dans la barre de recherche sous la forme `champ:`, puis ses valeurs sont suggérées ;
- les valeurs du champ choisi : en choisir une ajoute une condition.

Le bouton avec une icône de viseur, **Rechercher autour de moi**, ajoute votre position à la recherche (votre navigateur vous demande l'autorisation).

Le menu **Plus** de la barre de recherche contient **Effacer la recherche** (texte, conditions et tri), **Enregistrer la recherche** ou, pour une recherche enregistrée, **Mettre à jour la recherche** et **Enregistrer comme nouvelle recherche** (voir [Recherches enregistrées](#recherches-enregistrées)).

### Conditions

Les conditions filtrent les résultats sur un champ. Elles s'affichent sous forme de pastilles sous la barre de recherche.

- Cliquez sur le texte d'une pastille pour modifier la condition.
- Son bouton ⋮ (ou un clic droit) propose **Désactiver** ou **Activer**, **Inverser (NOT)** (ou **Annuler l'inversion**), **Modifier** et **Retirer**. Une condition inversée s'affiche en rouge, une condition désactivée est barrée.

Pour ajouter une condition :

1. Cliquez sur **Ajouter une condition**.
2. Dans l'onglet **Assistant**, choisissez un champ dans **Sélectionner un champ…** (champs **Intégrés** comme la collection, les dates, le type de fichier ou la confidentialité, ou vos **Attributs**), puis un opérateur (**Est**, **Est l'un de**, **Contient**, **Commence par**, **Est avant le**, **Est vide**… selon le type du champ) et une valeur.
3. Ajoutez d'autres conditions avec **Ajouter une condition**, ou des groupes de conditions avec **Ajouter un groupe de conditions**. Chaque groupe retient **Toutes (ET)** ou **Au moins une (OU)** de ses conditions.
4. Cliquez sur **Ajouter**.

**Aperçu AQL** montre la requête écrite par l'assistant. L'onglet **AQL** permet de l'écrire directement. L'interrupteur **Inverser (NOT)** inverse toute la condition.

Des filtres épinglés peuvent aussi s'afficher avant les pastilles (voir [Profils d'affichage et filtres épinglés](#profils-daffichage-et-filtres-épinglés)).

### Facettes

L'onglet **Facettes** liste, pour les résultats en cours, les valeurs des champs configurés comme facettes, avec le nombre d'assets pour chaque valeur.

- Cochez une ou plusieurs valeurs pour ne garder que les assets correspondants (plusieurs valeurs d'une même facette se combinent en OU). **Manquant** garde les assets sans valeur.
- Pour une date, déplacez les poignées du curseur pour choisir une période ; **Effacer le filtre** la retire.
- Les boutons qui apparaissent au survol du titre d'une facette l'épinglent en haut (**Épingler en haut**) ou la masquent (**Masquer la facette**).

Une valeur cochée ajoute une condition, que vous pouvez ensuite inverser ou désactiver comme toute autre condition.

Le menu ⋮ de l'onglet contient **Tout déplier**, **Tout replier** et **Paramètres des facettes** : faites glisser les facettes pour les réordonner, masquez-les ou affichez-les (les facettes masquées sont listées sous **Masquées**), ou cliquez sur **Rétablir par défaut**.

Les facettes elles-mêmes sont choisies par les administrateurs de l'espace de travail.

### Trier

Cliquez sur **Trier** pour ouvrir **Trier par** :

1. Activez les champs sur lesquels trier avec leur interrupteur, ou ajoutez un attribut avec **Ajouter un tri…**.
2. Faites glisser les champs pour choisir leur ordre, et cliquez sur la flèche d'un champ pour choisir le sens.
3. Cochez **Grouper par sections** pour séparer les résultats selon le premier champ (indisponible lors du tri par pertinence).
4. Cliquez sur **Appliquer**. **Réinitialiser** rétablit le tri par défaut.

Par exemple, triez sur un attribut « Pays » puis sur un attribut « Ville », et groupez par sections : les résultats sont listés par pays, puis par ville, avec un séparateur pour chaque pays.

### Recherches enregistrées

Une recherche enregistrée conserve le texte, les conditions et le tri d'une recherche.

1. Ouvrez le menu **Plus** de la barre de recherche et choisissez **Enregistrer la recherche**.
2. Saisissez le nom et choisissez la confidentialité : **Secrète** (seulement vous et les utilisateurs que vous autorisez), **Privée** (accessible par lien, non listée) ou **Publique** (listée pour tous). Publier une recherche demande une permission particulière.
3. Cliquez sur **Enregistrer**.

Les recherches enregistrées sont listées dans la section **Recherches enregistrées** de l'onglet **Navigation** : cliquez sur l'une d'elles pour la lancer. Après l'avoir modifiée, **Mettre à jour la recherche** enregistre les changements, et **Enregistrer comme nouvelle recherche** en crée une autre.

Le menu ⋮ d'une recherche enregistrée contient **Modifier**, qui ouvre **Gérer la recherche enregistrée** (onglets **Infos**, **Modifier**, **Permissions** et **Automatisations**), et **Supprimer**.

Les automatisations appliquent des actions aux résultats d'une recherche enregistrée, à la demande ou selon un calendrier : ajout à une collection, modification d'un attribut, partage, publication dans Expose… Chaque automatisation d'une recherche enregistrée figure dans son menu, avec **Exécuter maintenant** et **Simulation**.

### Profils d'affichage et filtres épinglés

Un profil d'affichage mémorise l'affichage de Databox : les attributs affichés dans le panneau de l'asset, les attributs affichés sur les vignettes de la grille, les filtres de recherche épinglés au-dessus des résultats, et vos préférences d'affichage (disposition, facettes, sections du panneau latéral, onglets du panneau de l'asset, thème…).

Les profils se choisissent dans le menu des réglages, sous **Profil d'affichage** :

- **Changer de profil** liste le profil par défaut (**Profil d'affichage par défaut**) et les profils que vous avez créés ou qui ont été partagés avec vous.
- **Synchroniser les préférences** enregistre vos changements dans le profil au fur et à mesure ; sans cette option, **Enregistrer les préférences actuelles dans le profil** les enregistre à la demande.
- **Nouveau profil…** crée un profil, et **Gérer les profils…** les liste.

La fenêtre d'un profil comporte les onglets **Général** (nom et description), **Attributs de l'asset** (les attributs affichés dans le panneau de l'asset, dans l'ordre, avec des séparateurs), **Carte de la grille** (les valeurs affichées sur ou sous les vignettes), **Filtres de recherche** et **Permissions** (**Public** pour partager le profil avec tous les utilisateurs).

Vous pouvez aussi ajouter un attribut à votre profil depuis le panneau de l'asset, avec **Épingler dans le profil**.

Les filtres de recherche épinglés s'affichent avant les conditions, au-dessus des résultats. Cliquez sur l'un d'eux pour choisir sa valeur (une liste, des boutons radio, ou les dates **Du** et **Au** pour une date), puis cliquez sur **Appliquer**. **Effacer** retire la valeur.

### Vue carte

Choisissez **Carte** dans **Paramètres d'affichage** pour placer les résultats sur une carte, d'après leur attribut de géolocalisation (un attribut de type « point géographique »). Les assets proches sont regroupés dans des bulles qui indiquent leur nombre ; cliquez sur une bulle pour les voir et zoomer, et cliquez sur un asset pour l'ouvrir.

- **Dessiner une zone** : cliquez et faites glisser sur la carte pour chercher dans un cercle (Échap annule). Faites glisser la zone pour la déplacer, ou son bord pour la redimensionner ; **Effacer la zone** la retire.
- **Rechercher dans cette zone**, dans la bulle d'un groupe, limite la recherche à sa zone ; **Effacer la zone** retire cette limite.
- **Afficher tous les résultats** centre la carte sur les résultats, et **Vue satellite** change le fond de carte.

Lorsque les espaces de travail ont plusieurs attributs de géolocalisation, choisissez celui à utiliser en haut à gauche de la carte.

### Recherche par similarité

Lorsque la similarité est activée pour l'espace de travail, **Rechercher des similaires**, dans le menu d'un asset, lance une recherche des assets visuellement similaires. Chaque résultat affiche sa **Similarité** en pourcentage. L'onglet **Similaires** de la visionneuse affiche les assets les plus similaires, et **Rechercher les assets similaires** les ouvre dans la recherche.

### Visages

Lorsque l'intégration de reconnaissance faciale est activée, son onglet dans la visionneuse liste les visages de l'image. Cliquez sur **Détecter les visages** pour l'analyser.

- Pour nommer un visage **Sans nom**, saisissez un nom dans son champ **Qui est-ce ?**, puis choisissez une personne existante ou créez-en une nouvelle.
- Un visage **Reconnu** a été nommé automatiquement d'après un autre visage ; **Confirmer la personne** en fait une référence.
- **Oublier ce visage** le supprime.

Les personnes sont enregistrées dans un attribut de l'asset : recherchez une personne avec la facette ou une condition sur cet attribut.

## Consulter un asset

### Ouvrir la visionneuse

Double-cliquez sur un asset, ou choisissez **Ouvrir** dans son menu. La visionneuse affiche le fichier à gauche et un panneau à droite.

- **Précédent** et **Suivant** (ou les touches ← et →) parcourent les résultats. Dans une story, ils parcourent ses éléments : à la fin de la story, appuyez de nouveau pour passer à l'asset suivant.
- La liste en haut permet d'afficher un autre rendu.
- Lorsque l'asset a une région d'intérêt, **Afficher l'asset entier** et **Afficher la région d'intérêt** basculent l'affichage.
- Les boutons de l'en-tête téléchargent, modifient, partagent ou suppriment l'asset ; son menu ⋮ contient les autres actions (voir [Actions sur les assets](#actions-sur-les-assets)). **Suivre** vous abonne à l'asset (voir [Suivi et notifications](#suivi-et-notifications)).
- **Masquer le panneau** donne toute la place au fichier. Le bouton de fermeture (ou Échap) ferme la visionneuse.

Les images se zooment avec la molette ; un double-clic bascule entre l'image entière et sa taille réelle. Pour un asset sans fichier, **Ajouter un fichier source** permet d'en ajouter un.

### Onglets du panneau

Le panneau comporte les onglets suivants, selon vos permissions et la configuration de l'espace de travail :

- **Infos** : attributs et informations (voir ci-dessous).
- **Discussion** : messages et annotations (voir [Discuter](#discuter)).
- **Rendus** : les rendus du fichier.
- **Similaires** : les assets similaires (voir [Recherche par similarité](#recherche-par-similarité)).
- un onglet par intégration disponible pour le fichier (par exemple l'éditeur photo ou la reconnaissance faciale) ;
- **Versions** : les fichiers source précédents.
- **Permissions** : qui est autorisé sur l'asset.
- **Workflow** : les exécutions des traitements de l'asset.
- **Opérations** : emplacement, raccourcis et suppression.

Les onglets qui ne tiennent pas sont dans le menu ⋮ de la barre d'onglets. **Réorganiser les onglets**, dans ce menu, permet de faire glisser les onglets pour changer leur ordre (**Rétablir par défaut** le rétablit).

### Onglet Infos

L'onglet **Infos** se compose de sections :

- **Attributs** : les valeurs des attributs. Au survol d'un attribut, des boutons permettent de changer son format d'affichage (**Changer le format**), de copier sa valeur, ou de l'épingler dans votre profil (**Épingler dans le profil**).
- **Informations** : propriétaire, dates de création et de modification, confidentialité, espace de travail, collection de référence, fichier source et type.
- **Collections** et **Stories** : les collections et les stories auxquelles appartient l'asset ; cliquez sur l'une d'elles pour l'ouvrir dans la recherche.
- **Métriques** : l'audience de l'asset (vues, lectures…), lorsque votre organisation la mesure.
- **Pièces jointes** : des fichiers joints à l'asset. **Ajouter une pièce jointe** ajoute un fichier ou une URL ; le menu ⋮ d'une pièce jointe la télécharge, la renomme (**Renommer**), la détache (**Détacher**, l'asset joint est conservé) ou la supprime.

### Onglet Rendus

Les rendus sont les versions du fichier générées pour l'affichage et le téléchargement (vignette, aperçu, autres formats…). L'onglet affiche chaque rendu avec son type et sa taille ; cliquez sur un rendu pour l'afficher dans la visionneuse. Des badges signalent un rendu en cours de génération (**Génération…**), **Verrouillé**, **Substitué** ou **Obsolète**.

- **Télécharger** et **Enregistrer sous…** s'appliquent au rendu.
- **Substituer** remplace le fichier d'un rendu par un fichier de votre choix, et **Téléverser** fournit un rendu pas encore généré (pour les rendus qui le permettent).
- **Créer un rendu personnalisé** (images) crée un nouveau rendu : choisissez la **Source**, le **Rapport d'aspect** et le **Zoom** (faites glisser l'image pour la cadrer), la **Largeur max** et la **Hauteur max**, le **Format**, **Noir et blanc** et **Écrire les attributs dans les métadonnées du fichier**, puis cliquez sur **Créer**.

### Onglet Versions

Lorsque le fichier source est remplacé, le fichier précédent est conservé comme version. L'onglet liste les versions avec leur date, leur type et leur taille ; chacune peut être téléchargée, supprimée, ou enregistrée avec **Enregistrer sous…**. Pour restaurer une version, choisissez **Enregistrer sous…** puis **Remplacer le fichier source de cet asset**.

### Onglet Workflow

Les workflows exécutent les traitements des assets (analyse, génération des rendus, intégrations…). L'onglet liste les exécutions avec leur statut ; **Voir** ouvre le détail d'une exécution, où une tâche peut être relancée avec **Relancer**. **Relancer le workflow** exécute de nouveau le workflow de l'asset.

Pendant une exécution, une carte au-dessus du fichier affiche sa progression ; **Ouvrir l'exécution** l'ouvre.

### Onglet Opérations

- **Emplacement** : l'espace de travail et la collection de référence de l'asset.
- **Raccourcis** : les autres collections où figure l'asset ; **Retirer** l'en retire.
- **Zone de danger** : supprimer (ou restaurer) l'asset ; saisissez son nom pour confirmer.

### Intégrations

Selon la configuration de l'espace de travail, d'autres onglets apportent des intégrations, par exemple :

- l'éditeur photo : **Ouvrir l’éditeur photo** ouvre l'image dans un éditeur ; **Enregistrer sous** enregistre le résultat, que vous pouvez ensuite rouvrir depuis l'onglet ;
- la reconnaissance faciale (voir [Visages](#visages)) ou la détection d'étiquettes et de textes.

## Modifier les attributs

### Modifier un asset

1. Ouvrez l'asset et cliquez sur le bouton de modification (crayon) de l'en-tête, ou choisissez **Modifier** dans le menu de l'asset.
2. Le panneau passe en mode modification : changez la confidentialité et les valeurs des attributs. Un attribut traduisible a un onglet par langue ; un attribut multivalué a un bouton pour ajouter une valeur et un bouton pour retirer chacune d'elles.
3. Cliquez sur **Enregistrer**. **Réinitialiser** annule vos changements, et **Fermer l’éditeur** quitte le mode modification.

Les attributs que vous ne pouvez pas modifier sont marqués « lecture seule ».

### Modifier par lot

1. Sélectionnez plusieurs assets du même espace de travail.
2. Cliquez sur **Modifier les attributs**. L'éditeur par lot s'ouvre avec les assets sélectionnés en haut.
3. Dans la liste des attributs, choisissez un attribut (Tab et Maj+Tab passent au suivant et au précédent).
4. Saisissez la valeur. Elle s'applique aux assets sélectionnés en haut : cliquez sur une vignette pour travailler sur elle seule, ⌘-clic sur macOS (Ctrl-clic ailleurs) ou Maj-clic pour en sélectionner plusieurs.
    - Pour un attribut à valeur unique, **Indéterminé** signale que les assets ont des valeurs différentes : une valeur saisie s'applique à tous.
    - Pour un attribut multivalué, chaque valeur indique la part des assets qui l'ont, avec un bouton pour l'ajouter à tous les assets sélectionnés et un bouton pour la retirer ; **Ajouter à tous** ajoute une nouvelle valeur.
5. À droite, l'onglet **Valeurs** liste les valeurs utilisées (**Appliquer à la sélection**, **Sélectionner des assets**), et l'onglet **Aperçu** montre le résultat asset par asset.
6. Cliquez sur **Enregistrer**, vérifiez le récapitulatif dans **Confirmer les modifications ?**, puis confirmez.

**Annuler** et **Rétablir** annulent ou refont vos derniers changements avant l'enregistrement.

## Actions sur les assets

### Sélectionner des assets

- Cliquez sur un asset pour le sélectionner ; un ⌘-clic sur macOS (Ctrl-clic ailleurs) ajoute ou retire un asset ; un Maj-clic sélectionne une plage.
- ⌘A sur macOS (Ctrl+A ailleurs) sélectionne tous les assets chargés, et Échap vide la sélection.

### Barre d'actions et menus

Lorsque des assets sont sélectionnés, la barre d'outils affiche les actions disponibles pour eux : **Télécharger** (un asset) ou **Exporter** (plusieurs), **Modifier** ou **Modifier les attributs**, **Partager**, **Déplacer**, **Copier**, **Créer une story…** et **Supprimer**. Dans la corbeille, **Restaurer** et **Supprimer définitivement** les remplacent.

Le bouton ⋮ d'une vignette, ou un clic droit sur celle-ci, ouvre le menu de l'asset (ou de toute la sélection, si l'asset est sélectionné). Il contient, en plus des actions ci-dessus :

- **Ouvrir** et **Infos**, qui ouvrent la visionneuse ;
- **Rechercher des similaires** (voir [Recherche par similarité](#recherche-par-similarité)) ;
- **Épingler la story** pour une story (voir [Stories](#stories)) ;
- **Ajouter au panier** ;
- **Enregistrer sous…** et **Remplacer le fichier source** (voir [Remplacer le fichier source](#remplacer-le-fichier-source)) ;
- **Modifier la région d'intérêt** (voir [Région d'intérêt](#région-dintérêt)).

### Glisser-déposer

Faites glisser des assets (ou la sélection) sur une collection ou un espace de travail de l'onglet **Navigation**, sur un panier de l'onglet **Paniers**, ou sur une story épinglée. Pendant le glissement, le panneau latéral passe sur l'onglet **Navigation** ; survolez un autre onglet pour l'ouvrir, ou un espace de travail ou une collection repliés pour les déplier. L'étiquette qui suit le pointeur indique ce que fera le dépôt.

| Touches maintenues | Sur une collection ou un espace de travail | Sur une story |
|---|---|---|
| aucune | Déplacer | Ajouter |
| ⌘ sur macOS, Ctrl ailleurs | Ajouter (raccourci) | Déplacer |
| ⇧⌘ sur macOS, Ctrl+Maj ailleurs | Dupliquer | Dupliquer |

Un dépôt sur un panier ajoute toujours les assets. Un dépôt dans un autre espace de travail les duplique toujours, après confirmation.

### Exporter et télécharger

1. Sélectionnez les assets et cliquez sur **Exporter** (ou **Télécharger** pour un seul asset).
2. Cochez les rendus à inclure dans l'export.
3. Lorsque des assets ont une région d'intérêt ou un extrait de panier, les fichiers exportés sont découpés et recadrés : cochez **Exporter les fichiers entiers (ignorer les régions d'intérêt et les extraits)** pour les exporter entiers.
4. Si un espace de travail exige l'acceptation de ses conditions générales, lisez-les et acceptez-les.
5. Cliquez sur **Exporter**.

Un message indique la préparation de l'export, puis **Export prêt !** avec un bouton **Télécharger**.

### Déplacer et copier

**Déplacer** change la collection des assets, à l'intérieur de leur espace de travail. Choisissez la **Destination**, puis cliquez sur **Déplacer**.

**Copier** ajoute les assets à une autre collection :

- avec **Copier par référence (raccourci)** (par défaut), le même asset figure dans la collection de destination : une modification de l'asset apparaît dans les deux collections ;
- sans cette option, l'asset est dupliqué : un nouvel asset est créé, avec ses propres attributs si **Copier les attributs** est coché.

Une copie dans un autre espace de travail duplique toujours les assets.

### Remplacer le fichier source

**Remplacer le fichier source** remplace le fichier de l'asset par un nouveau fichier, téléversé ou donné par son URL. Le fichier précédent est conservé dans l'onglet **Versions** et les rendus sont générés de nouveau.

**Enregistrer sous…** enregistre le fichier source (ou un rendu, ou une version) comme :

- **Nouvel asset** : une copie du fichier dans un nouvel asset, dans la collection de votre choix ;
- **Rendu de cet asset** : le fichier de l'un de ses rendus ;
- **Remplacer le fichier source de cet asset** : le nouveau fichier source de l'asset.

### Région d'intérêt

**Modifier la région d'intérêt** (images, vidéos et fichiers audio) définit la partie de l'asset à montrer et à utiliser : un rectangle de recadrage et, pour les vidéos et les fichiers audio, des plages temporelles. Le fichier n'est pas modifié : la région d'intérêt est affichée par défaut, et appliquée lorsque l'asset est publié ou exporté.

- **Recadrage** : tracez le rectangle sur le média, puis déplacez-le ou redimensionnez-le par ses coins ; choisissez un ratio (**Libre**, **Original**, 1:1, 4:3…).
- **Plages temporelles** : faites glisser sur la ligne de temps pour ajouter une plage, ou cliquez sur **Ajouter une plage** ; faites glisser ses bords, ou utilisez **Commencer à la position courante** et **Finir à la position courante**. Les plages sont lues l'une après l'autre.

**Supprimer la région d'intérêt** la supprime.

### Suppression et corbeille

1. Sélectionnez les assets et cliquez sur **Supprimer**.
2. Si les assets sont dans des collections, choisissez **Placer dans la corbeille**, ou **Retirer seulement des collections** et cochez les collections dont les retirer.
3. Confirmez.

Les assets supprimés partent dans la corbeille. Cliquez sur **Corbeille** en bas de la section **Espaces de travail et collections** pour les voir. Là, **Restaurer** les remet dans leurs collections (si leur collection est aussi supprimée, restaurez-la d'abord), et **Supprimer définitivement** les supprime pour de bon : saisissez « Supprimer » pour confirmer.

### Partager

**Partager** crée des liens publics vers un ou plusieurs assets du même espace de travail.

- Activez **Créer un lien public**, puis cliquez sur **Copier le lien**. Pour un asset seul, choisissez si le lien ouvre la page de l'asset ou un rendu donné. Les boutons en dessous partagent le lien sur les réseaux sociaux, et **Code d'intégration** donne le code pour insérer l'asset dans une page web.
- Pour révoquer le lien, désactivez **Créer un lien public**.

**Avancé : plusieurs liens avec audience et dates** permet de créer plusieurs liens, par exemple un par destinataire :

1. Cliquez sur **Créer un nouveau lien de partage**.
2. Renseignez **Nom / audience** et, si besoin, **Débute le** et **Expire le**. Activez **Protéger par un mot de passe** pour demander un mot de passe (au moins 4 caractères) aux personnes qui ouvrent le lien : le lien n'ouvre alors que sa page, il ne donne aucun lien direct vers les fichiers, même pour l'intégration.
3. Cochez les **Rendus partagés** : seuls ces rendus peuvent être consultés et téléchargés depuis le lien.
4. Cliquez sur **Créer**.

Chaque lien peut ensuite être modifié (nom, dates, mot de passe : **Changer** ou **Retirer**), intégré (**Code d'intégration**) ou révoqué (**Révoquer**).

Les personnes qui ouvrent un lien voient les assets partagés, peuvent les parcourir et télécharger les rendus partagés (**Télécharger…**, **Tout télécharger…**). Si le lien est protégé par un mot de passe, elles le saisissent d'abord : il reste valable 24 heures dans leur onglet, et trop de mots de passe erronés bloquent le lien pendant un moment. Si l'espace de travail a des conditions générales, elles doivent d'abord les accepter.

## Stories

Une story est un asset qui regroupe d'autres assets dans un ordre donné, par exemple les pages d'un rapport ou les photos d'un événement. Elle a ses propres attributs et une couverture.

Pour créer une story :

1. Sélectionnez les assets du même espace de travail et choisissez **Créer une story…**.
2. Saisissez le **Nom de la story** et choisissez la **Couverture de la story**.
3. Confirmez.

Vous pouvez aussi créer une story lors d'un téléversement (voir [Téléverser sous forme de story](#téléverser-sous-forme-de-story)), ou ajouter des assets à une story en les faisant glisser dessus (voir [Glisser-déposer](#glisser-déposer)).

Ouvrez une story pour voir ses éléments dans un bandeau sous le fichier ; cliquez sur un élément pour l'afficher. Si vous pouvez modifier la story, faites glisser les éléments pour les réordonner, et faites un clic droit sur un élément pour **Définir comme couverture**, **Placer au début**, **Placer à la fin** ou **Retirer de la story**.

**Épingler la story**, dans le menu d'une story, l'ajoute à la section **Stories épinglées** de l'onglet **Navigation** ; **Désépingler la story** l'en retire.

## Collaboration

### Annoter

Les annotations tracent des formes et des textes sur une image, une vidéo ou un PDF, pour désigner une zone précise.

1. Ouvrez l'asset, puis l'onglet **Discussion**, et cliquez sur **Annoter le média**.
2. Dans la barre d'annotation, choisissez un outil : **Sélectionner**, **Rectangle**, **Cercle**, **Visage**, **Flèche**, **Ligne**, **Cible**, **Texte** ou **Dessin**. Pour une vidéo, **Ajouter un repère à l'instant courant** marque un moment.
3. Choisissez la **Couleur**, le **Remplissage** et la **Taille** (ou la **Taille de police** d'un texte), puis tracez sur le média.
4. Sélectionnez une forme pour la déplacer, la redimensionner, la renommer (**Renommer**), la dupliquer (**Dupliquer**) ou la supprimer.
5. Écrivez votre message et envoyez-le : les annotations y sont jointes.

Dans un message, cliquez sur une annotation pour l'afficher sur le média. Certains attributs enregistrent aussi des annotations : leur bouton d'annotation se trouve dans le mode modification du panneau. **Masquer les annotations** masque les formes, et **Afficher les annotations du média** les affiche de nouveau.

### Discuter

L'onglet **Discussion** de la visionneuse rassemble les messages sur l'asset. Saisissez votre texte dans **Écrire un message…** et appuyez sur Entrée pour l'envoyer (Maj+Entrée pour aller à la ligne).

- **Mentionner quelqu’un** (ou saisir `@`) prévient un utilisateur.
- **Joindre des fichiers** ajoute des fichiers (vous pouvez aussi les déposer ou les coller), **Emoji** insère un emoji, et **Afficher la mise en forme** affiche la barre de mise en forme (gras, italique, lien, listes, citation, code…).
- **Répondre** répond à un message ; le menu ⋮ de vos messages permet de les modifier ou de les supprimer.

Les messages sont visibles par les utilisateurs qui ont accès à l'asset.

### Suivi et notifications

**Suivre**, dans l'en-tête de la visionneuse, vous abonne à l'asset ; sa flèche permet de choisir les sujets : **Mises à jour**, **Suppression** et **Nouveaux commentaires**. **Ne plus suivre** met fin à l'abonnement.

Pour suivre une collection, choisissez **Notifications** dans son menu, puis choisissez les sujets : **Asset ajouté**, **Asset retiré**, **Asset mis à jour** et **Nouveau commentaire**.

La cloche **Notifications** de la barre supérieure liste vos notifications ; cliquez sur l'une d'elles pour ouvrir ce qu'elle concerne. **Tout marquer comme lu** les marque toutes comme lues.

## Paniers

Un panier est une sélection d'assets, conservée temporairement ou durablement, pour travailler sur eux ensemble : les exporter, les partager, les publier dans une galerie web… Un même asset peut figurer plusieurs fois dans un panier.

### Accéder aux paniers

L'onglet **Paniers** du panneau latéral liste vos paniers, avec leur nombre d'éléments. Cliquez sur un panier pour l'ouvrir. **Afficher les archivés** affiche aussi les paniers archivés.

### Créer un panier

1. Cliquez sur **Créer un panier** (bouton + de l'onglet **Paniers**).
2. Saisissez un nom et, si besoin, une description.
3. Cliquez sur **Enregistrer**.

### Le panier courant

Le panier courant reçoit les assets que vous ajoutez. Le bouton du panier, dans la barre d'outils des résultats, affiche son nom et son nombre d'éléments :

- lorsque des assets sont sélectionnés, le bouton les ajoute au panier courant (**Ajouter la sélection au panier**) ;
- sinon, il ouvre le panier courant (**Ouvrir le panier**).

La flèche du bouton (**Changer de panier**) liste vos paniers, pour choisir un autre panier courant. Vous pouvez aussi choisir **Définir comme panier courant** dans le menu d'un panier, ou double-cliquer dessus dans l'onglet **Paniers**. Un nouveau panier devient le panier courant.

### Ajouter des assets à un panier

- Sélectionnez des assets et cliquez sur le bouton du panier de la barre d'outils.
- Choisissez **Ajouter au panier** dans le menu d'un asset.
- Faites glisser les assets sur un panier de l'onglet **Paniers**.

### Travailler dans un panier

La vue du panier affiche ses éléments dans l'ordre, avec leur position. Sélectionnez des éléments pour utiliser les actions sur les assets (export, partage…) ; **Retirer du panier** les retire du panier sans les supprimer.

Le menu ⋮ d'un panier, dans l'onglet **Paniers**, contient **Définir comme panier courant**, **Modifier**, **Archiver** (ou **Désarchiver**) et **Supprimer**. Un panier archivé est masqué de la liste.

### Découper et recadrer les éléments d'un panier

**Découper / recadrer**, sur un élément du panier (images, vidéos et fichiers audio), définit un extrait pour cet élément seulement : un rectangle de recadrage et, pour les vidéos et les fichiers audio, des plages temporelles (comme pour la [région d'intérêt](#région-dintérêt)). Le fichier n'est pas modifié : l'extrait s'applique lorsque l'élément est lu, publié ou exporté, et remplace la région d'intérêt de l'asset. **Retirer l'extrait** le supprime.

Un badge sur l'élément indique son extrait, ou la région d'intérêt qu'il hérite de l'asset.

### Partager des paniers

Vous pouvez partager un panier avec d'autres utilisateurs de Databox : choisissez **Modifier** dans le menu du panier, allez dans l'onglet **Permissions** de la fenêtre **Gérer le panier**, ajoutez les utilisateurs ou les groupes et choisissez leurs permissions.

### Publier des paniers

Les paniers peuvent être publiés sous forme de galeries web grâce au service Phrasea Expose : la publication suit le panier, chaque asset ajouté ou retiré lui est envoyé.

1. Ouvrez le panier : faites un clic droit sur son titre puis **Modifier**, ou cliquez sur le bouton **Intégrations** de la vue du panier.
2. Dans la fenêtre **Gérer le panier**, allez dans l'onglet **Intégrations**.
3. La première fois, cliquez sur **Autoriser** sous l'intégration Expose et connectez-vous.
4. Cliquez sur **Nouvelle publication**, donnez-lui un titre (et si besoin un slug, une description, une publication parente ou un profil), puis cliquez sur **Créer et synchroniser**.

La synchronisation peut ensuite être relancée (**Forcer la synchronisation**) ou arrêtée (**Arrêter**, en supprimant si besoin la publication dans Expose). La mise en page, les téléchargements, le mot de passe, les conditions et les autres réglages de la publication se règlent dans Expose.

Pour poursuivre, vous devez avoir accès à Expose. Voir le [guide utilisateur d'Expose](../Expose/expose-documentation.fr.md) pour la procédure complète et les réglages des publications.

## Quarantaine

Lorsque l'analyse d'un fichier téléversé le rejette (par exemple un doublon possible, ou un contenu inattendu), l'asset est mis en quarantaine : il attend qu'un utilisateur l'accepte, le fusionne ou le supprime. Un asset en quarantaine affiche le bandeau **Cet asset est en quarantaine** ; **Détail de l'analyse** affiche le rapport d'analyse et les doublons possibles.

**Quarantaine**, en bas de la section **Espaces de travail et collections**, ouvre la liste des assets à vérifier ; l'icône à côté les affiche plutôt dans la recherche.

1. Sélectionnez un asset dans la liste (ou cliquez sur **Résoudre** dans son bandeau).
2. Lisez le rapport d'analyse et les **Doublons possibles**.
3. Choisissez une action :
    - **Contourner** : accepter l'asset tel quel ;
    - **Fusionner les doublons** : choisir, pour chaque attribut, la valeur à garder sur l'asset existant, puis choisir de **Conserver le fichier entrant comme nouvelle version de l'asset existant** ou de **Rejeter le fichier entrant** ;
    - **Placer dans la corbeille** ou **Supprimer définitivement**.

L'asset suivant s'affiche alors ; **Passer** va au suivant sans décider. Cochez plusieurs assets pour appliquer une action à tous.

## Conditions générales

Un espace de travail peut exiger l'acceptation de ses conditions générales. À l'ouverture de Databox, une fenêtre les affiche : cochez **J'ai lu et j'accepte les conditions générales** et cliquez sur **Accepter** pour accéder au contenu de l'espace de travail. **Plus tard** reporte la décision jusqu'au prochain rechargement de la page.

Les conditions sont aussi demandées lorsque vous exportez des assets de l'espace de travail, et sur les pages de partage.

## Réglages

Le menu des réglages (icône d'engrenage) de la barre supérieure contient :

- **Profil d'affichage** : voir [Profils d'affichage et filtres épinglés](#profils-daffichage-et-filtres-épinglés).
- **Thème** :
    - **Apparence** : **Clair**, **Sombre** ou **Système** (suit votre appareil) ;
    - **Thème** : les couleurs et le style de l'interface, parmi **Par défaut** et les thèmes intégrés (**Océan**, **Forêt**, **Coucher de soleil**…), ou le thème de votre organisation sous **Organisation**.
- **Langue** : la langue de l'interface. **Langue des données…** choisit la langue utilisée pour les valeurs des attributs et les noms traduits ; **Par défaut — utiliser la langue de l'interface** suit la langue de l'interface.
- **Cookies**, lorsque votre organisation mesure l'audience avec des cookies : les accepter ou les refuser.

Les administrateurs trouvent d'autres entrées dans ce menu (tâches d'opération, workflows, intégrations, journal d'audit, permissions de l'instance) : voir [Administration](../../tech/Databox/administration.md).

## Signaler un problème

Si votre organisation l'a activé, le bouton **Signaler un problème** (en bas à gauche de la fenêtre) envoie un signalement à l'équipe de support :

1. Choisissez le **Type** : **Anomalie**, **Amélioration** ou **Question**.
2. Renseignez le **Résumé** et les **Détails**.
3. Si besoin, ajoutez une **Capture d'écran** : cliquez sur **Capturer cette page**, ou collez une image.
4. Cliquez sur **Créer le ticket**.

La page sur laquelle vous êtes et votre session sont jointes au signalement.

## Raccourcis clavier

Sur macOS, utilisez ⌘ là où le tableau indique Ctrl.

| Touches | Où | Action |
|---|---|---|
| Ctrl+A | Résultats, panier | Sélectionner tous les assets chargés |
| Échap | Résultats, panier | Vider la sélection |
| Ctrl+V | Écran de recherche, hors champ de texte | Téléverser les fichiers collés |
| Entrée | Barre de recherche | Lancer la recherche, ou appliquer la suggestion en surbrillance |
| ↑ / ↓ | Barre de recherche | Parcourir les suggestions |
| Échap | Barre de recherche | Fermer les suggestions |
| ← / → | Visionneuse | Asset précédent / suivant |
| Échap | Visionneuse | Fermer la visionneuse |
| Espace ou K | Vidéo | Lecture / pause |
| M / F / C | Vidéo | Muet / plein écran / sous-titres |
| Tab / Maj+Tab | Éditeur par lot | Attribut suivant / précédent |
| Entrée | Discussion | Envoyer le message (Maj+Entrée : nouvelle ligne) |
| Ctrl+B / Ctrl+I | Discussion | Gras / italique |
| Suppr | Annotations | Supprimer la forme sélectionnée |
| Flèches | Annotations | Déplacer la forme sélectionnée (Maj : plus vite) |
| Espace (maintenue) | Annotations | Déplacer l'image au lieu de dessiner |
| Échap | Annotations | Annuler, quitter l'outil, puis désélectionner |
| ← / → | Ligne de temps de la région d'intérêt ou d'un extrait | Avancer d'une image (Maj : une seconde) |
| I / O | Ligne de temps | Début / fin de la plage à la tête de lecture |
| + / − / 0 | Ligne de temps | Zoomer / dézoomer / durée totale |
| Ctrl+U | Partout | **Changer d'utilisateur…** (utilisateurs autorisés à naviguer en tant qu'un autre utilisateur) |

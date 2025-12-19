---
status: en attente de relecture
title: Notification Phrasea
---

> **Statut :** _Travail en cours_  
> Cette page est en cours de rédaction et peut être fréquemment mise à jour.

# Notifications dans Phrasea

# Implémentation du framework Novu dans Phrasea

Novu est une infrastructure open-source de notifications conçue pour aider les développeurs et les équipes produit à créer, gérer et diffuser des notifications multicanal.  
Elle fournit les outils et le framework nécessaires pour centraliser les besoins de communication de l’application, sur différents canaux.  
Phrasea utilise Novu pour envoyer des notifications d’événements survenant dans le DAM, par exemple lorsqu’un nouvel asset est ajouté au DAM.

Pour plus d’informations sur Novu, consultez la [documentation officielle](https://docs.novu.co/platform/concepts/notifications).

# Liste des notifications disponibles

| Workflow | Description | Destinataire par défaut | Qui peut s’abonner | Canal |
| :---- | :---- | :---- | :---- | :---- |
| basic | Message pouvant être émis depuis l’administration Databox | personne | Toute personne ayant accès à l’application peut s’abonner à ce type de notification | in app, email |
| databox-user-exception | Envoyé à tout utilisateur rencontrant une erreur après une action sur l’interface | Utilisateur ayant rencontré l’erreur | Personne | in app |
| databox-collection-asset-add | Lorsqu’un asset est créé | Propriétaire de la collection | Tout utilisateur pouvant gérer la collection | in app |
| databox-collection-asset-remove | Lorsqu’un asset est supprimé | Propriétaire de la collection | Tout utilisateur pouvant gérer la collection | in app |
| databox-asset-update | Lorsqu’une modification d’indexation d’un asset est effectuée | Propriétaire(s) de la collection et de l’asset | Tout utilisateur pouvant gérer la collection | in app |
| databox-discussion-new-comment | Lorsqu’un utilisateur ajoute un commentaire ou une réponse sur un asset | Propriétaire de l’asset | Toute personne pouvant commenter ou voir l’asset | in app, email avec digest |
| uploader-commit-acknowledged | Lorsqu’un utilisateur envoie des fichiers via une cible uploader et que ces fichiers sont ajoutés à une collection | L’utilisateur qui ajoute les fichiers | Personne | email |
| expose-zippy-download-link | Lorsqu’un utilisateur télécharge des assets | L’utilisateur demandeur | Personne | email |
| expose-download-link | Lorsqu’un utilisateur reçoit un lien de téléchargement | L’utilisateur demandeur | Personne | email |

##
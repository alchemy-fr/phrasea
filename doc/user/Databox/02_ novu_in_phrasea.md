---
status: pending review
title: Phrasea notification
---

> **Status:** _Work In Progress_  
> This page is currently being drafted and may be updated frequently.

# Phrasea’s notifications.

# Implementation of Novu framework In Phrasea.

Novu is an open-source notification infrastructure designed to help developers and product teams build, manage, and deliver multi-channel notifications.  
It provides the tools and framework to handle application's communication needs in one place, across various channels.  
Phrasea uses Novu to send event notifications happening in the DAM, e.g., when a new asset is added to the DAM.

For more information on Novu, please refer to the official Novu's [documentation](https://docs.novu.co/platform/concepts/notifications).

# List of available Notifications 

| Workflow | Description | Who is the default receiver | who can subscribe | Channel |
| :---- | :---- | :---- | :---- | :---- |
| basic | Message can be emitted from databox admin  | nobody | Anyone can acceded to application and subscribing to this of notification | in app email |
| databox-user-exception | Send to any user who encounter error after a Gui action | User who encounter this error  | Nobody | in app |
| databox-collection-asset-add | When an asset is created | collection owner | Any users who can manage to the collection  | in app  |
| databox-collection-asset-remove | When an asset is erased | collection owner | Any users who can manage to the collection  | in app |
| databox-asset-update | When an asset’s indexation modification is performed  | collections owner assets owner | Any users who can manage to the collection  | in app |
| databox-discussion-new-comment | When a user add comment or answer on an assets | assets owner | Anyone can comments on asset, anyone can see assets  | in app email with digest |
| uploader-commit-acknowledged | When a user sends files through an uploader target and when this files is added to a collection.  | The user who adding file’s  | Nobody | email |
| expose-zippy-download-link | When a user download assets from  | the requester user | Nobody | email |
| expose-download-link | When a user received  | the requester user | Nobody | email |

##

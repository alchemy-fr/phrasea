---
title: 'Administration'
---

# Administration

The Databox API serves a back office built on EasyAdmin, at `/admin` of the API host
(`https://api-databox.${PHRASEA_DOMAIN}/admin`, from `DATABOX_API_URL`). It gives the admins a
raw view of every entity, key figures, and the technical screens (queues, failed messages,
Elasticsearch indices, populate, realtime). The day-to-day admin screens (workflows, tasks, audit
log, instance permissions and integrations) are in the Databox client.

The dashboard is `databox/api/src/Controller/Admin/DashboardController.php`
(`#[AdminDashboard(routePath: '/admin', routeName: 'easyadmin')]`); its base class, the shared
pages and fields come from `lib/php/admin-bundle` (`Alchemy\AdminBundle`).

## Access

- **Sign-in**: the `admin` firewall (`databox/api/config/packages/security.yaml`) is stateful and
  authenticates through `Alchemy\AuthBundle\Security\OAuthAuthorizationAuthenticator`: an
  anonymous visitor is redirected to Keycloak (authorization code flow) and comes back on
  `/admin/oauth/check`. `/admin/logout` signs out.
- **Role**: `access_control` requires `ROLE_ADMIN` on `^/admin`. `ROLE_ADMIN` is given by the
  Keycloak realm role `admin` or `databox-admin` (`Alchemy\AuthBundle\Security\RoleMapper`); the
  realm role `tech` gives `ROLE_TECH`. A signed-in user without `ROLE_ADMIN` gets the
  **Not allowed!** page with a **Logout** button (`AdminAccessDeniedHandler`).
- **Admins pass every check**: `Alchemy\AuthBundle\Security\Voter\AdminVoter` grants any
  attribute other than `ROLE_ADMIN` itself to a token holding `ROLE_ADMIN`. The pages and menus
  restricted to `ROLE_TECH` (the **Dev** menu, **Queues**, **PHP Info**) are therefore open to
  every admin, while a `ROLE_TECH` user without `ROLE_ADMIN` is stopped by the `access_control` rule.

:::warning
Because of `AdminVoter`, `Actions::disable()` neither hides nor forbids an action for an admin.
A CRUD controller that must stay read-only removes its write actions and refuses them server
side: see [Read-only screens](#read-only-screens).
:::

## Home page

The home page (`AbstractAdminDashboardController::index()`, template
`@AlchemyAdmin/home.html.twig`) shows three kinds of cards. Each card is computed on its own: a
failing source (database, Elasticsearch) only turns its own card into an error card,
and the error is logged. The key figures are cached for 5 minutes (`HOME_CACHE_LIFETIME = 300`,
pool `alchemy_admin.home.cache`).

**Key figures**, with the counts of the **Last 24 hours**, **Last 7 days** and **Last 30 days**
when the source is dated; each card links to its list:

| Card                     | Source                                                                |
| ------------------------ | --------------------------------------------------------------------- |
| **Assets**               | Elasticsearch count of the non-deleted assets (never SQL)             |
| **Collections**          | non-deleted collections                                               |
| **Workspaces**           | non-deleted workspaces (total only)                                   |
| **Workflows**            | workflow states, by start date                                        |
| **Failed workflows**     | workflow states in failure                                            |
| **Exports**              | asset exports                                                         |
| **Shares**               | shares                                                                |
| **Baskets**              | baskets                                                               |
| **Active users (30 days)** | distinct users of the audit log over the last 30 days               |
| **Failed messages**      | messages of the Messenger `failed` transport                          |

**Charts**, computed by Elasticsearch aggregations on the non-deleted assets
(`databox/api/src/Admin/Home/AssetHomeCharts.php`):

| Chart                        | Aggregation                                                              |
| ---------------------------- | ------------------------------------------------------------------------ |
| **Assets created (30 days)** | date histogram per day on `createdAt`                                    |
| **Assets by file family**    | terms on `fileFamily` (10 buckets, the assets without family included)   |
| **Assets by workspace**      | terms on `workspaceId` (top 10), with the sum of `fileSize` (**Total size**) |

**Latest activity**: the 10 latest **Latest assets** (from Elasticsearch, with their status),
**Audit log** entries, **Workflows** (with their status) and **Exports**, each with a link to its
detail page and the user's name, resolved from the identity provider.

## Menu

The menu (`DashboardController::configureMenuItems()`) groups the CRUD screens by domain:

| Section           | Entries                                                                                                                                                                                                                                                                                       |
| ----------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Home**          | the home page                                                                                                                                                                                                                                                                                 |
| **Permissions**   | **Workspace permissions**, **Collection permissions**, **Asset permissions**, **Basket permissions**, **All permissions (advanced)**                                                                                                                                                          |
| **Assets**        | **Workspaces**, **Collections**, **Collection Access**, **Collection Assets**, **Assets**, **Asset Relationships**, **Asset File Versions**, **Asset Imports**, **Asset Import Errors**, **Files**, **File Analyses**, **File Duplicates**, **Attachments**, **Alternate URLs**, **Asset Embeddings**, **Asset Policies**, **Asset Exports**, **Multipart Uploads** |
| **Attributes**    | **Attribute Definitions**, **Attributes**, **Attribute Entities**, **Entity Lists**, **Attribute Policies**, **Attribute Filter Rules**                                                                                                                                                       |
| **Renditions**    | **Rendition Definitions**, **Rendition Policies**, **Asset Renditions**                                                                                                                                                                                                                       |
| **Templates**     | **Workspace Templates**, **Data Templates**, **Template Attributes**                                                                                                                                                                                                                          |
| **Sharing**       | **Shares**, **Baskets**, **Basket Assets**, **Threads**, **Messages**, **Pages**, **Terms Versions**, **Terms Signatures**                                                                                                                                                                    |
| **Searches**      | **Profiles**, **Profile Items**, **Saved Searches**, **Saved Search Automations**, **Automation Runs**, **Automation Shares**                                                                                                                                                                 |
| **Workflows**     | **Workflow States**, **Job States**                                                                                                                                                                                                                                                           |
| **Integrations**  | **Integrations**, **Integration Data**, **Integration Tokens**, **Env Vars**, **Secrets**, **Help**; then a **Webhooks** section: **Webhooks**, **Webhook Errors**                                                                                                                            |
| **Logs**          | **Audit Log**, **Change Log**                                                                                                                                                                                                                                                                 |
| **Maintenance**   | **Operation Tasks**, **Populate Passes**, **ES Indices & Aliases**                                                                                                                                                                                                                            |
| **Notifications** | **Broadcasts**, **Subscribers**, **In-app Notifications**, **Pending Digests**, **Preferences** (`Alchemy\NotifierBundle\Admin\NotifierAdminMenu`)                                                                                                                                            |
| **Settings**      | **Global Config**, **Realtime**                                                                                                                                                                                                                                                               |
| **Dev**           | **Messenger Failed Events**, **Queues**, **PHP Info** (`ROLE_TECH`, see [Access](#access))                                                                                                                                                                                                   |

The list pages show **detail**, **edit** and **delete** as icon buttons, their label as a
tooltip.

## Pages and actions

### Permissions

The CRUD controllers of the objects carrying ACLs (workspaces, collections, assets, baskets, but
also data templates, attribute and rendition policies, pages, profiles and saved searches) extend
`Alchemy\AdminBundle\Controller\Acl\AbstractAclAdminCrudController`, which adds two actions:

- **Global permissions** (`globalPermissions`): the access control entries applying to every
  object of the type (ACEs without object id). The four first entries of the **Permissions**
  menu open it. It only edits the permission mask of these ACEs; the instance capabilities they
  also carry are granted from the client (see [Instance permissions](./permissions.md)).
- **Permissions** (row action): the ACEs of one object.

**All permissions (advanced)** lists every access control entry
(`AccessControlEntryCrudController`). The permission model is described in
[Access control](./access-control.md).

### Assets, workflows and workspaces

| Screen                | Action                | Effect                                                                                                    |
| --------------------- | --------------------- | --------------------------------------------------------------------------------------------------------- |
| **Assets**            | **Trigger Ingest**    | dispatches the ingest workflow of the asset (`AssetIngestWorkflowEvent`), the admin being its initiator  |
| **Workspaces** (detail) | **Save as template** | creates a workspace template from the workspace and opens it in **Workspace Templates**                  |
| **Workflow States**   | **View**              | opens the workflow in the Databox client (`/workflows/[id]`, new tab)                                     |
| **Workflow States**   | **Cancel Workflow**   | cancels a started workflow                                                                                |
| **Job States**        | **Retry job**         | retries a failed job                                                                                      |
| **Job States**        | **Rerun job**         | runs again a job that is not in failure                                                                   |
| **Job States**        | **Cancel job**        | cancels the workflow of a running job                                                                     |

See [Workflows](./workflows.md).

### Imports, relations, terms and messages

These screens list what the client and the workers create; they create and edit nothing:

- **Asset Imports** and **Asset Import Errors**: the imports (workspace, collection,
  integration, format, status, source, `total`, `created`, `skipped`, `failed` counters) and their
  errors (line, key, message); filters on workspace, collection, status, format, owner, dates and
  error. Imports can be deleted, errors cannot. See [Import](./import.md).
- **Asset Relationships**: the relations between assets (type, `sticky`, source, target, source
  file, integration), filterable by each of them. They can be deleted, not created or edited.
- **Terms Versions** and **Terms Signatures**: the versions of the terms of each workspace (text
  or PDF, translations, checksum) and who signed which version. Versions are fully read-only;
  signatures can be deleted. See [Workspace terms](./workspace-terms.md).
- **Threads** and **Messages**: the discussion threads and their messages (author, content,
  attachments, files). Messages can be deleted, not created or edited.

### Messages and queues

- **Queues** (`Alchemy\AdminBundle\Controller\QueuesListController`, route
  `easyadmin_queues_list`): one row per AMQP Messenger transport (the queue is named after the
  transport), with its **Message count** and **Consumer count**; a queue missing on RabbitMQ is
  shown **Not declared** after a `messenger:setup-transports` attempt. **Inspect** summarizes
  the first pending messages of a queue (up to `alchemy_admin.worker.inspect_limit`, 1000 by
  default), grouped by message **Type** (and by job id for the workflow jobs) with their
  **Count**. To do so it reads the messages and publishes a copy of each at the end of the queue:
  nothing is lost nor retried, but the inspected messages lose their position. The queues
  themselves are described in [Workers](./workers.md).
- **Messenger Failed Events** (`Alchemy\MessengerBundle\Controller\MessengerMessageCrudController`):
  the messages of the `failed` transport (`doctrine://default?queue_name=failed`, the failure
  transport of every bus), newest first, with their queue, type and error,
  filterable by queue, type, dates and **Retried**. **Retry** (row, detail page and batch action)
  sends a message back to its transport once; **Retry all** retries every message not retried yet,
  whatever the current filter. **Purge all** deletes every message of the table, retried or not,
  after a confirmation dialog then a page where the number of messages to delete must be typed;
  messages failing after that page is displayed are kept.

### Search index

- **Populate Passes**: the populate runs, per index (progress, duration, memory, document count,
  error). **Add Populate** queues a populate of all indices or of the selected ones (`ESPopulate`
  messages on the `task` transport), refused while one of them is still running; the same page
  force-releases the lock of an index left by a dead worker. See [Populate](./es_populate.md).
- **ES Indices & Aliases** (`Alchemy\ESBundle\Controller\Admin\ESIndexAdminController`): the
  logical indices of the FOS Elastica configuration, the physical indices (health, status,
  documents, size, shards, aliases) and the aliases, with actions to delete, open, close or refresh
  an index, reset a logical index, clean its old indices, and add, remove or switch an alias.

### Tasks and logs

- **Operation Tasks**: the operation tasks (task, owner, payload, status, dates); they are
  started from the client, the admin has no **New** button. See [Operation tasks](./operation-tasks.md).
- **Audit Log**: the audit log entries, read-only. See [Audit log](./audit-log.md).
- **Change Log**: the field changes recorded by `Alchemy\TrackBundle`.

### Integrations

**Integrations**, **Integration Data**, **Integration Tokens**, **Env Vars** and **Secrets** are
the CRUD screens of the workspace integrations and of their storage (see
[Integrations](./integration/01_integrations.md) and [Secrets](./secrets.md)). **Help** (route
`admin_integrations_help`, `/admin/integrations/help`) lists every available integration with its
YAML configuration reference.

### Settings

- **Global Config** (`Alchemy\ConfiguratorBundle\Controller\ConfiguratorEntryCrudController`): the
  global configuration entries shared by the applications; **Push Configuration** dispatches
  `DeployConfig`, which writes them to the configurator bucket. See [Configuration](./configuration.md).
- **Realtime** (`Alchemy\AdminBundle\Controller\RealtimeStatusController`, `ROLE_ADMIN`): the
  state of the Sockudo server (status, app id, occupied channels, subscriptions), the
  **Connected users** with their number of connections, and the **Channels by type**. See
  [Realtime](./realtime.md).
- **PHP Info** (in **Dev**): the `phpinfo()` of the API container.

## Read-only screens

`Alchemy\AdminBundle\Controller\ReadOnlyCrudTrait` makes a CRUD controller read-only: its
`removeWriteActions()` removes **New**, **Edit**, **Delete** and the batch delete from the list
and detail pages, and the `new`, `edit`, `delete` and `batchDelete` actions throw
`ForbiddenActionException` whatever URL is called. It is used by **Audit Log**
(`AuditLogCrudController`).

The other screens only remove some actions from their pages (`Actions::remove()`), which hides
them; only `ReadOnlyCrudTrait` also refuses them server side:

| Screen                                                                                                                                                         | Removed from the list | Removed from the detail page |
| -------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------- | ---------------------------- |
| **Asset Import Errors**, **File Analyses**, **File Duplicates**, **Collection Assets**, **Terms Versions**, **Saved Search Automations**, **Automation Runs** | new, edit, delete     | edit, delete                 |
| **Asset Imports**, **Asset Relationships**, **Asset File Versions**, **Asset Embeddings**, **Terms Signatures**, **Messages**, **Automation Shares**, **Integration Tokens** | new, edit             | edit                         |
| **Assets**, **Asset Renditions**, **Integration Data**, **Multipart Uploads**, **Operation Tasks**, **Job States**, **Workflow States**, **Populate Passes**     | new, edit             |                              |
| **Asset Exports**                                                                                                                                              | new, edit, delete     |                              |
| **Attributes**, **Basket Assets**, **Attribute Filter Rules**, **Files**                                                                                       | new                   |                              |

The notifier screens (**Subscribers**, **In-app Notifications**, **Pending Digests**,
**Preferences**, **Broadcasts**) declare their write actions disabled (`Actions::disable()`) and
remove them from their pages; **Broadcasts** keeps **New**, which sends a broadcast (see [Notifications](./notifications.md)).

## Client admin screens

The Databox client has its own admin screens, under `/admin` of the client
(`databox/client/src/app/(app)/admin/`), shown to the users holding the `admin` or
`databox-admin` role:

| Route                  | Entry (settings menu of the top bar)       | Page                                                  |
| ---------------------- | ------------------------------------------ | ----------------------------------------------------- |
| `/admin/tasks`         | **Operation tasks**                        | [Operation tasks](./operation-tasks.md)               |
| `/admin/workflows`     | **Workflows**                              | [Workflows](./workflows.md)                           |
| `/admin/integrations`  | **Instance integrations**                  | [Integrations](./integration/01_integrations.md)      |
| `/admin/audit-log`     | **Audit log**                              | [Audit log](./audit-log.md)                           |
| `/admin/permissions`   | **Instance permissions**                   | [Instance permissions](./permissions.md)              |
| `/admin/theme`         | **Customize theme…** (theme menu)          | theme editor of the organization                      |

## Configuration

| Setting                                                         | Effect                                                                                          |
| --------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| `alchemy_admin.service` (`databox/api/config/packages/alchemy_admin.yaml`) | title (`Databox`) and name of the service                                         |
| `alchemy_admin.worker.rabbitmq`                                 | connection of the **Queues** page (`RABBITMQ_HOST`, `RABBITMQ_PORT`, `RABBITMQ_USER`, `RABBITMQ_PASSWORD`, `RABBITMQ_VHOST`; `RABBITMQ_SSL` for TLS) |
| `alchemy_admin.worker.inspect_limit`                            | messages read by **Inspect** per queue (1000)                                                   |
| `DISPLAY_SERVICES_MENU`, `DASHBOARD_CLIENT_URL`                 | show the Phrasea services menu of the Dashboard in the admin layout                             |

The Traefik IP allowlist of the technical consoles does not apply to `/admin`: it is protected by
the Keycloak sign-in and `ROLE_ADMIN` only (see
[Administration consoles](../01_setup.md#administration-consoles)).

## See also

- [Console commands](./console-commands.md)
- [Workers](./workers.md)
- [Instance permissions](./permissions.md)
- [Populate](./es_populate.md)
- [Operation tasks](./operation-tasks.md)
- [Audit log](./audit-log.md)

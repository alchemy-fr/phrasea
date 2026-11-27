---
title: 'Databox'
---

# Databox

Databox is the core application of Phrasea: the Digital Asset Management service that stores
the assets, their files and renditions, their metadata and attributes, organizes them in
workspaces and collections, and makes them searchable. Every other application (Uploader,
Expose, the Dashboard) and every integration goes through its API: the database is only reached
through this API envelope, which applies the permissions and the business rules.

## Components

| Component | Compose service | Role |
| --- | --- | --- |
| API | `databox-api-php` (php-fpm) behind `databox-api-nginx` | The Symfony API (`databox/api`): REST resources (API Platform), the admin (EasyAdmin), the [MCP server](./mcp.md). |
| Workers | `databox-worker` | Consume the Messenger transports (RabbitMQ queues): indexing, renditions, workflows, integrations, notifications… See [Workers](./workers.md) for the queues and their priorities. |
| Scheduler | `databox-scheduler` | Runs the recurring tasks of the `main` schedule (`WORKER_PRIORITIES=scheduler_main`): purges, retentions, safety nets. |
| Client | `databox-client` | The web client (`databox/client`): Next.js (App Router), React 19, TypeScript. |
| Indexer | none (runs outside the stack) | The [Databox indexer](https://github.com/alchemy-fr/databox-indexer) imports existing content (directory, S3, Phraseanet) through the API; the configurator creates its OAuth client and its bucket. |

## Stack

- PHP 8.5, Symfony 8.1, API Platform 5, Doctrine ORM 3 (PostgreSQL);
- Elasticsearch (FOS Elastica), for every listing and search of assets and collections: the
  assets are never queried in SQL, so that their permissions apply in the query;
- RabbitMQ, the transport of Symfony Messenger;
- Redis (cache, locks);
- S3-compatible storage (MinIO in dev) for the files;
- Sockudo, a Pusher-compatible server, for the real-time updates of the client;
- Keycloak for the authentication (OpenID Connect).

## Concepts

- **Workspace**: the top-level container, with its own members, settings, attribute and
  rendition definitions, and integrations. See [Access control](./access-control.md) and
  [Workspace terms](./workspace-terms.md).
- **Collection**: a node of the collection tree of a workspace; an asset belongs to one
  reference collection and may be added to others. See
  [Filtering assets by collection](./asset_collection_filters.md) and
  [Collection search](./collection_search.md).
- **Asset**: a digital item, with its source file, its renditions and its attributes. See
  [Asset lifecycle](./asset-lifecycle.md).
- **Story**: an asset grouping other assets, in order. See [Stories](./stories.md).
- **Rendition**: a version of the file of an asset built for a use (main, preview,
  thumbnail…), from a rendition definition. See [Renditions](./Renditions.mdx).
- **Attribute**: a value of an asset for an attribute definition (text, number, date,
  entity…), possibly translated. See [Metadata](./metadata.md) and
  [Attribute initial values](./Attributes.mdx).
- **Display profile**: the attributes, layout and display options a user applies to the
  results. See [Display profiles](./display-profiles.md).
- **Basket**: a selection of assets a user collects, shares or publishes. See
  [Baskets](./baskets.md).
- **Share**: a public link giving access to assets and some of their renditions. See
  [Shares](./shares.md).

## Pages

### Concepts and permissions

- [Access control](./access-control.md)
- [Instance and entity list permissions](./permissions.md)
- [Asset policies](./asset-policies.md)
- [Attribute filter rules](./attribute-filter-rules.md)
- [Quarantine](./quarantine.md)
- [Stories](./stories.md)
- [Asset lifecycle](./asset-lifecycle.md)
- [Workspace terms](./workspace-terms.md)
- [Entity lists](./entity-lists.md)
- [Display profiles](./display-profiles.md)
- [Shares](./shares.md)
- [Baskets](./baskets.md)
- [Export](./export.md)
- [Notifications](./notifications.md)

### Search

- [AQL (Asset Query Language)](./aql.md)
- [Search suggestions](./search_suggestions.md)
- [Collection search](./collection_search.md)
- [Filtering assets by collection](./asset_collection_filters.md)
- [Map of the results](./map.md)
- [Elasticsearch populate](./es_populate.md)

### Files and renditions

- [Metadata](./metadata.md)
- [Attribute initial values](./Attributes.mdx)
- [Renditions](./Renditions.mdx)
- [File analyzers](./FileAnalyzers.mdx)
- [Regions of interest](./roi.md)
- [Annotations](./annotations.md)
- [Asset import](./import.md)

### Automation

- [Workflows](./workflows.md)
- [Automations](./automations.md)
- [Share and export automation](./automation-share-export.md)
- [Operation tasks](./operation-tasks.md)

### Integrations

- [Databox integrations](./integration/01_integrations.md)
- [MCP server](./mcp.md)
- [Databox Indexer](https://github.com/alchemy-fr/databox-indexer) (external)

### Operations

- [Configuration](./configuration.md)
- [Administration](./administration.md)
- [Console commands](./console-commands.md)
- [Workers](./workers.md)
- [Message deduplication](./message-deduplication.md)
- [Real time](./realtime.md)
- [Audit log](./audit-log.md)
- [Analytics (Matomo)](./analytics.md)
- [Impersonation](./impersonation.md)
- [Ticketing](./ticketing.md)
- [Secrets](./secrets.md)

### Development

- [Fixtures](./fixtures.md)

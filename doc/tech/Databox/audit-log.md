# Audit log

The audit log records who did what in Databox: every write (content, configuration,
permissions, attributes) and every download through an export. Admins read it in the
Databox client (**Audit log** in the settings menu of the top bar) or in the admin
(**Logs > Audit Log**).

It is stored in the `audit_log` table (formerly `action_log`), one row per action.

## What is logged

| Action (`action`)                                         | Logged when                                                                                   | `data`                                                                               |
| --------------------------------------------------------- | --------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| `object_created`                                          | an auditable object is created                                                                | `values`: the fields set (secrets masked)                                            |
| `object_updated`                                          | fields of an auditable object change                                                          | `changes`: `{field: [old, new]}`, `{field: {added, removed}}` for a list; `sort` for a reordering |
| `object_deleted`                                          | an object is deleted for good                                                                 | `purged`: objects deleted with it, by type; `reason`: `trash_retention` when the trash is emptied |
| `object_trashed` / `object_restored`                      | an asset, a collection or a workspace goes to the trash / comes back                          |                                                                                      |
| `asset_moved`                                             | the reference collection of an asset changes                                                  | `oldCollectionId`, `newCollectionId`                                                 |
| `asset_substituted`                                       | the source file of an asset changes                                                           | `oldSourceId`, `newSourceId`, `keepVersion`                                          |
| `asset_added_to_collection` / `asset_removed_from_collection` | an asset is linked to / unlinked from a collection                                        | `collectionId`, `collectionTitle`                                                    |
| `asset_attribute_changed`                                 | attributes of an asset change (one entry per asset and per batch)                             | `changes`: `[{definitionId, definitionName, locale, op, old, new}]`                  |
| `asset_quarantine_bypassed`                               | a quarantined asset is accepted despite its file analysis                                     |                                                                                      |
| `asset_copied`                                            | an asset is copied (the copy is logged as created)                                            | `destinationWorkspaceId`, `destinationCollectionId`, `options`                       |
| `asset_downloaded`                                        | an asset is exported (`POST /asset-exports`)                                                  | `exportId`, `renditions`, `basketIds`                                                |
| `rendition_deleted`                                       | a rendition of an asset is deleted                                                            | `renditionId`, `definitionId`, `definitionName`                                      |
| `collection_moved`                                        | the parent of a collection changes                                                            | `oldParentId`, `newParentId`                                                         |
| `basket_assets_added` / `basket_assets_removed`           | assets are put in / taken out of a basket (one entry per basket and per request)              | `assetIds`                                                                           |
| `basket_archived` / `basket_unarchived`                   | a basket is archived / unarchived                                                             |                                                                                      |
| `profile_updated`                                         | the items of a profile change                                                                 | `items`: `[{op, itemId, definitionId, key}]`                                         |
| `permission_granted` / `permission_changed` / `permission_revoked` | an access control entry is created / updated / deleted                               | `userType`, `userId`, `previousPermissions`, `permissions` (bit names and masks)    |
| `workflow_triggered`, `workflow_job_rerun`, `workflow_cancelled` | a workflow is triggered on an asset, a job is rerun, a run is cancelled                 | `workflowId`, `jobId`                                                                |
| `automation_triggered`                                    | an automation is run on demand                                                                | `runId`, `dryRun`                                                                    |
| `workspace_terms_signed`                                  | a user accepts the Terms & Conditions of a workspace                                          | `termsVersionId`, `version`                                                          |

The auditable objects are the entities implementing `App\Audit\AuditableInterface`:
assets, collections, workspaces, baskets, shares, saved searches and their automations,
profiles, pages, templates, attribute definitions, policies and filter rules, entity lists
and entities, rendition definitions and policies, tags, workspace environment variables,
integrations and secrets. Their object type is the key of the `alchemy_track` entities map
(`config/packages/alchemy_track.yaml`), exposed by the API as the snake case short class
name (`asset`, `saved_search`...).

Not logged: reads (only the exports are), the downloads from a public share page, the
progress of imports and tasks, the workflow states, the ACEs deleted with their object.

### How

- The `App\Doctrine\Listener\AuditLogListener` (onFlush, after the other listeners) logs
  what the UnitOfWork sees. A field with `#[App\Audit\AuditIgnore]` is left out of the
  diffs (`#[AuditIgnore(mask: true)]` logs `***` instead of its values, e.g. a workspace
  secret or a share token); the timestamps (`createdAt`, `updatedAt`...) and the `position`
  are always left out. An update without any remaining field is not logged.
- The writes the UnitOfWork does not see (DQL/SQL) are logged by their code: the attribute
  batches (`BatchAttributeManager`, through `AttributeChangeAuditor`, which compares the
  values before and after the batch), the collections trashed or restored, the sorts.
- The cascades (deletion of a workspace, a collection, assets) turn the log off with
  `AuditLogManager::mute()` and log a single `object_deleted` entry on their root, with
  the number of objects deleted with it.
- Elsewhere, `AuditLogManager::log()` only persists the entry: the caller flushes.

## Actor and asynchronous actions

Each entry has an `actorType`:

- `user`: `userId` and `userName` (the username at that time, kept even if the user is
  removed), and `impersonatorId` when an admin acts as the user;
- `oauth_client`: `clientId` (a client credentials token);
- `system`: a console command, the scheduler, a message dispatched without context.

Most actions end in a Messenger handler (in a worker in production). The
`AuditContextMiddleware` stamps every dispatched message with the audit context of the
dispatching action (`AuditContextStamp`: actor, IP, URI, route, correlation ID), and
restores it while the message is handled: the trash, restoration, move or copy of an asset
is logged with the user who requested it. An automation acts as the owner of its saved
search (source `automation`).

The `correlationId` is generated once per HTTP request or console command and carried by
the messages it dispatches: it groups an action and its asynchronous effects (in the
client, a click on the `#…` of an entry filters on it). `meta` holds the `uri` and
`_route` of the original request and the `source` of the entry: `api`, `admin`,
`console` (with the `command`), `worker`, `automation` or `integration`.

## Attribute values

The old and new values of the attributes are always stored (`asset_attribute_changed`):
the audit log may hold personal or confidential data entered in attributes. Keep its
retention short enough.

## Retention

`app:audit-log:purge` deletes the entries older than `AUDIT_LOG_RETENTION_DAYS` days
(`DATABOX_AUDIT_LOG_RETENTION_DAYS` in `.env`, 365 by default, `0` keeps them forever).
The `databox-scheduler` runs it every night; `--days` overrides the delay.

## API

`GET /audit-logs` (admins only, `ROLE_ADMIN`), the last entry first, 50 per page
(`limit`, at most 100; `page`). Filters:

| Parameter                     | Matches                                                  |
| ----------------------------- | -------------------------------------------------------- |
| `action[]`                    | action keys                                              |
| `actorType`                   | `user`, `oauth_client` or `system`                       |
| `objectType`, `objectId`      | the object                                               |
| `userId`                      | the acting user, or the admin impersonating them         |
| `clientId`                    | the OAuth client                                         |
| `workspaceId`                 | the workspace of the object                              |
| `correlationId`               | an action and its effects                                |
| `date[after]`, `date[before]` | ISO 8601 dates, inclusive                                |

`GET /audit-logs/{id}` returns one entry.

## Difference with the change log

The change log (`change_log` table, **Logs > Change Log** in the admin) is kept as it
was: it records the raw changesets of a dozen configuration entities
(`LoggableChangeSetInterface` of the track bundle). The audit log covers those entities
too, with the actor, the workspace, the correlation and the semantic actions.

---
title: 'Access control'
---

# Access control

Who may see and change what in Databox results from four layers: the **access control
entries** (ACEs) set on workspaces, collections, assets and other objects, the **privacy
level** of collections and assets, the **attribute and rendition policies**, and the
ownership of the objects. This page describes how they combine, and how the same rules are
applied in Elasticsearch so that search results never leak what a user may not read.

Related pages: [instance permissions](./permissions.md),
[asset policies](./asset-policies.md), [attribute filter rules](./attribute-filter-rules.md),
[quarantine](./quarantine.md).

## Access control entries

An ACE grants a **mask** of permissions on an object, to a user, a group, or everyone (no
user id). The ACL engine is the `alchemy/acl-bundle` Composer package; Databox declares its
object types and enabled permissions in `databox/api/config/packages/alchemy_acl.yaml`.

| Bit | Value   | Bit            | Value    |
|-----|---------|----------------|----------|
| `VIEW`     | 1   | `CHILD_VIEW`     | 512    |
| `CREATE`   | 2   | `CHILD_CREATE`   | 1024   |
| `EDIT`     | 4   | `CHILD_EDIT`     | 2048   |
| `DELETE`   | 8   | `CHILD_DELETE`   | 4096   |
| `UNDELETE` | 16  | `CHILD_UNDELETE` | 8192   |
| `OPERATOR` | 32  | `CHILD_OPERATOR` | 16384  |
| `MASTER`   | 64  | `CHILD_MASTER`   | 32768  |
| `OWNER`    | 128 | `CHILD_OWNER`    | 65536  |
| `SHARE`    | 256 | `CHILD_SHARE`    | 131072 |

`UNDELETE` is not among the enabled permissions. The `CHILD_*` bits, set on a workspace or a
collection, apply to the **assets** it contains; the plain bits apply to the container itself.

Object types: `workspace`, `collection`, `asset`, `basket`, `attribute_policy`,
`rendition_policy`, `entity_list`, `integration`, `saved_search`, `profile`, plus the asset
data templates and pages.

### Extra permissions

Some rights are not bits but **metadata** of an ACE
(`App\Security\Voter\DataboxExtraPermissionInterface`):

| Constant                  | Value | Meaning                                                      |
|---------------------------|-------|--------------------------------------------------------------|
| `PERM_EDIT_PERMISSIONS`   | 1     | Edit the permissions and privacy of the owned content.       |
| `PERM_MANAGE_USERS`       | 2     | Manage the users of a workspace.                             |
| `PERM_QUARANTINE`         | 3     | See the quarantined assets (see [quarantine](./quarantine.md)). |
| `PERM_QUARANTINE_BY_PASS` | 4     | Release them from quarantine.                                |
| 5 to 9                    |       | Instance capabilities, see [instance permissions](./permissions.md). |

### Voters

Each object type has a voter in `databox/api/src/Security/Voter/`. They all extend
`lib/php/auth-bundle/Security/Voter/AbstractVoter.php` (`hasAcl()`, `hasMetadata()`) and first
let through a token holding the matching scope (`workspace:read`, `asset:edit`…), with a
hierarchy: `EDIT` implies `READ` and `CREATE`, `OPERATOR` implies `EDIT` and `DELETE`,
`OWNER` implies `OPERATOR` and `EDIT_PERMISSIONS`.

**`WorkspaceVoter`** — a soft-deleted workspace is denied to everyone. The workspace owner
(`ownerId`) has every right.

- `READ_NO_TERMS`: owner, public workspace, or `VIEW`/`OWNER`. `READ` adds the signature of
  the current [terms](./workspace-terms.md).
- `CREATE_COLLECTION`: `CREATE` or `OWNER`. `EDIT`: `EDIT` or `OWNER`. `DELETE`: `DELETE`.
- `EDIT_PERMISSIONS`: `OWNER`. `MANAGER_USERS`: `OWNER` or `PERM_MANAGE_USERS`.
- Rights on the assets of the whole workspace: `ASSET_VIEW` (`CHILD_VIEW`), `ASSET_CREATE`
  (`CHILD_CREATE`), `ASSET_EDIT_ATTRIBUTES` (`CHILD_EDIT`), `ASSET_EDIT` (`CHILD_OPERATOR`),
  `ASSET_DELETE` (`CHILD_DELETE`), `ASSET_SHARE` (`CHILD_SHARE`), `ASSET_OWNER`
  (`CHILD_OWNER`), `ASSET_QUARANTINE` (metadata 3), `ASSET_QUARANTINE_BYPASS` (metadata 4),
  `ASSET_EDIT_PERMISSIONS` (metadata 1). `OWNER` grants all of them; `CHILD_OWNER` grants the
  view, edit and delete ones.

**`CollectionVoter`** — every right first requires `READ` on the workspace. The collection
owner and the workspace owner have every right, and most rights are **inherited from the
parent collection**, recursively.

- `READ` (the collection is listed): owner, privacy high enough (see below), `VIEW` or
  `OWNER`, or `READ` on the parent. A trashed collection is only readable by who may delete it.
- `ASSET_VIEW` (its assets are visible): owner, privacy high enough, `VIEW`, `CHILD_VIEW`,
  `CHILD_OWNER` or `OWNER`, or `ASSET_VIEW` on the parent.
- `EDIT`, `DELETE`, `OWNER`: the matching bit or `OWNER`.
- Asset rights: `ASSET_CREATE` (`CHILD_CREATE`), `ASSET_EDIT_ATTRIBUTES` (`CHILD_EDIT`),
  `ASSET_EDIT` (`CHILD_OPERATOR`), `ASSET_DELETE` (`DELETE`, `CHILD_DELETE`), `ASSET_SHARE`
  (`CHILD_SHARE`), `ASSET_OWNER` (`CHILD_OWNER`), quarantine metadata 3 and 4.
- The hidden collection of a [story](./stories.md) has no ACE of its own: it follows the
  story asset (`READ` on the asset to read, `EDIT` on the asset to change its items).

**`AssetVoter`** — every right first requires `READ` on the workspace. The asset owner and the
workspace owner have every right except sharing, which the workspace owner, `SHARE` or a
container `ASSET_SHARE` grants.

- `READ`: the asset is not trashed (or the user may delete it), it is accepted (or the user
  may see the [quarantine](./quarantine.md)), and one of: privacy high enough, `VIEW` or
  `OWNER` on the asset, `ASSET_VIEW` on the workspace, on the reference collection or on any
  collection holding the asset, `READ` on the story holding it.
- `EDIT_ATTRIBUTES`: `EDIT`/`OWNER`, or `ASSET_EDIT_ATTRIBUTES` on the workspace or the
  reference collection.
- `EDIT` (rename, move, copy, replace the source, manage renditions): `OPERATOR`/`OWNER`, or
  `ASSET_EDIT` on a container.
- `DELETE`, `OWNER`: the matching bit or the container right.
- `EDIT_PERMISSIONS`: workspace owner, or `OWNER` plus metadata 1 (on the asset or a
  container).
- `CREATE`: `ASSET_CREATE` on the reference collection, or on the workspace for an asset at
  its root.
- After this, every right but `CREATE` also requires the asset to pass the
  [attribute filter rules](./attribute-filter-rules.md) (`App\Security\AssetAttributeFilterChecker`).

**`BasketVoter`** — see [baskets](./baskets.md).

**`SetPermissionVoter`** — reading or writing the ACEs of an object requires
`EDIT_PERMISSIONS` on it.

### Administrators

`lib/php/auth-bundle/Security/Voter/AdminVoter.php` grants **every attribute** to a token
holding `ROLE_ADMIN`, and the access decision strategy is the default (affirmative): an admin
reads and changes any object by its ID, including an asset hidden from them by the attribute
filter rules. In search, admins get no ACL filter, but the attribute filter rules still apply
to them. Admins do not list the baskets of others (see [instance permissions](./permissions.md#defaults)).

## Privacy levels

Collections and assets carry a `privacy` (`App\Entity\Core\WorkspaceItemPrivacyInterface`),
`SECRET` by default (`App\Entity\Traits\WorkspacePrivacyTrait`):

| Value | Constant               | Effect                                                          |
|-------|------------------------|-----------------------------------------------------------------|
| 0     | `SECRET`               | Only the owner and the users granted by an ACE.                 |
| 1     | `PRIVATE_IN_WORKSPACE` | Listed to the workspace users; content not accessible.          |
| 2     | `PUBLIC_IN_WORKSPACE`  | Open to the workspace users.                                    |
| 3     | `PRIVATE`              | Listed to every user; content not accessible.                   |
| 4     | `PUBLIC_FOR_USERS`     | Open to every authenticated user.                               |
| 5     | `PUBLIC`               | Open to everyone, anonymous visitors included.                  |

Since every right first requires `READ` on the workspace, the "in workspace" levels and the
"every user" levels differ by who reads the workspace: its owner, its ACEs, or everyone when
the workspace is public.

- A collection with a "private" level (1, 3) is **listed** but does not open its assets; a
  "public" level (2, 4, 5) opens its assets at that level.
- A collection inherits the most open privacy of its ancestors:
  `Collection::getInheritedPrivacy()`, output as `inheritedPrivacy`;
  `GET /collections/{id}/privacy` answers `privacy`, `computedPrivacy` and
  `canEditAssetPrivacy`. The client privacy field does not offer a level more restrictive
  than the inherited one ("Privacy is inherited from the parent … and cannot be more
  restrictive").
- An asset is indexed with the most open of its own privacy, of the privacy its collections
  open to their assets (up the parent chain, trashed collections aside except the reference
  one), and of the privacy of the stories holding it.
- Privacy is written with `privacy` (integer) or `privacyLabel` (`secret`,
  `private_in_workspace`, `public_in_workspace`, `private`, `public_for_users`, `public`;
  any other label is a 400). It is only applied when the user has `EDIT_PERMISSIONS` on the
  object, and silently ignored otherwise
  (`App\Api\Mapper\Input\AbstractInputMapper::transformPrivacy()`).

### Collection tree access

The collection tree shown to a user is read from the `collection_access` table
(`App\Entity\Core\CollectionAccess`): one row per collection and user or group (`NULL` for
everyone), with the best privacy granted and the materialized path (PostgreSQL `ltree`). It is
computed by `App\Service\Collection\CollectionAccessService` from the collection owner, the
collection ACEs holding `VIEW`, and, for root collections, the workspace owner and its `OWNER`
ACEs. It is a listing aid only: the voter still has the last word on every collection.

It is recomputed asynchronously (`ComputeCollectionBranch`, `ComputeWorkspaceCollections`)
when a collection is created, when its privacy, owner or parent changes, when an ACE of a
collection or workspace changes, and when a workspace changes owner. To rebuild it entirely
(after an upgrade, see `UPGRADE-5.md`):

```bash
bin/console app:collection:recompute-access
```

The EasyAdmin page **Collection Access** shows its rows.

## Attribute policies

An attribute policy (`App\Entity\Core\AttributePolicy`, ACL type `attribute_policy`) groups
attribute definitions of a workspace and decides who reads and who writes their values:

- `public`: every reader of the asset reads the values; otherwise only the users and groups
  with `VIEW` on the policy.
- `editable`: every user who may edit the asset attributes writes the values; otherwise only
  the users and groups with `EDIT` on the policy. The definition must also be editable.

Enforcement: `App\Security\Voter\AttributeVoter` (values),
`App\Security\Voter\AttributeDefinitionVoter` (`VIEW_ATTRIBUTES`), `App\Attribute\AttributeValidator`
and `App\Attribute\BatchAttributeManager` (writes), and
`AttributeDefinitionRepository::getAttributesWithPermission()` for the attributes searchable
by a user. Admins read every policy.

API: `/attribute-policies` (`GET ?workspaceId=`, `POST`, `GET`, `PATCH`, `DELETE`). Reading
takes `READ` on the workspace, managing takes `EDIT` on it. A `POST` with a `key` already
used in the workspace updates that policy.

## Rendition policies

A rendition policy (`App\Entity\Core\RenditionPolicy`, ACL type `rendition_policy`) does the
same for renditions (`App\Security\Voter\AssetRenditionVoter`):

- reading a rendition takes `READ` on the asset and a `public` policy or `CHILD_VIEW` on it;
- creating, replacing or deleting one takes `EDIT` on the asset and an `editable` policy or
  `CHILD_EDIT` on it;
- a rendition without definition follows its asset.

Only the renditions of a public policy can be delivered by a [share](./shares.md).

API: `/rendition-policies`, with the same rights as the attribute policies.

## Owners

`ownerId` of an asset or a collection defaults to the current user on `POST /assets` and
`POST /collections`; only an admin may set another user, and a token without user must give
one (`App\Api\Processor\WithOwnerIdProcessorTrait`).

The `owner` property of the asset and collection outputs can be restricted to a role:

| Variable (root `.env`)                               | Container variable                           | Default |
|------------------------------------------------------|----------------------------------------------|---------|
| `DATABOX_API_ASSET_OWNER_PROPERTY_REQUIRED_ROLE`      | `API_ASSET_OWNER_PROPERTY_REQUIRED_ROLE`      | empty   |
| `DATABOX_API_COLLECTION_OWNER_PROPERTY_REQUIRED_ROLE` | `API_COLLECTION_OWNER_PROPERTY_REQUIRED_ROLE` | empty   |

Empty, everyone gets the property. The asset one also hides the `@owner` built-in attribute
and facet (`App\Elasticsearch\BuiltInAttribute\OwnerBuiltInAttribute`).

## Rights in Elasticsearch

Assets are never listed from SQL: every search goes through
`AssetSearch::createBaseFilterQueries()` (`databox/api/src/Elasticsearch/AssetSearch.php`),
which combines:

- the ACL query of `AbstractSearch::createACLBoolQuery()`: the readable workspaces, and
  either a privacy above the threshold, or `ownerId`, `users` or `groups` matching the user
  (`*` stands for everyone); anonymous visitors only get `PUBLIC` assets of public
  workspaces; admins get no ACL filter;
- the [attribute filter rules](./attribute-filter-rules.md) query;
- the trash filter (`deleteUsers`, `deleteGroups`);
- the status filter (`quarantineUsers`, `quarantineGroups`).

The permission fields of the asset document are computed by
`App\Elasticsearch\AssetPermissionComputer` from the ACEs of the asset, of the workspace and of
every collection holding it (up the parents), and from the owners: `privacy`, `ownerId`,
`users`, `groups`, `deleteUsers`, `deleteGroups`, `quarantineUsers`, `quarantineGroups`,
`collectionPaths`, `referenceCollectionPath`, `stories`. An ACE change reindexes the
concerned assets (`App\Listener\AclListener`).

## API

The ACL routes of `alchemy/acl-bundle` are mounted under `/permissions`
(`databox/api/config/routes/alchemy_acl.yaml`):

- `GET /permissions/aces?objectType=…&objectId=…`: the ACEs of an object;
- `PUT /permissions/ace`: create or replace an ACE (`userType`, `userId`, `objectType`,
  `objectId`, `mask`, `metadata`);
- `DELETE /permissions/ace`: remove one.

The ACEs of an object are read and written under the `ACL_READ` and `ACL_WRITE` checks,
which `SetPermissionVoter` maps to `EDIT_PERMISSIONS` on the object; the ACEs without object
id are reserved to admins (see [instance permissions](./permissions.md)). The `capabilities` of each output
(`editPermissions`, `edit`, `delete`, `share`…) tell the client what the user may do.

## Client

The **Permissions** tab of the workspace (`/workspaces/{id}/manage/permissions`), collection
(`/collections/{id}/manage/permissions`) and asset management screens appears to the users who
may edit the permissions. It lists one row per user, group, "All users" or "All groups", with
the labels of the permission levels:

- workspace: "Access", "Create collections", "Manage", "Delete", "Owner", "Edit permissions";
- collection: "View", "Create collections", "Manage collection", "Manage permissions of owned
  content", "Delete", "Owner", and the inherited permissions of the parents;
- assets of a workspace or collection: "View assets", "Create assets", "Edit asset
  attributes", "Manage assets", "Delete assets", "Share assets", "Owner of assets", "View
  quarantined", "Bypass quarantine";
- asset: "View", "Edit attributes", "Manage", "Edit permissions / privacy", "Delete",
  "Owner";
- attribute and rendition policies: "View", "Edit".

The **Privacy** field (`databox/client/src/components/form/PrivacyField.tsx`) composes the
level from three choices:

| Choice                                 | "Only visible to workspace users" | "User must be authenticated" | Privacy                |
|----------------------------------------|-----------------------------------|------------------------------|------------------------|
| "Secret"                               |                                   |                              | `SECRET`               |
| "Private (users can request access)"   | checked                           |                              | `PRIVATE_IN_WORKSPACE` |
| "Private (users can request access)"   | unchecked                         |                              | `PRIVATE`              |
| "Public"                               | checked                           | (implied)                    | `PUBLIC_IN_WORKSPACE`  |
| "Public"                               | unchecked                         | checked                      | `PUBLIC_FOR_USERS`     |
| "Public"                               | unchecked                         | unchecked                    | `PUBLIC`               |

The EasyAdmin **Permissions** menu holds "Workspace permissions", "Collection permissions",
"Asset permissions", "Basket permissions" and "All permissions (advanced)".

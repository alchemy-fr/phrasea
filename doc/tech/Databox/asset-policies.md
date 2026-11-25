---
title: 'Asset policies'
---

# Asset policies

An asset policy hides some renditions or some attributes of the assets of a workspace from
some users or groups, for instance the source file of the assets of one collection, or a
confidential attribute. It narrows what the [access control](./access-control.md) grants: it
never opens anything.

## Model

`App\Entity\Core\AssetPolicy\AssetPolicy` belongs to a workspace and holds:

| Field        | Meaning                                                                 |
|--------------|-------------------------------------------------------------------------|
| `name`       | Required.                                                               |
| `enabled`    | A disabled policy applies to nobody. Default `true`.                    |
| `conditions` | The assets it applies to (JSON list, see below).                       |
| `actions`    | What it hides (JSON list, at least one).                               |
| `users`      | Its targets, users and groups (`AssetPolicyUser`, `userType` 0 = user, 1 = group). |
| `priority`   | Stored (administration, workspace templates) but not used by the evaluation. |
| `ownerId`    | The creator.                                                            |

`AssetPolicyDependency` exists in the same namespace but is not used by the evaluation.

Limits (`App\Api\Model\Input\AssetPolicyInput`): 1 to 100 actions, at most 100 conditions,
30 users and 30 groups. A new policy needs at least one user or one group.

### Conditions

A condition is `{field, operator, value}`. All the conditions of a policy must match. The
only evaluated condition is the collection one:

```json
{"field": "collection", "operator": "=", "value": "<collection id>"}
```

It matches the assets whose **reference collection** is that collection or one of its
descendants. Any other condition is ignored, so that a policy never hides less than its
author meant. In search, a collection condition naming an unknown collection makes the
policy apply to no asset.

### Actions

An action is `{action, definitionId}`:

| Action           | `definitionId`                       | Effect                                           |
|------------------|--------------------------------------|--------------------------------------------------|
| `hide_rendition` | A rendition definition of the workspace | The renditions of that definition are not output, listed, shared nor downloadable. |
| `hide_attribute` | An attribute definition of the workspace | The values are not output, and are not searchable, sortable, faceted or suggested. |

Unknown actions are kept but ignored.

### Targets

A policy applies to its target users and to the members of its target groups, and only
while enabled. A policy without target applies to **nobody**, and anonymous visitors are
never targeted. Every enabled policy matching the asset and the user applies: their effects
add up.

## Evaluation

`App\Service\Asset\AssetPolicy\AssetPolicyManager`:

- `getPolicyApplicationFilter(Asset)` gives the renditions and attributes hidden on one
  asset for the current user. It is used by the asset output, the rendition and attribute
  collections, the rendition definitions, and the [shares](./shares.md) (rendition delivery
  and URLs). The policies are cached for the request, per workspace and user.
- `getHiddenAttributeScopes()` gives, per hidden attribute definition, the scopes it is
  hidden in: a workspace and the collection paths of the conditions.

In Elasticsearch, `App\Elasticsearch\HiddenAttributeScope` turns these scopes into queries
on `workspaceId` and `referenceCollectionPath`, applied at query time to full-text search,
AQL conditions (and their PHP evaluation), sorts, facets, values and suggestions. An
attribute hidden on a whole workspace is treated as not searchable there. Nothing is
reindexed when a policy changes.

## API

| Method and path               | Security                                      |
|-------------------------------|-----------------------------------------------|
| `GET /asset-policies?workspaceId=…` | `EDIT` on the workspace                  |
| `GET /asset-policies/{id}`    | `EDIT` on the workspace                       |
| `POST /asset-policies`        | `EDIT` on the workspace                       |
| `PATCH /asset-policies/{id}`  | `EDIT` on the workspace; sending `users` or `groups` replaces the targets |
| `DELETE /asset-policies/{id}` | `EDIT` on the workspace                       |

The voter is `App\Security\Voter\AssetPolicyVoter`.

## Client

Workspace management → **Asset policies** (`/workspaces/{id}/manage/asset-policies`), for
the workspace editors (`AssetPoliciesTab.tsx`):

- "New policy", "Name", "Enabled";
- "Target groups", "Target users";
- "Conditions (all must match)" → "Add condition": "Collection" "is in (or below)" a picked
  collection;
- "Actions" → "Add action": "Hide rendition" or "Hide attribute".

:::warning
The targets fields carry the help "Empty = everyone", which does not hold for asset
policies: a policy without target applies to nobody.
:::

## Administration and templates

The EasyAdmin page **Asset Policies** lists, filters and edits the policies (priority,
conditions and actions as JSON). Workspace templates export and import them
(`App\Service\Workspace\Template\Section\AssetPolicySection`).

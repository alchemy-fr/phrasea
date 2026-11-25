---
title: 'Attribute filter rules'
---

# Attribute filter rules

An attribute filter rule restricts the assets of a workspace that some users or groups see:
the targets of a rule only see the assets matching its [AQL](./aql.md) condition, for
instance `country IS "FR"`. This page covers managing the rules; how they are evaluated, and
the limits of their evaluation outside Elasticsearch, are described in the
[Attribute filter rules section of the AQL page](./aql.md#attribute-filter-rules).

## Model

`App\Entity\Core\AttributeFilterRule` belongs to a workspace and holds:

- `condition`: an AQL condition, validated on save by `ValidAQLConstraint` (the violation
  reads "Invalid AQL condition: …");
- `targets` (`AttributeFilterRuleTarget`): users and groups (`userType` 0 = user, 1 = group).
  **A rule without target applies to everyone**, anonymous visitors included.

A user is subject to the rules without target and to the rules targeting them or one of their
groups; all of them must be satisfied. Admins are subject to the rules in search, but read
any asset by its ID (see [access control](./access-control.md#administrators)).

## Evaluation

Rules are applied wherever assets are read:

- in every search, by `AssetSearch::createBaseFilterQueries()`: for each workspace, its
  assets must match its conditions;
- on every asset vote but `CREATE`, by `App\Security\AssetAttributeFilterChecker`, which
  evaluates the condition in PHP on the asset document built in memory
  (`AQLDocumentEvaluator`). The batch operations (delete, move, copy, restore, baskets,
  collections, quarantine, attribute batches) preload the assets to evaluate them at once.

Both fail closed:

- a condition that cannot be turned into an Elasticsearch query hides the whole workspace
  and logs the error "Invalid attribute filter rule condition";
- a condition the PHP evaluator does not support denies the asset and logs the warning
  "Attribute filter rule condition not evaluable outside of Elasticsearch", with the
  workspace, the condition and the asset. Save-time validation only checks the
  Elasticsearch query, so such a rule can be saved: check the logs after creating a rule
  with an unusual condition.

### Caches

The rules of the current user and the PHP decisions are cached for the request only
(`App\Security\AttributeFilterManager`, `AssetAttributeFilterChecker`), and cleared by
`App\Doctrine\Listener\AttributeFilterRuleCacheListener` when a rule or a target changes.
Nothing is reindexed: rules apply at query time.

## API

| Method and path                         | Security                         |
|-----------------------------------------|----------------------------------|
| `GET /attribute-filter-rules?workspaceId=…` | rules of the workspaces the user may edit |
| `GET /attribute-filter-rules/{id}`      | `EDIT` on the workspace          |
| `POST /attribute-filter-rules`          | `EDIT` on the workspace          |
| `PATCH /attribute-filter-rules/{id}`    | `EDIT` on the workspace          |
| `DELETE /attribute-filter-rules/{id}`   | `EDIT` on the workspace          |

Input: `workspaceId` (on creation), `condition`, `userIds[]`, `groupIds[]`. Output: `id`,
`workspaceId`, `condition`, `users` and `groups` (`id`, `name`), `createdAt`. The voter is
`App\Security\Voter\AttributeFilterRuleVoter`.

## Client

Workspace management → **Filter rules** (`/workspaces/{id}/manage/filter-rules`), for the
workspace editors (`FilterRulesTab.tsx`), titled "Attribute filter rules": "Assets are only
visible to the targets when they match the condition."

- "Add rule", then "Users" and "Groups" ("Empty = everyone");
- "Condition" → "Add condition" / "Edit condition" opens the AQL condition builder;
- each rule shows its condition in plain words, and its targets or "Everyone".

## Administration and templates

The EasyAdmin page **Attribute Filter Rules** lists the rules and edits their condition; it
does not create rules. Workspace templates export and import them
(`App\Service\Workspace\Template\Section\AttributeFilterRuleSection`).

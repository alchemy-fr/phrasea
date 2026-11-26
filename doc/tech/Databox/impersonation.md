---
title: 'Impersonation'
---

# Impersonation

An administrator holding the right Keycloak role can browse Databox as another user, to
check what this user sees and may do. The admin keeps their own session; every API request
carries the `X-Impersonate-User` header, and the API evaluates the permissions with the
roles and groups of the target only.

## Concepts

- **Impersonator**: the signed-in user. They need the client role `impersonation` of the
  Keycloak client `realm-management` (in the access token under
  `resource_access.realm-management.roles`). `Alchemy\AuthBundle\Security\JwtExtractor`
  turns it into the Symfony role `ROLE_IMPERSONATOR`. Keycloak composite roles are expanded
  in the token: `realm-admin`, which the configurator grants to the default admin, includes
  it. There is no switch to turn the feature off: review who holds these roles in Keycloak.
- **Target**: the user acted as. Their identity is read from the Keycloak admin API at each
  request (roles and groups are never cached), so a change of their rights applies at once.
- **Effective user**: the `Alchemy\AuthBundle\Security\JwtUser` of the request, built by
  `Alchemy\AuthBundle\Security\Impersonator::impersonate()`: id, username, realm roles
  (mapped by `RoleMapper`) and groups of the target, the scopes of the impersonator's token,
  and `getImpersonatorId()` set to the admin's id (`isImpersonated()` is then true).

## Rules

The header is read by `Alchemy\AuthBundle\Security\AccessTokenAuthenticator` (shared
`lib/php/auth-bundle`), after the bearer token is validated. The impersonation is refused
(403) when:

- the token does not carry `ROLE_IMPERSONATOR`, or the request is already impersonated (no
  chain of impersonations);
- the target id is not made of 1 to 64 letters, digits or dashes;
- the target does not exist in Keycloak or is disabled;
- the target lacks one of the `alchemy_auth.required_roles` (by default the realm role named
  after the application, `databox`), as for a normal login.

Any other user can be impersonated, admins included: the effective user then gets their
`ROLE_ADMIN`. Conversely the impersonator's own roles are dropped, and the target never gets
`ROLE_IMPERSONATOR` nor the Keycloak management roles, which only come from the token.

What an impersonated request can do: everything the target may do through the API, under the
target's roles, groups and ACLs, evaluated by the same voters. What it cannot do:

- act on Keycloak with the admin's token: listing or creating OAuth clients
  (`App\Api\Provider\OAuthClientProvider`, `App\Api\Processor\CreateOAuthClientProcessor`)
  and managing groups (`Alchemy\AuthBundle\Controller\GroupsController`) are refused by
  `Alchemy\AuthBundle\Security\Traits\KeycloakTokenOwnerTrait`, since the token still
  belongs to the admin;
- call the impersonation routes themselves.

The CORS configuration of the Databox API (`nelmio_cors.yaml`) allows the
`X-Impersonate-User` header from the Databox and Dashboard clients.

## API

`Alchemy\AuthBundle\Controller\ImpersonationController` (routes
`alchemy_auth_impersonation_*`, imported in `databox/api/config/routes/alchemy_auth.yaml`).
Both must be called as the real user, without the header, and require `ROLE_IMPERSONATOR`
(403 otherwise):

| Method and path                | Answer                                                                                   |
| ------------------------------ | ---------------------------------------------------------------------------------------- |
| `GET /impersonation/users`     | the users of the realm: `id`, `username`, `email`, `firstName`, `lastName`, `enabled`. Query `query` (search), `offset`, `limit` (30 by default, 100 at most) |
| `GET /impersonation/users/{id}` | the identity the API would use: the fields above plus `roles` and `groups`; 404 if the user cannot be impersonated |

Any other request becomes impersonated by adding the header:

```http
GET /assets
Authorization: Bearer <admin access token>
X-Impersonate-User: <target user id>
```

`Impersonator::getIdentity()` is also used outside of any request to act as a user with
their current rights: by the automations (`App\Automation\UserContextSwitcher`) and the
integrations acting as their owner (`App\Security\IntegrationOwnerProvider`).

## Client

**Switch user** is offered only when the token grants the `impersonation` role
(`canImpersonate`, `lib/js/core/src/auth/oidc.ts`):

- the user menu of the top bar shows **Switch user…** with its shortcut, Ctrl+U (⌘U on
  macOS), which overrides the browser's "view source" except in a rich text editor
  (`databox/client/src/features/impersonation/useSwitchUserShortcut.ts`);
- the dialog **Switch user** (`SwitchUserDialog.tsx`) reads: **Browse the app with another
  user's permissions. Your actions are logged as done by you on their behalf.** It searches
  with **Search by name, username or email…** (50 results), marks the admin's own line
  **(you)** and the current target with a check; disabled users cannot be picked. Picking a
  user loads their identity (`GET /impersonation/users/{id}`), then reloads the app.
- while impersonating, a banner at the top of every page reads **You are browsing as**
  followed by the target's name, with a **Back to my account** button
  (`ImpersonationBanner.tsx`); the user menu shows the target with "via" and the admin's
  name, and a **Back to** item followed by the admin's name.

How it works (in `databox/client/src/`: `lib/auth/impersonation.ts`,
`lib/auth/AuthProvider.tsx`, `lib/api/http.ts`):

- the target is stored in `localStorage` (`dbx.impersonation`): it survives reloads and is
  shared by the tabs; a switch in another tab reloads the others;
- the API client adds `X-Impersonate-User` to every authenticated API request, except those
  sent with `asRealUser` (the impersonation routes, and the one-time token of the
  integration authorization popup, which is always granted as the signed-in user);
- starting or stopping reloads the page (after clearing the session caches `dbx.prefs` and
  the attribute definitions cache), the only reliable reset of every store and realtime
  channel;
- at startup the stored identity is refreshed; it is dropped when the admin lost the right
  or the API answers 403/404 for the target; logging out clears it;
- `useAuth()` exposes `user` (effective), `realUser`, `impersonating`, `canImpersonate`;
  `canViewOAuthClients`, `canManageOAuthClients` and `canManageGroups` are off while
  impersonating.

The realtime channels are authorized with the target's rights too, including the target's
in-app notification channel (see [Realtime updates](./realtime.md)). A ticket created with
**Report an issue** is always reported by the signed-in admin (see [Ticketing](./ticketing.md)).

## Audit log and analytics

- Each [audit log](./audit-log.md) entry of an impersonated action has the target as
  `userId`/`userName` and the admin as `impersonatorId`
  (`App\Audit\AuditContext::resolveTokenActor()`); the context, carried by the Messenger
  messages, keeps it for the asynchronous part of the action. The Databox audit log screen
  shows the actor followed by "via" and the admin's name. Filtering on `userId` matches both
  columns, so the actions of an admin include those done as somebody else. The admin
  **Audit Log** has an **Impersonator** filter and an `impersonatorId` column.
- [Analytics](./analytics.md): the Matomo user id is the effective user's id
  (`databox/client/src/lib/analytics/MatomoTracker.tsx`), i.e. the target while
  impersonating.

## Permissions

| Action                                | Requires                                                         |
| ------------------------------------- | ---------------------------------------------------------------- |
| Impersonate, list the users           | Keycloak `realm-management` → `impersonation` (`ROLE_IMPERSONATOR`), not impersonated |
| Be impersonated                       | an enabled Keycloak user holding the `required_roles` (`databox`) |

See also [Permissions](./permissions.md) and [Authentication](../Authentication/permissions.md).

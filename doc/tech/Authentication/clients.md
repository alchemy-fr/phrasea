# Access tokens and audiences

The Databox, Expose and Uploader APIs authenticate their requests with a Keycloak access token of the
Phrasea realm (`Authorization: Bearer <token>`), checked by `Alchemy\AuthBundle\Security\JwtValidator`
(`lib/php/auth-bundle`). The same checks apply to the session of the admin (EasyAdmin), whose token is
checked again on each request.

## What is checked

A token is accepted when all of these hold:

- **Signature**: RS256, with the public key of the realm.
- **Issuer** (`iss`): the realm (`KEYCLOAK_URL/realms/KEYCLOAK_REALM_NAME`).
- **Dates**: `exp` not reached, `nbf` and `iat` not in the future, with a tolerance of 30 seconds for the
  clock skew between Keycloak and the API (`alchemy_auth.token_leeway`, in seconds).
- **Type**: the `typ` claim is `Bearer`. The ID tokens (`typ: ID`) and refresh tokens, signed by the same
  key, are refused: an ID token travels more than an access token (e.g. `id_token_hint` of the logout).
- **Audience**: the `aud` claim (a string or a list) includes the **admin client of the API**
  (`ADMIN_CLIENT_ID` of the API: `DATABOX_ADMIN_CLIENT_ID`, `EXPOSE_ADMIN_CLIENT_ID`,
  `UPLOADER_ADMIN_CLIENT_ID`, read by the bundle as `alchemy_auth.client_id`). The tokens of that admin
  client itself (admin, Swagger UI, client credentials of its service account), read from the `azp` claim
  (else `client_id`), are accepted without it.

## Audiences

Keycloak decides which clients get which audience: each API has a **client scope** named `<app>-api`
(`databox-api`, `expose-api`, `uploader-api`), whose audience mapper (`oidc-audience-mapper`, "Included
Client Audience" = the admin client of the API) adds the API to the `aud` claim of the access tokens. The
scope is not added to the `scope` claim of the token (`include.in.token.scope = false`), which keeps carrying
the functional scopes only (`databox:*`, `publish`…).

A client calls an API when it has the scope of the API among its **default** client scopes. The configurator
creates the scopes and gives them to the clients of the stack, at the installation (`bin/setup.sh`) and at
each upgrade (`bin/migrate.sh`, Keycloak synchronization):

| Caller                                         | Client                                                 | Client scopes                  |
| ---------------------------------------------- | ------------------------------------------------------ | ------------------------------ |
| Databox client                                 | `DATABOX_CLIENT_ID`                                    | `databox-api`                  |
| Expose client                                  | `EXPOSE_CLIENT_ID`                                     | `expose-api`                   |
| Uploader client                                | `UPLOADER_CLIENT_ID`                                   | `uploader-api`, `databox-api`: the Uploader API forwards the user's token to read Databox |
| Dashboard                                      | `DASHBOARD_CLIENT_ID`                                  | `databox-api` (groups management) |
| Databox indexer                                | `INDEXER_DATABOX_CLIENT_ID` (client credentials)       | `databox-api`                  |
| Expose integration of Databox                  | `DATABOX_EXPOSE_INTEGRATION_CLIENT_ID`                 | `expose-api`                   |
| Expose integration of Databox                  | the clients created from Databox (`POST /oauth-clients`, Keycloak attribute `phrasea.created_by=databox`) | `expose-api` |
| Development                                    | `postman` (`dev` preset)                               | `databox-api`, `expose-api`, `uploader-api` |
| Admins (EasyAdmin), Swagger UI, service accounts | `*_ADMIN_CLIENT_ID`                                  | none: accepted by their own API |
| MCP clients (`/mcp` of each API)               | none by default, see below                             | the scope of the API of the server; the Uploader one also reaches Databox with the same token |

Databox creates the clients of its integrations with the `expose-api` default scope, and the configurator gives
it again to every client carrying the `phrasea.created_by=databox` attribute.

These do not go through the access tokens, and are not affected: upload links of the Uploader
(`UploadLink` scheme), commits sent by the Uploader to Databox (`ApiKey`), signed URLs, one-time tokens.
`/oauth/v2/authorize` and `/oauth/v2/token` of the APIs only forward to Keycloak.

## Allowing a new client

A refused token answers `401 Invalid token.`, and the API logs a warning naming the client and the scope it
lacks:

```
Access token of the OAuth client "my-script" refused: its audience does not include "databox-admin" (give the client the "databox-api" client scope in Keycloak)
```

In Keycloak, add the scope of every API the client calls: Clients › `<client>` › Client scopes › Add client
scope › `<app>-api`, as **Default**. As **Optional**, the client must ask for it when obtaining the token
(`scope=openid databox-api`). The tokens issued before the change keep their audience: log in again (or
refresh the token).

- **MCP clients**: create a Keycloak client for them (e.g. a public client `mcp` with the standard flow and
  the redirect URIs of the MCP clients), with the scopes of the APIs whose server they use, plus
  `databox-api` for the Uploader server. Clients registered dynamically do not have the scopes: prefer a
  dedicated client.
- **Integrations** of Databox calling Expose: a client created from Databox has the `expose-api` scope; one
  created by hand in Keycloak must be given it. The client picker of the Expose integration flags the clients
  without it ("Refused by Expose", read from the default client scopes of the client), and Databox answers the
  refusal of Expose with a message naming the client and the scope instead of `401 Invalid token.`. The users
  must then authorize the integration again, for a token with the new audience.
- **Scripts** with client credentials: the scope applies to the service account tokens as well.

## Tests

`KeycloakClientTestMock::getJwtFor()`, `getClientCredentialJwt()` and `createJwt()` forge access tokens
like Keycloak's (`typ: Bearer`, `azp: test-app`, `nbf` and `iat` at the current time), with the admin client of
the API under test as audience (`aud`: `ADMIN_CLIENT_ID` of the environment,
`KeycloakClientTestMock::getApiAudience()`). Pass `'aud' => null` to `createJwt()` for a token without audience.

# Access tokens and allowed clients

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
- **Client**: the client the token was issued to, read from the `azp` claim (else `client_id`), is allowed
  on this API.

The audience (`aud`) is not checked: Keycloak does not put the APIs in it without an audience mapper.

## Allowed clients

Each API accepts the tokens of:

- **its own admin client** (`ADMIN_CLIENT_ID` of the API: `DATABOX_ADMIN_CLIENT_ID`,
  `EXPOSE_ADMIN_CLIENT_ID`, `UPLOADER_ADMIN_CLIENT_ID`), always: admin, Swagger UI, client credentials of
  its service account;
- **the clients listed in `ALLOWED_CLIENT_IDS`** (comma separated), set from the `.env` of the stack:

  | API      | Variable of the stack            | Default                                                                                          |
  | -------- | -------------------------------- | ------------------------------------------------------------------------------------------------ |
  | Databox  | `DATABOX_API_ALLOWED_CLIENT_IDS`  | `DATABOX_CLIENT_ID`, `DASHBOARD_CLIENT_ID`, `UPLOADER_CLIENT_ID`, `INDEXER_DATABOX_CLIENT_ID`    |
  | Expose   | `EXPOSE_API_ALLOWED_CLIENT_IDS`   | `EXPOSE_CLIENT_ID`, `DATABOX_EXPOSE_INTEGRATION_CLIENT_ID`                                        |
  | Uploader | `UPLOADER_API_ALLOWED_CLIENT_IDS` | `UPLOADER_CLIENT_ID`                                                                             |

  The defaults are built from the client variables of the `.env`: overriding `DATABOX_CLIENT_ID` (or the
  others) carries over. Without the variable, an API falls back to the default client IDs of its own
  `.env` (`databox-app`, `expose-app`…);
- **Expose only: the clients Databox creates for its integrations** (from the configuration of an
  integration in the Databox client, `POST /oauth-clients`: Keycloak attribute
  `phrasea.created_by=databox`). Expose looks each unknown
  client up in Keycloak with its service account, which the configurator grants the `view-clients` role of
  `realm-management`, and caches the answer for a minute. If Keycloak cannot be reached, these clients are
  refused for 30 seconds (an error is logged) while the listed ones keep working.

### Who calls what

| Caller                                         | Client                                                 | APIs                                                                                   |
| ---------------------------------------------- | ------------------------------------------------------ | -------------------------------------------------------------------------------------- |
| Databox client                                 | `DATABOX_CLIENT_ID`                                    | Databox                                                                                |
| Expose client                                  | `EXPOSE_CLIENT_ID`                                     | Expose                                                                                 |
| Uploader client                                | `UPLOADER_CLIENT_ID`                                   | Uploader, and Databox: the Uploader API forwards the user's token to read Databox      |
| Dashboard                                      | `DASHBOARD_CLIENT_ID`                                  | Databox (groups management)                                                            |
| Admins (EasyAdmin), Swagger UI                 | `*_ADMIN_CLIENT_ID`                                    | their own API                                                                          |
| Databox indexer                                | `INDEXER_DATABOX_CLIENT_ID` (client credentials)       | Databox                                                                                |
| Expose integration of Databox                  | `DATABOX_EXPOSE_INTEGRATION_CLIENT_ID`, or a client created by Databox | Expose                                                                 |
| MCP clients (`/mcp` of each API)               | none by default, see below                             | the API of the server; the Uploader one also reaches Databox with the same token       |

These do not go through the access tokens, and are not affected: upload links of the Uploader
(`UploadLink` scheme), commits sent by the Uploader to Databox (`ApiKey`), signed URLs, one-time tokens.
`/oauth/v2/authorize` and `/oauth/v2/token` of the APIs only forward to Keycloak.

## Allowing a new client

A refused token answers `401 Invalid token.`, and the API logs a warning naming the client:

```
Access token of the OAuth client "my-script" refused: the client is not allowed on this API
```

Add its client ID to the variable of every API it calls, in the `.env.local` of the stack (or the values of
the deployment), then recreate the API containers (`dc up -d`):

```dotenv
DATABOX_API_ALLOWED_CLIENT_IDS="${DATABOX_CLIENT_ID},${DASHBOARD_CLIENT_ID},${UPLOADER_CLIENT_ID},${INDEXER_DATABOX_CLIENT_ID},my-script"
```

- **MCP clients**: create a Keycloak client for them (e.g. a public client `mcp` with the standard flow and
  the redirect URIs of the MCP clients), and allow it on the APIs whose server they use, plus Databox for the
  Uploader server. Clients registered dynamically get a random client ID: they cannot be listed, prefer a
  dedicated client.
- **Integrations** of Databox calling Expose: a client created from Databox is accepted by itself; a
  client created by hand in Keycloak must be added to `EXPOSE_API_ALLOWED_CLIENT_IDS`.
- **Development**: the `postman` client created by the `dev` preset of the configurator is not allowed by
  default; add it to the variables of the APIs you call with it.

Outside the stack's `.env`, the API reads `ALLOWED_CLIENT_IDS`, through the bundle configuration:

```yaml
# config/packages/alchemy_auth.yaml
alchemy_auth:
    allowed_client_ids: '%env(csv:ALLOWED_CLIENT_IDS)%' # default, or a list
    token_leeway: 30 # seconds
```

An application can also allow clients known at runtime only, with a service implementing
`Alchemy\AuthBundle\Security\AllowedClientIdsProviderInterface` (autoconfigured): it is asked about the
clients missing from the list, once the token is otherwise valid (see `DataboxIntegrationClientIds` of
Expose).

## Tests

`KeycloakClientTestMock::getJwtFor()`, `getClientCredentialJwt()` and `createJwt()` forge access tokens
like Keycloak's (`typ: Bearer`, `azp: test-app`, `nbf` and `iat` at the current time). In the `test`
environment, `TestAllowedClientIdsProvider` allows their clients (`test-app`, `machine_client`), whatever
`ALLOWED_CLIENT_IDS` the container defines.

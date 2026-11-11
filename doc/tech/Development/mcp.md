# MCP servers

The Phrasea APIs embed a [Model Context Protocol](https://modelcontextprotocol.io/) server so that AI assistants
(Claude, IDE agents…) can act on behalf of a user. Each API exposes its own server on `https://<API host>/mcp`
(Streamable HTTP transport); the tools of each one are listed in its own page:

- [Expose](../Expose/mcp.md)

The servers are built on [`symfony/mcp-bundle`](https://symfony.com/doc/current/ai/bundles/mcp-bundle.html); what
they share lives in `lib/php/mcp-bundle` (`AlchemyMcpBundle`).

## Architecture: tools are REST sub-requests

Tools do not reimplement any business rule. `Alchemy\McpBundle\Api\ApiClient` replays each tool call as an
internal **sub-request** to the REST API of the application (`HttpKernelInterface::SUB_REQUEST`), so that voters,
ACL, Doctrine extensions, validation, persistence and normalization are exactly those of the API clients.

- The firewall only authenticates the main request (`/mcp`): sub-requests inherit its security token. The
  sub-request also gets the host, scheme and `Authorization` header of the MCP request.
- `get()`, `post()`, `put()`, `patch()` (sent as `application/merge-patch+json`) and `delete()` return the decoded
  JSON response; `getCollection()` asks for JSON-LD and returns `{items, totalItems, page, hasNextPage}`, plus the
  top-level keys given in `$extraKeys` (e.g. `facets`).
- JSON-LD metadata (`@id`, `@type`, `@context`) and debug data (`debug:…` keys) are removed from the responses.
- An error response becomes a `ToolCallException` whose message is reported to the model:
  `<api_name> API responded <status>: <violations or detail>`.
- `ApiClient::withoutNulls()` drops the arguments left unset by the model, so that a partial update does not
  overwrite the other fields.

## Authentication

Every request needs a Keycloak access token of the Phrasea realm (`Authorization: Bearer <token>`), the same as
the web clients. Tools run with the permissions of that user.

Without a token, the endpoint answers `401` with a
`WWW-Authenticate: Bearer resource_metadata="…/.well-known/oauth-protected-resource"` header
(`Alchemy\McpBundle\Security\McpAuthenticationEntryPoint`). That [RFC 9728](https://www.rfc-editor.org/rfc/rfc9728)
document, also served on `/.well-known/oauth-protected-resource/mcp` (the variant MCP clients try first), names
the Keycloak realm as authorization server, so that MCP clients supporting OAuth discovery can obtain a token by
themselves (this requires a Keycloak client they can use, e.g. through dynamic client registration).

## Adding the MCP server to an API

1. Require `symfony/mcp-bundle` and `alchemy/mcp-bundle` (Composer `path` repository `../../lib/php/mcp-bundle`),
   and enable both bundles in `config/bundles.php` (`McpBundle` before `AlchemyMcpBundle`).
2. `config/packages/alchemy_mcp.yaml`:

   ```yaml
   alchemy_mcp:
       server: databox                  # key of the server in mcp.servers
       resource_name: 'Phrasea Databox' # advertised in the protected resource metadata
       api_name: Databox                # prefix of the errors (defaults to resource_name)
   ```

   The bundle prepends the server's `http.path: /mcp`, `http.allowed_hosts: false` (requests carry a Bearer
   token, which a browser never attaches to a rebound request) and `session.store: framework` (shared across
   PHP workers and replicas).
3. `config/packages/mcp.yaml` declares the server itself: `name`, `description`, `instructions` and
   `registry.tools: ['App\Mcp\Tool\']`.
4. `config/routes/mcp.yaml` imports the routes of the bundle (the MCP endpoint and the metadata):

   ```yaml
   alchemy_mcp:
       resource: '@AlchemyMcpBundle/config/routing.yaml'
   ```

5. `config/packages/security.yaml`, before the `api` firewall:

   ```yaml
   oauth_metadata:
       pattern: ^/\.well-known/oauth-protected-resource(/mcp)?$
       security: false

   mcp:
       pattern: ^/mcp
       stateless: true
       entry_point: Alchemy\McpBundle\Security\McpAuthenticationEntryPoint
       custom_authenticators:
           - Alchemy\AuthBundle\Security\AccessTokenAuthenticator
   ```

   and `- { path: ^/mcp, roles: IS_AUTHENTICATED_FULLY }` in `access_control` (the metadata must stay public if
   a catch-all rule follows).
6. The SDK needs PSR-17 factories (`http_discovery.yaml` or `nyholm_psr7.yaml`).

## Adding a tool

Tools are public methods of services in `src/Mcp/Tool/`, one class per domain, with the `#[McpTool]` attribute of
`mcp/sdk`:

```php
#[McpTool(
    name: 'update_asset',
    description: 'Update the metadata of an asset. Only the given fields are changed.',
    annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true),
)]
public function update(
    #[Schema(description: 'Asset ID (UUID)')]
    string $id,
    ?string $title = null,
): array {
    return $this->client->patch('/assets/'.rawurlencode($id), ApiClient::withoutNulls([
        'title' => $title,
    ]));
}
```

- The description is what the model reads: say what the tool does, its pagination, and what to call first.
- Annotate every tool: `readOnlyHint` for reads, `destructiveHint` for deletions, `idempotentHint` for updates.
- Escape the identifiers put in a path (`rawurlencode()`); refer to related resources with their IRI.
- Never check permissions nor validate in the tool: the API does it, and its error reaches the model.

List the registered tools with `bin/console debug:mcp` (in the API container).

## Testing

- Unit tests of the tools extend `Alchemy\McpBundle\Test\AbstractToolTestCase`: queue the API responses on
  `$this->kernel` (`RecordingHttpKernel::willRespond()`), call the tool with `$this->client`, and check the
  sub-request with `assertLastRequest($method, $path, $query, $body)`.
- Functional tests use `Alchemy\McpBundle\Test\McpClientTrait`: implement `mcpRequest()` with the request helper of
  the application, then `mcpInitialize($token)` opens the MCP session that `mcpCallTool($token, $name, $arguments)`
  and `mcpRpc($token, $method, $params)` reuse (`Mcp-Session-Id` header, protocol `2025-06-18`).
  `mcpCallTool(..., expectError: true)` returns the error message reported to the model.

The bundle tests run with `composer install && composer test` in `lib/php/mcp-bundle`.

## Connecting a client

Claude Code, with a token obtained from Keycloak:

```bash
claude mcp add --transport http expose https://api-expose.phrasea.local/mcp --header "Authorization: Bearer $TOKEN"
```

MCP Inspector: `npx @modelcontextprotocol/inspector`, then choose the *Streamable HTTP* transport, the `/mcp`
URL of the API and add the `Authorization` header.

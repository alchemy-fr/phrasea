---
title: MCP server
---

# MCP server

Expose API embeds a [Model Context Protocol](https://modelcontextprotocol.io/) server so that AI assistants
(Claude, IDE agents…) can manage publications on behalf of a user.

- Endpoint: `https://<EXPOSE_API_URL>/mcp` (Streamable HTTP transport)
- Implementation: [`symfony/mcp-bundle`](https://symfony.com/doc/current/ai/bundles/mcp-bundle.html),
  configured in `expose/api/config/packages/mcp.yaml`, tools in `expose/api/src/Mcp/Tool/`.

## Authentication

Every request needs a Keycloak access token of the Phrasea realm (`Authorization: Bearer <token>`), the same
one the Expose client uses. Tools run with the permissions of that user.

Without a token, the endpoint answers `401` with a
`WWW-Authenticate: Bearer resource_metadata="…/.well-known/oauth-protected-resource"` header. That
[RFC 9728](https://www.rfc-editor.org/rfc/rfc9728) document names the Keycloak realm as authorization server,
so that MCP clients supporting OAuth discovery can obtain a token by themselves (this requires a Keycloak client
they can use, e.g. through dynamic client registration).

## Tools

| Tool                      | Description                                                |
|---------------------------|------------------------------------------------------------|
| `list_publications`       | List/search publications (filters, pagination)             |
| `get_publication`         | Get a publication by ID or slug                            |
| `check_publication_slug`  | Tell whether a slug is available                           |
| `create_publication`      | Create a publication (optionally under a parent/profile)   |
| `update_publication`      | Update a publication                                       |
| `delete_publication`      | Delete a publication                                       |
| `sort_publication_assets` | Reorder the assets of a publication                        |
| `list_profiles`           | List/search publication profiles                           |
| `get_profile`             | Get a publication profile                                  |
| `create_profile`          | Create a publication profile                               |
| `update_profile`          | Update a publication profile                               |
| `delete_profile`          | Delete a publication profile                               |
| `list_publication_assets` | List the assets of a publication                           |
| `get_asset`               | Get an asset                                               |
| `update_asset`            | Update the metadata of an asset                            |
| `delete_asset`            | Delete an asset                                            |

Tools do not reimplement any business rule: `App\Mcp\ApiClient` replays each call as an internal sub-request to
the REST API, so voters, ACL, validation and normalization are exactly those of the API.

List the registered tools with:

```bash
dc run --rm expose-api-php su app -c "bin/console debug:mcp"
```

## Connecting a client

Claude Code, with a token obtained from Keycloak:

```bash
claude mcp add --transport http expose https://api-expose.phrasea.local/mcp --header "Authorization: Bearer $TOKEN"
```

MCP Inspector: `npx @modelcontextprotocol/inspector`, then choose the *Streamable HTTP* transport, the `/mcp`
URL and add the `Authorization` header.

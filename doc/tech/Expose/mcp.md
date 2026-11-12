---
title: MCP server
---

# MCP server

Expose API embeds a [Model Context Protocol](https://modelcontextprotocol.io/) server so that AI assistants
(Claude, IDE agents…) can manage publications on behalf of a user.

- Endpoint: `https://<EXPOSE_API_URL>/mcp` (Streamable HTTP transport), with a Keycloak access token of the user
- Configuration: `expose/api/config/packages/mcp.yaml` and `alchemy_mcp.yaml`, tools in `expose/api/src/Mcp/Tool/`

Architecture, authentication, client connection and how to add a tool: see
[MCP servers](../Development/mcp.md).

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

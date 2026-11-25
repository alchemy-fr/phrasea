---
title: MCP server
---

# MCP server

Expose API embeds a [Model Context Protocol](https://modelcontextprotocol.io/) server so that AI assistants
(Claude, IDE agents…) can manage publications, their assets, share links and permissions on behalf of a user.

- Endpoint: `<EXPOSE_API_URL>/mcp` (Streamable HTTP transport), with a Keycloak access token of the user
- Configuration: `expose/api/config/packages/mcp.yaml` and `alchemy_mcp.yaml`, tools in `expose/api/src/Mcp/Tool/`

Architecture, authentication, client connection and how to add a tool: see
[MCP servers](../Development/mcp.md).

## Tools

"Edit", "delete" and `OPERATOR` are the rights of [Access](./access.md): administrators, the owner of the
publication, or the ACL permission of that name. The tools only replay REST calls: the API enforces these rights.

| Tool                            | Description                                                                                          | Permission                                                     |
|---------------------------------|------------------------------------------------------------------------------------------------------|----------------------------------------------------------------|
| `list_publications`             | List/search publications (title, description, filters, pagination)                                   | any user (publications they may see)                           |
| `get_publication`               | Get a publication by ID or slug                                                                      | publication readable by the user                               |
| `check_publication_slug`        | Tell whether a slug is available                                                                     | create publications                                            |
| `create_publication`            | Create a publication (optionally under a parent/profile)                                             | create publications                                            |
| `update_publication`            | Update a publication: settings, cover, package, profile (or detach it), parent (or move to the root) | edit; `OPERATOR` to change the profile                         |
| `delete_publication`            | Delete a publication                                                                                 | delete                                                         |
| `sort_publication_assets`       | Reorder the assets of a publication                                                                  | edit                                                           |
| `list_profiles`                 | List/search publication profiles                                                                     | any signed-in user (profiles they may read)                    |
| `get_profile`                   | Get a publication profile                                                                            | profile readable by the user                                   |
| `create_profile`                | Create a publication profile                                                                         | create profiles                                                |
| `update_profile`                | Update a publication profile                                                                         | edit the profile                                               |
| `delete_profile`                | Delete a publication profile                                                                         | delete the profile                                             |
| `list_publication_assets`       | List the assets of a publication                                                                     | publication details readable by the user, or edit              |
| `get_asset`                     | Get an asset                                                                                         | any user (details hidden when the publication is not readable) |
| `update_asset`                  | Update the metadata of an asset (title, location, slug, position, tracking ID, WebVTT subtitles)     | edit the publication                                           |
| `delete_asset`                  | Delete an asset                                                                                      | edit the publication                                           |
| `list_asset_sub_definitions`    | List the sub-definitions (renditions) of an asset                                                    | publication details readable by the user                       |
| `get_sub_definition`            | Get a sub-definition                                                                                 | publication details readable by the user                       |
| `delete_sub_definition`         | Delete a sub-definition and its file                                                                 | edit the publication                                           |
| `list_publication_shares`       | List the share links of a publication                                                                | `OPERATOR`                                                     |
| `get_publication_share`         | Get a share link                                                                                     | `OPERATOR`                                                     |
| `create_publication_share`      | Create a share link (`POST /publications/{id}/shares`)                                               | `OPERATOR`                                                     |
| `update_publication_share`      | Rename a share link, change or remove its expiration                                                 | `OPERATOR`                                                     |
| `revoke_publication_share`      | Revoke a share link, for good                                                                        | `OPERATOR`                                                     |
| `delete_publication_share`      | Delete a share link                                                                                  | `OPERATOR`                                                     |
| `list_publication_permissions`  | List the ACEs of a publication, a profile or the whole type (`GET /permissions/aces`)                | owner of the object, admin                                     |
| `set_publication_permission`    | Grant permissions (names, turned into the mask) to a user or group (`PUT /permissions/ace`)          | owner of the object, admin                                     |
| `remove_publication_permission` | Remove the ACE of a user or group (`DELETE /permissions/ace`)                                        | owner of the object, admin                                     |
| `search_users`                  | Search the users (`GET /permissions/users`), to get their ID                                         | signed-in user allowed by Keycloak                             |
| `search_groups`                 | Search the groups (`GET /permissions/groups`), to get their ID                                       | signed-in user allowed by Keycloak                             |

The permission tools only handle the `publication` and `profile` object types; a permission without `objectId`
applies to every object of the type (`CREATE` there allows creating publications or profiles). Uploading files,
download requests, archives and statistics are not available through the server.

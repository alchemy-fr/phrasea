---
title: MCP server
---

# MCP server

Databox API embeds a [Model Context Protocol](https://modelcontextprotocol.io/) server so that AI assistants
(Claude, IDE agents…) can search, describe and organize assets and set up workspaces on behalf of a user.

- Endpoint: `https://<DATABOX_API_URL>/mcp` (Streamable HTTP transport), with a Keycloak access token of the user
- Configuration: `databox/api/config/packages/mcp.yaml` and `alchemy_mcp.yaml`, tools in `databox/api/src/Mcp/Tool/`

Architecture, authentication, client connection and how to add a tool: see
[MCP servers](../Development/mcp.md).

Every tool replays a call to the REST API: the permissions, the validation and the asynchronous processing
(renditions, moves, copies, trash) are those of the API. Attribute values written through the server are
recorded with the `machine` origin (and the `originVendor` given by the model). Out of reach on purpose:
deleting or flushing a workspace, permissions (ACEs) and file uploads (an asset is created from the URL of its
file).

## Tools

### Workspaces

| Tool               | Description                                                      |
|--------------------|------------------------------------------------------------------|
| `list_workspaces`  | List the workspaces of the user                                  |
| `get_workspace`    | Get a workspace by ID or slug                                    |
| `create_workspace` | Create a workspace                                               |
| `update_workspace` | Update the settings of a workspace (locales, trash, terms…)      |

### Renditions

| Tool                             | Description                                                         |
|----------------------------------|---------------------------------------------------------------------|
| `get_rendition_build_reference`  | Reference of the YAML build configuration, one section per module   |
| `list_rendition_definitions`     | List the rendition definitions                                      |
| `get_rendition_definition`       | Get a rendition definition with its build configuration             |
| `create_rendition_definition`    | Create a rendition definition (build mode, build configuration…)    |
| `update_rendition_definition`    | Update a rendition definition                                       |
| `duplicate_rendition_definition` | Copy a rendition definition                                         |
| `sort_rendition_definitions`     | Reorder the rendition definitions of a workspace                    |
| `delete_rendition_definition`    | Delete a rendition definition                                       |
| `list_rendition_policies`        | List the rendition policies of a workspace                          |
| `create_rendition_policy`        | Create a rendition policy                                           |
| `update_rendition_policy`        | Update a rendition policy                                           |
| `delete_rendition_policy`        | Delete a rendition policy                                           |
| `list_asset_renditions`          | List the renditions of an asset                                     |

An invalid build configuration is refused with the message of the API (`422`, on `definition`).

### Attributes, tags, entity lists

| Tool                          | Description                                   |
|-------------------------------|-----------------------------------------------|
| `list_attribute_definitions`  | List the attribute definitions                |
| `get_attribute_definition`    | Get an attribute definition                   |
| `create_attribute_definition` | Create an attribute definition                |
| `update_attribute_definition` | Update an attribute definition                |
| `sort_attribute_definitions`  | Reorder the attribute definitions             |
| `delete_attribute_definition` | Delete an attribute definition                |
| `list_attribute_policies`     | List the attribute policies of a workspace    |
| `create_attribute_policy`     | Create an attribute policy                    |
| `update_attribute_policy`     | Update an attribute policy                    |
| `delete_attribute_policy`     | Delete an attribute policy                    |
| `list_tags`                   | List/search the tags                          |
| `get_tag`                     | Get a tag                                     |
| `create_tag`                  | Create a tag                                  |
| `update_tag`                  | Update a tag                                  |
| `delete_tag`                  | Delete a tag                                  |
| `list_entity_lists`           | List the entity lists                         |
| `get_entity_list`             | Get an entity list                            |
| `create_entity_list`          | Create an entity list                         |
| `update_entity_list`          | Update an entity list                         |
| `delete_entity_list`          | Delete an entity list                         |

### Integrations

| Tool                     | Description                                                   |
|--------------------------|---------------------------------------------------------------|
| `list_integration_types` | List the available integration types                          |
| `get_integration_type`   | Get an integration type with the reference of its configuration |
| `list_integrations`      | List the integrations of the workspaces or of the instance    |
| `get_integration`        | Get an integration with its configuration                     |
| `create_integration`     | Set up an integration (YAML configuration)                    |
| `update_integration`     | Update an integration                                         |
| `delete_integration`     | Delete an integration                                         |

### Collections

| Tool                  | Description                                          |
|-----------------------|------------------------------------------------------|
| `list_collections`    | List/search the collections (roots or children)      |
| `get_collection`      | Get a collection                                     |
| `create_collection`   | Create a collection                                  |
| `update_collection`   | Update a collection (name, privacy, translations)    |
| `move_collection`     | Move a collection under another one or to the root   |
| `delete_collections`  | Move collections to the trash                        |
| `restore_collections` | Restore collections from the trash                   |

### Assets

| Tool                           | Description                                                         |
|--------------------------------|---------------------------------------------------------------------|
| `search_assets`                | Search the assets: full-text query, [AQL](./aql.md) conditions, facets |
| `get_asset`                    | Get an asset                                                        |
| `create_asset_from_url`        | Create an asset from the URL of its file, or a story                |
| `update_asset`                 | Update the privacy or the tags of an asset                          |
| `set_asset_attributes`         | Set, add or delete attribute values (`machine` origin)              |
| `move_assets`                  | Move assets into a collection or to the workspace root              |
| `copy_assets`                  | Copy assets into a collection or a workspace                        |
| `add_assets_to_collection`     | Add assets to a collection or a story                               |
| `remove_asset_from_collection` | Remove an asset from a collection                                   |
| `delete_assets`                | Move assets to the trash                                            |
| `restore_assets`               | Restore assets from the trash                                       |
| `find_similar_assets`          | Find the visually similar assets                                    |

### Baskets and shares

| Tool                 | Description                                  |
|----------------------|----------------------------------------------|
| `list_baskets`       | List the baskets of the user                 |
| `create_basket`      | Create a basket                              |
| `update_basket`      | Rename a basket                              |
| `delete_basket`      | Delete a basket                              |
| `add_to_basket`      | Add assets to a basket (or the default one)  |
| `remove_from_basket` | Remove items from a basket                   |
| `list_basket_assets` | List the items of a basket                   |
| `list_shares`        | List the share links of an asset             |
| `create_share`       | Create a public share link                   |
| `update_share`       | Update a share link                          |
| `delete_share`       | Delete a share link                          |

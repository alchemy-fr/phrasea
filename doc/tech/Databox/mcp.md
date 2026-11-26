---
title: MCP server
---

# MCP server

Databox API embeds a [Model Context Protocol](https://modelcontextprotocol.io/) server so that AI assistants
(Claude, IDE agents…) can search, describe and organize assets and set up workspaces on behalf of a user.

- Endpoint: `<DATABOX_API_URL>/mcp` (Streamable HTTP transport), with a Keycloak access token of the user
- Configuration: `databox/api/config/packages/mcp.yaml` and `alchemy_mcp.yaml`, tools in `databox/api/src/Mcp/Tool/`

Architecture, authentication, client connection and how to add a tool: see
[MCP servers](../Development/mcp.md).

Every tool replays a call to the REST API: the permissions, the validation and the asynchronous processing
(renditions, moves, copies, trash) are those of the API. Attribute values written through the server are
recorded with the `machine` origin (and the `originVendor` given by the model); a `delete` action removes the
values whatever their origin. Out of reach on purpose: deleting or flushing a workspace, the access control
(ACEs, attribute filter rules, asset policies) and file uploads (an asset or a rendition gets its file from a
URL).

## Tools

### Workspaces

| Tool               | Description                                                      |
|--------------------|------------------------------------------------------------------|
| `list_workspaces`  | List the workspaces of the user                                  |
| `get_workspace`    | Get a workspace by ID or slug                                    |
| `create_workspace` | Create a workspace (empty: no default renditions nor attributes) |
| `update_workspace` | Update the settings of a workspace (locales, trash, terms…)      |

### Renditions

| Tool                             | Description                                                            |
|----------------------------------|------------------------------------------------------------------------|
| `get_rendition_build_reference`  | Reference of the YAML build configuration, one section per module      |
| `list_rendition_definitions`     | List the rendition definitions                                         |
| `get_rendition_definition`       | Get a rendition definition with its build configuration                |
| `create_rendition_definition`    | Create a rendition definition (build mode, build configuration…)       |
| `update_rendition_definition`    | Update a rendition definition                                          |
| `duplicate_rendition_definition` | Copy a rendition definition                                            |
| `sort_rendition_definitions`     | Reorder the rendition definitions of a workspace                       |
| `delete_rendition_definition`    | Delete a rendition definition                                          |
| `list_rendition_policies`        | List the rendition policies of a workspace                             |
| `create_rendition_policy`        | Create a rendition policy                                              |
| `update_rendition_policy`        | Update a rendition policy                                              |
| `delete_rendition_policy`        | Delete a rendition policy                                              |
| `list_asset_renditions`          | List the renditions of an asset                                        |
| `build_custom_rendition`         | Build a custom rendition of an asset from an inline YAML configuration |
| `set_rendition_from_url`         | Replace the file of a rendition by the file at a URL (substitution)    |
| `delete_rendition`               | Delete a rendition of an asset                                         |

An invalid build configuration is refused with the message of the API (`422`, on `definition`).

### Attributes, entity lists, entities

| Tool                          | Description                                                     |
|-------------------------------|-----------------------------------------------------------------|
| `list_attribute_definitions`  | List the attribute definitions                                  |
| `get_attribute_definition`    | Get an attribute definition                                     |
| `create_attribute_definition` | Create an attribute definition                                  |
| `update_attribute_definition` | Update an attribute definition                                  |
| `sort_attribute_definitions`  | Reorder the attribute definitions                               |
| `delete_attribute_definition` | Delete an attribute definition                                  |
| `list_attribute_policies`     | List the attribute policies of a workspace                      |
| `create_attribute_policy`     | Create an attribute policy                                      |
| `update_attribute_policy`     | Update an attribute policy                                      |
| `delete_attribute_policy`     | Delete an attribute policy                                      |
| `list_entity_lists`           | List the entity lists                                           |
| `get_entity_list`             | Get an entity list                                              |
| `create_entity_list`          | Create an entity list                                           |
| `update_entity_list`          | Update an entity list                                           |
| `delete_entity_list`          | Delete an entity list                                           |
| `import_entity_list_values`   | Add or update values in bulk (raw lines or CSV)                 |
| `export_entity_list_values`   | Export the values of a list (CSV or JSON)                       |
| `clear_entity_list`           | Delete every value of a list                                    |
| `list_entities`               | List/search the entities (values) of the lists                  |
| `get_entity`                  | Get an entity                                                   |
| `create_entity`               | Add an entity to a list                                         |
| `update_entity`               | Update an entity (value, translations, synonyms, emoji, color…) |
| `delete_entity`               | Delete an entity                                                |
| `merge_entities`              | Merge duplicate entities into one                               |

An entity attribute holds entity IDs: `set_asset_attributes` and the AQL conditions of `search_assets` take
the IDs returned by `list_entities`, not the labels. The image of an entity is out of reach (file upload).

### Integrations

| Tool                     | Description                                                     |
|--------------------------|-----------------------------------------------------------------|
| `list_integration_types` | List the available integration types                            |
| `get_integration_type`   | Get an integration type with the reference of its configuration |
| `list_integrations`      | List the integrations of the workspaces or of the instance      |
| `get_integration`        | Get an integration with its configuration                       |
| `create_integration`     | Set up an integration (YAML configuration)                      |
| `update_integration`     | Update an integration                                           |
| `delete_integration`     | Delete an integration                                           |

### Collections

| Tool                  | Description                                        |
|-----------------------|----------------------------------------------------|
| `list_collections`    | List/search the collections (roots or children)    |
| `get_collection`      | Get a collection                                   |
| `create_collection`   | Create a collection                                |
| `update_collection`   | Update a collection (name, privacy, translations)  |
| `move_collection`     | Move a collection under another one or to the root |
| `delete_collections`  | Move collections to the trash                      |
| `restore_collections` | Restore collections from the trash                 |

### Assets

| Tool                           | Description                                                                    |
|--------------------------------|--------------------------------------------------------------------------------|
| `search_assets`                | Search the assets: full-text query, [AQL](./aql.md) conditions, facets         |
| `get_asset`                    | Get an asset                                                                   |
| `create_asset_from_url`        | Create an asset from the URL of its file, or a story                           |
| `update_asset`                 | Update the privacy or the external ID of an asset                              |
| `set_asset_attributes`         | Set, add or delete attribute values (`machine` origin)                         |
| `move_assets`                  | Move assets into a collection or to the workspace root                         |
| `copy_assets`                  | Copy assets into a collection or a workspace                                   |
| `add_assets_to_collection`     | Add assets to a collection or a story                                          |
| `remove_asset_from_collection` | Remove an asset from a collection                                              |
| `delete_assets`                | Move assets to the trash                                                       |
| `restore_assets`               | Restore assets from the trash                                                  |
| `find_similar_assets`          | Find the visually similar assets                                               |
| `batch_update_attributes`      | Set, add, delete or replace attribute values on many assets (`machine` origin) |

`search_assets` runs a saved search with `savedSearchId`: its query, conditions and sort apply, refined by
the given ones.

### Stories and attachments

| Tool                 | Description                                                    |
|----------------------|----------------------------------------------------------------|
| `list_story_assets`  | List the assets of a story in their order, with their position |
| `set_asset_position` | Move an asset to a rank inside a story or a collection         |
| `set_story_cover`    | Make one of the assets of a story its cover                    |
| `list_attachments`   | List the attachments of an asset                               |
| `create_attachment`  | Attach an existing asset of the workspace to an asset          |
| `delete_attachment`  | Remove an attachment (the attached asset is kept)              |

An attachment is another asset: attaching a new file means creating its asset first
(`create_asset_from_url`).

### Asset files and history

| Tool                       | Description                                                           |
|----------------------------|-----------------------------------------------------------------------|
| `list_asset_file_versions` | List the previous versions of the source file of an asset             |
| `find_duplicate_assets`    | Find the assets the file analysis detected as duplicates of an asset  |
| `get_file_metadata`        | Get the metadata read from a file (EXIF, IPTC, XMP…)                  |
| `get_asset_metrics`        | Get the audience of an asset, from Matomo (refused without Matomo)    |
| `list_audit_logs`          | List the audit log: by object, type, action, user, workspace, dates   |

`list_audit_logs` is reserved to the administrators, as `GET /audit-logs`.

### Discussions

| Tool                   | Description                                                     |
|------------------------|-----------------------------------------------------------------|
| `list_thread_messages` | List the messages of a discussion thread                        |
| `post_message`         | Post a message in the discussion of an asset (or of a thread)   |

The discussion of an asset is the thread keyed by `threadKey` (`asset:<ID>`), given by `get_asset` with
`thread.id` once a first message created it. There is no listing of the threads themselves: the API has none.

### Saved searches

| Tool                            | Description                                                      |
|---------------------------------|------------------------------------------------------------------|
| `list_saved_searches`           | List the saved searches the user can see                         |
| `get_saved_search`              | Get a saved search with its criteria                             |
| `create_saved_search`           | Save a query, AQL conditions and a sort under a name             |
| `update_saved_search`           | Rename a saved search, change its visibility or its criteria     |
| `delete_saved_search`           | Delete a saved search and its automations                        |
| `list_saved_search_automations` | List the automations of a saved search                           |
| `run_saved_search_automation`   | Run an automation now (or a dry run counting its results)        |
| `list_automation_runs`          | List the runs of an automation, with their status and results    |

The criteria are stored as the client stores them (`data`: `query`, `conditions` as `{id, query}`, `sortBy`
as `{a, w, g}`), so that a saved search created by a model opens in the client. Creating or editing the
automations themselves stays in the client.

### Data templates

| Tool                         | Description                                                    |
|------------------------------|----------------------------------------------------------------|
| `list_asset_data_templates`  | List the data templates of a workspace or of a collection      |
| `get_asset_data_template`    | Get a data template with its attribute values                  |
| `create_asset_data_template` | Create a data template (attributes, privacy, collection scope) |
| `update_asset_data_template` | Update a data template                                         |
| `delete_asset_data_template` | Delete a data template                                         |

### Exports and imports

| Tool                       | Description                                                           |
|----------------------------|-----------------------------------------------------------------------|
| `create_asset_export`      | Export renditions of assets as an archive, built in the background    |
| `get_asset_export`         | Follow an export: its status, then the URL of the archive once ready  |
| `list_asset_imports`       | List the asset imports of a workspace, with their counts              |
| `list_asset_import_errors` | List the entries of an import that could not be imported              |

Starting an import (an instructions file) stays in the client and the API.

### Workflows and quarantine

| Tool                         | Description                                                            |
|------------------------------|------------------------------------------------------------------------|
| `list_workflows`             | List the workflow runs (of given assets, all of them for an admin)     |
| `get_workflow`               | Get a workflow run with its jobs                                       |
| `rerun_workflow_job`         | Run a job of a workflow run again                                      |
| `cancel_workflow`            | Cancel a running workflow run                                          |
| `reprocess_asset`            | Run the ingest workflow of an asset again (`409` while one is running) |
| `check_quarantined_assets`   | Tell which resolution each quarantined asset is eligible to            |
| `resolve_quarantined_assets` | Bypass, merge, trash or delete quarantined assets, asset by asset      |
| `bypass_quarantine`          | Accept a quarantined asset despite its failed analysis                 |

### References

| Tool                       | Description                                                        |
|----------------------------|--------------------------------------------------------------------|
| `list_field_types`         | Types of the attribute definitions                                 |
| `list_built_in_attributes` | Built-in fields of the assets (`@createdAt`…), for AQL and sorts   |
| `list_metadata_tags`       | Metadata tags of the files, for `readFromMetadata`/`writeMetadata` |
| `list_locales`             | Locale codes and names                                             |

### Baskets and shares

| Tool                 | Description                                 |
|----------------------|---------------------------------------------|
| `list_baskets`       | List the baskets of the user                |
| `create_basket`      | Create a basket                             |
| `update_basket`      | Rename a basket                             |
| `delete_basket`      | Delete a basket                             |
| `add_to_basket`      | Add assets to a basket (or the default one) |
| `remove_from_basket` | Remove items from a basket                  |
| `list_basket_assets` | List the items of a basket                  |
| `list_shares`        | List the share links of an asset            |
| `create_share`       | Create a public share link                  |
| `update_share`       | Update a share link                         |
| `delete_share`       | Delete a share link                         |

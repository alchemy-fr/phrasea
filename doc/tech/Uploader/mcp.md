---
title: MCP server
---

# MCP server

Uploader API embeds a [Model Context Protocol](https://modelcontextprotocol.io/) server so that AI assistants
(Claude, IDE agents…) can browse the upload targets and their form, check form data and follow the commits on
behalf of a user.

- Endpoint: `https://<UPLOADER_API_URL>/mcp` (Streamable HTTP transport), with a Keycloak access token of the user
- Configuration: `uploader/api/config/packages/mcp.yaml` and `alchemy_mcp.yaml`, tools in `uploader/api/src/Mcp/Tool/`

Architecture, authentication, client connection and how to add a tool: see
[MCP servers](../Development/mcp.md).

Files are uploaded (S3 multipart) and committed from the Uploader client: the server has no tool to send files.

## Tools

| Tool                     | Description                                                                 | Permission                       |
|--------------------------|-----------------------------------------------------------------------------|----------------------------------|
| `list_targets`           | List/search the targets the user may use                                    | any user                         |
| `get_target`             | Get a target                                                                | target allowed to the user       |
| `get_target_form_schema` | Get the upload form of a target (`/targets/{id}/form`), null without form   | target allowed to the user       |
| `validate_form_data`     | Validate form data against the form of a target (`POST /form/validate`)     | any user                         |
| `list_commits`           | List the commits, filtered by target and acknowledgement                    | `commit:list` scope or admin     |
| `get_commit`             | Get a commit with its files and form data                                   | `commit:list` scope or admin     |
| `get_upload_asset`       | Get an uploaded file (asset) of a commit                                    | admin                            |

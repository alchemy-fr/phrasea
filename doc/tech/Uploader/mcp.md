---
title: MCP server
---

# MCP server

Uploader API embeds a [Model Context Protocol](https://modelcontextprotocol.io/) server so that AI assistants
(Claude, IDE agents…) can send files from a URL, fill the upload form of the targets, follow and acknowledge the
commits, manage the upload links and administer the targets on behalf of a user.

- Endpoint: `https://<UPLOADER_API_URL>/mcp` (Streamable HTTP transport), with a Keycloak access token of the user
- Configuration: `uploader/api/config/packages/mcp.yaml` and `alchemy_mcp.yaml`, tools in `uploader/api/src/Mcp/Tool/`

Architecture, authentication, client connection and how to add a tool: see
[MCP servers](../Development/mcp.md).

The model never sends file bytes: `upload_from_url` makes the server download the file from a public URL
(`POST /downloads`) and commit it, in the background. The other uploads (S3 multipart) and commits are made from
the Uploader client. `upload_from_url` answers once the download is queued: the URLs of private networks and the
refused file types only fail when the worker downloads the file, and the downloads are rate limited per user
(`DOWNLOAD_RATE_LIMIT` per hour, `429` over it).

## Tools

| Tool                           | Description                                                                                        | Permission                                       |
|--------------------------------|----------------------------------------------------------------------------------------------------|--------------------------------------------------|
| `list_targets`                 | List/search the targets the user may use                                                           | any user                                         |
| `get_target`                   | Get a target                                                                                       | target allowed to the user                       |
| `get_target_form_schema`       | Get the upload form of a target (`/targets/{id}/form`), null without form                          | target allowed to the user                       |
| `list_form_field_options`      | List the values of an entity field of the form (`/targets/{id}/form-fields/{key}/options`)        | target allowed to the user                       |
| `validate_form_data`           | Validate form data against the form of a target (`POST /form/validate`)                            | target allowed to the user                       |
| `upload_from_url`              | Download a file from a URL and commit it to a target with form data (`POST /downloads`)            | upload to the target allowed to the user         |
| `list_commits`                 | List the commits, filtered by target and acknowledgement                                           | `commit:list` scope or admin                     |
| `get_commit`                   | Get a commit with its files and form data                                                          | `commit:list` scope or admin                     |
| `get_upload_asset`             | Get an uploaded file (asset) of a commit                                                           | admin                                            |
| `acknowledge_commit`           | Acknowledge a commit and its files (`POST /commits/{id}/ack`), to unblock a pending commit         | admin                                            |
| `acknowledge_upload_asset`     | Acknowledge a file (`POST /assets/{id}/ack`); the last one acknowledges the commit                 | admin                                            |
| `list_upload_links`            | List the upload links, filtered by target                                                          | `upload_link` permission (own links), admin (all) |
| `get_upload_link`              | Get an upload link with its token, limits, counters and status                                     | `upload_link` permission (own links), admin      |
| `create_upload_link`           | Create an upload link to a target                                                                  | `upload_link` permission and upload to the target |
| `update_upload_link`           | Update an upload link (name, enabled, expiration, limits)                                          | `upload_link` permission (own links), admin; its creator may always disable it |
| `delete_upload_link`           | Delete an upload link                                                                              | `upload_link` permission (own links), admin      |
| `regenerate_upload_link_token` | Give an upload link a new token: the former URL stops working                                      | `upload_link` permission (own links), admin      |
| `get_target_settings`          | Get a target with its delivery settings (`/targets/{id}/settings`)                                 | admin                                            |
| `create_target`                | Create a target                                                                                    | admin                                            |
| `update_target`                | Update a target                                                                                    | admin                                            |
| `delete_target`                | Delete a target, with its upload links                                                             | admin                                            |
| `update_target_form`           | Replace or remove the upload form of a target (`PUT /targets/{id}/form`)                           | admin or form editing permission                 |
| `get_target_data`              | Get the data merged into the form data of the commits of a target (`/targets/{id}/data`)           | admin or target data editing permission          |
| `update_target_data`           | Replace or remove the data of a target (`PUT /targets/{id}/data`)                                  | admin or target data editing permission          |

`update_target` and `update_upload_link` only send the given fields; the fields listed in their `clear` argument
are reset to null (e.g. the authorization key of a target, the limits of a link).

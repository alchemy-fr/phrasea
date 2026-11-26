---
title: Setup
---

# Uploader Setup

The Uploader is part of the stack (Docker Compose profile `uploader`, plus `uploader-scheduler` for the scheduler). Its Keycloak clients (`UPLOADER_CLIENT_ID` for the client, `UPLOADER_ADMIN_CLIENT_ID` for the admin) are provisioned by the configurator. Its variables are described in [Configuration](./configuration.md).

What remains is to create the targets. A target is created by an admin, in the Uploader client (Targets > New target) or in the admin (Uploads > Targets). Its destination is set by four properties:

- **Target URL** (`targetUrl`): where the commits are pushed, in https. Empty for the pull mode.
- **Authorization scheme** (`authorizationScheme`): the scheme of the `Authorization` header sent to the target URL, `ApiKey` by default.
- **Authorization key** (`authorizationKey`): the credential sent with it. It is never shown again, and it is removed when the target URL moves to another origin (scheme, host or port), unless a new key is entered along with it.
- **Default destination** (`defaultDestination`): the collection the files go to when the form does not choose one (`collection_destination`).

The **allowed groups** restrict the target to some Keycloak groups; empty, every user may use it. A **hidden** target is not listed to the users, but still accepts uploads by its slug; a **disabled** one accepts none. Both are set in the admin.

## Target for Databox

1. In Databox, add an **Uploader** integration to the workspace that receives the files (see [Integrations](../Databox/integration/01_integrations.md)). An integration belongs to one workspace: add one per workspace. Its configuration:

    ```yaml
    # Base URL of the Uploader API
    baseUrl: '${UPLOADER_API_URL}'
    # Collection receiving the files (optional)
    collectionId: ~
    # Key authenticating the requests of the Uploader, generated when left empty
    securityKey: ~
    ```

2. The integration then shows the values to report in the target: the **Target URL** (`https://<databox api>/integrations/uploader/<integration id>/incoming-commit`), the **Authorization Scheme** (`ApiKey`) and the **Security Key**.
3. In the Uploader, create the target with this URL, the `ApiKey` scheme (the default) and the security key as authorization key. The default destination can be the ID of a collection of the workspace.
4. In the Form tab of the target, pick the workspace in the form builder to offer its attributes (see [Form Configuration](./form_config.md)).

Databox reads the commit and its files with the token of the notification, creates the assets on behalf of the uploading user (within their rights), then acknowledges each file. The collections and assets named by the data must belong to the workspace of the integration; a file whose `data` holds `targetAsset` and `targetRendition` replaces that rendition of an existing asset instead of creating one.

The upload links read the entity values of their form through this integration: their target must use its URL and its key (see [Upload Links](./upload_links.md#attribution)).

## Target for Phraseanet

In push mode, the target notifies the enqueue endpoint of Phraseanet:

- **Target URL**: `https://<phraseanet>/api/v1/upload/enqueue/`;
- **Authorization scheme**: `OAuth`;
- **Authorization key**: a Phraseanet API token, created for an application in the developer pages of Phraseanet. The files are ingested as the Phraseanet user of the token;
- **Default destination**: the ID of a Phraseanet collection (e.g. `42`).

Phraseanet then reads the files from `UPLOADER_API_URL` with the token of the notification, and acknowledges the commit. A Phraseanet on the private network must be listed in `UPLOADER_TARGET_PRIVATE_ORIGINS` (see below).

## Target for another application

Any application can be a destination. It exposes an https endpoint that:

1. accepts the `POST` of the Uploader, authenticated by `Authorization: <scheme> <key>`, and answers a 2xx within 60 seconds;
2. reads the commit (`GET /commits/{id}`) and each file (`GET /assets/{id}`, then its signed `url`) with `Authorization: AssetToken <token>`;
3. acknowledges each file (`POST /assets/{id}/ack`) or the whole commit (`POST /commits/{id}/ack`).

The body of the notification and the calls are described in [Request Flow](./request_flow.md).

## Pull mode

A target without URL is in pull mode: nothing is pushed. The destination lists the commits itself, at its own pace:

- with an access token carrying the `commit:list` scope (a Keycloak client of its own) or of an admin;
- from the **Pull mode URL** of the target, shown in the admin: `<UPLOADER_API_URL>/commits?target=<target id>`, with `acknowledged=false` to skip what it already processed.

It then reads and acknowledges the files of each commit as in push mode. See [Request Flow](./request_flow.md#pull-mode).

Databox has no pull mode: its Uploader integration only receives pushed commits.

## Development

To upload without any destination, create a target:

- with the authorization key `avoid`: the target is never notified;
- or without target URL: it is in pull mode, and nothing reads its commits.

In both cases the commits stay unacknowledged, and their files are deleted after `ASSET_DAYS_RETENTION` days. The "trigger again" action of the Commits of the admin notifies a commit again, once the target is fixed.

The client shows a "Debug user" entry in the user menu when its `DEV_MODE` is on (see `uploader/client/README.md`).

## Targets on the Private Network

The URL of a target is set by an Uploader admin: on each commit, the worker notifies it with a `POST`.
It reaches the private network only for the origin (scheme, host and port) of `DATABOX_API_URL` and for the origins listed in `UPLOADER_TARGET_PRIVATE_ORIGINS`, comma separated.
Left empty (the default), it lists the Phraseanet of the stack, `PHRASEANET_URL`, which `docker-compose.yml` routes to the gateway; a value replaces it, so list `PHRASEANET_URL` in it to keep that target.
A target on any other host resolving to a private address (another service of the stack, an internal Phraseanet…) is refused unless its origin is listed:

```bash
UPLOADER_TARGET_PRIVATE_ORIGINS=https://phraseanet.local,http://phraseanet-internal:8080
```

A notification to one of these origins does not follow redirections.

## Further Reading

- [Uploader Configuration](./configuration.md)
- [Request Flow](./request_flow.md)
- [Form Configuration](./form_config.md)
- [Upload Links](./upload_links.md)
- [API reference](./api.md)

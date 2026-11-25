---
title: Intro
---

# Uploader

The Uploader receives files from users and hands them over to a destination: Databox, Phraseanet or a third-party application. The users only see a reduced interface: they pick a target, drop their files, fill in a form, and the destination ingests the files with the data of the form.

## Components

- **API** (`uploader/api`, `uploader-api-php` behind `uploader-api-nginx`): a Symfony application that presigns the uploads, records the files and the commits, notifies the destinations and serves the files to them. It also holds the admin (EasyAdmin) and an MCP server.
- **Worker** (`uploader-worker`): consumes the RabbitMQ queues: notifications of the targets, acknowledgements, imports from a URL, deletion of the files. Scale it as needed.
- **Scheduler** (`uploader-scheduler`): runs the daily cleanup tasks. A single replica.
- **Client** (`uploader/client`, `uploader-client`): a Next.js application where the users upload, and where the targets, their forms and the upload links are managed.
- **Storage**: an S3 bucket (`UPLOADER_S3_BUCKET_NAME`). The browsers send the files straight to it, in multipart uploads; the destinations read them from it through signed URLs.

The users sign in with Keycloak (the `UPLOADER_CLIENT_ID` client).

## Targets and destinations

A **target** is where a batch of files goes. It has a form, filled in by the user, data added to every batch, and the groups allowed to use it. Its destination is one of:

- **Databox**: the target URL and its key come from an Uploader integration of a Databox workspace (see [Integrations](../Databox/integration/01_integrations.md)). Databox creates one asset per file, with the values of the form as attributes.
- **Phraseanet**: the enqueue endpoint of a Phraseanet instance.
- **A third-party application**: any HTTPS endpoint accepting the notification of the Uploader (see [Request Flow](./request_flow.md#notification-of-the-target)).

## Push and pull

- **Push**: the target has a URL. Once a batch is committed, the worker notifies it with a `POST`; the destination then reads the files and acknowledges them.
- **Pull**: the target has no URL. The destination lists the unacknowledged commits of the target itself, at its own pace, from a network that does not need to be reachable from the Uploader.

## Uploading

A user uploads from the client: the files are sent while the form is filled in, then the batch is committed. A file can also be imported from a URL: the worker downloads it. An **upload link** lets someone without an account send files to one target, within limits (see [Upload Links](./upload_links.md)).

## Retention

The Uploader is not a storage: once the destination acknowledges a commit, its files are deleted (after `UPLOADER_DELETE_ASSET_GRACEFUL_TIME` seconds), and every file is deleted after `ASSET_DAYS_RETENTION` days (31) anyway. See [Retention and cleanup](./configuration.md#retention-and-cleanup).

## Pages of this section

- [Setup](./02_setup.md): create a target for Databox, Phraseanet or another application.
- [Configuration](./configuration.md): environment variables, stack configuration, retention, notifications.
- [Request Flow](./request_flow.md): the sequence of an upload, the notification of the target, the pull mode.
- [API reference](./api.md): the endpoints, the authentication and the permissions.
- [Form Configuration](./form_config.md): the form builder, the target data.
- [Upload Links](./upload_links.md): uploads without an account.
- [Administration](./admin.md): the admin, the console commands, the report log.
- [MCP server](./mcp.md): the tools for AI assistants.

---
title: 'Realtime updates'
---

# Realtime updates

Databox pushes live hints to the browser (a rendition is ready, a workflow job ended, an
export progresses, a new notification…) through [Sockudo](https://github.com/sockudo/sockudo),
a WebSocket server speaking the Pusher protocol. The API publishes events on channels, the
client subscribes to the channels of what it displays and reloads the data from the API.

## Concepts

- **Channel**: a named stream bound to one object (an asset, a workspace, a basket…). Every
  channel Databox emits on is **private** (`private-` prefix): the Sockudo key is public, so
  a subscription must be signed by the API, which checks the rights of the visitor first.
- **Event**: a name and a small JSON payload. Payloads are hints, never the data itself: the
  client reloads the object through the REST API, where the usual permissions apply.
- **Push**: either *direct* (a synchronous HTTP call to Sockudo, from the request or the
  worker) or *through the bus* (a `Alchemy\CoreBundle\Message\PusherMessage` routed to the
  `p1` transport, see [Workers](./workers.md)).

Pushes are best-effort: a failure is logged, never thrown, so that it never fails nor
retries the request or the message that triggered it.

## Server

The `sockudo` compose service (profile `sockudo`, image `SOCKUDO_IMAGE`) runs a single
application whose id, key and secret are `SOCKUDO_APP_ID`, `SOCKUDO_KEY` and
`SOCKUDO_SECRET`. It listens on port `6001` on the `data` network and is routed by Traefik:

| Host                          | Serves                                                                                   |
| ----------------------------- | ---------------------------------------------------------------------------------------- |
| `ws.${PHRASEA_DOMAIN}`        | the WebSocket endpoint (`/usage`, `/stats` and `/operator` excluded)                     |
| `ws-usage.${PHRASEA_DOMAIN}`  | only `/usage` (memory usage), behind the `admin-console` middleware (`SOCKUDO_USAGE_URL`) |

The service sets:

- `SOCKUDO_DEFAULT_APP_ENABLE_CLIENT_MESSAGES=false`: browsers only receive events;
- `SOCKUDO_DEFAULT_APP_ALLOWED_ORIGINS`: the URLs of the Databox, Expose, Uploader and
  Dashboard clients;
- `SOCKUDO_DEFAULT_APP_MAX_CONNECTIONS=100`;
- local/memory drivers (`ADAPTER_DRIVER`, `CACHE_DRIVER`, `QUEUE_DRIVER`,
  `APP_MANAGER_DRIVER`): a single node.

Its health check calls `http://127.0.0.1:6001/up/${SOCKUDO_APP_ID}`.

### Who reaches it, and how

| Container                                  | `SOCKUDO_HOST`                         | Other variables                                                  |
| ------------------------------------------ | -------------------------------------- | ---------------------------------------------------------------- |
| `databox-api-php` (and `databox-worker`, which extends it) | `sockudo` | `SOCKUDO_PORT=6001`, `SOCKUDO_SCHEME=http`, `SOCKUDO_APP_ID`, `SOCKUDO_KEY`, `SOCKUDO_SECRET` |
| `databox-client` (browser side)            | `ws.${PHRASEA_DOMAIN}${HTTPS_PORT_PREFIX}` | `SOCKUDO_KEY` only                                           |

The API pushes on the internal network over plain HTTP. Without `SOCKUDO_PORT` and
`SOCKUDO_SCHEME`, the PHP client falls back to HTTPS on port 443. To use an external
Pusher-compatible server without the `sockudo` profile, override these three variables for
`databox-api-php`.

`SOCKUDO_APP_ID`, `SOCKUDO_KEY` and `SOCKUDO_SECRET` must all be set (the `.env` values are
marked `@change-me`).

### PHP side

The Pusher integration is the `pusher` section of the `alchemy_core` configuration
(`lib/php/core-bundle`), enabled by `pusher: ~` in `databox/api/config/packages/alchemy_core.yaml`:

| Option           | Default                                       |
| ---------------- | --------------------------------------------- |
| `host`           | `%env(SOCKUDO_HOST)%`                         |
| `port`           | `%env(SOCKUDO_PORT)%`                         |
| `scheme`         | `%env(SOCKUDO_SCHEME)%`                       |
| `appId`          | `%env(SOCKUDO_APP_ID)%`                       |
| `key` / `secret` | `%env(SOCKUDO_KEY)%` / `%env(SOCKUDO_SECRET)%` |
| `verifySsl`      | `%env(bool:VERIFY_SSL)%`                      |
| `channel_prefix` | `private-user-` (the per-user channel)        |
| `disabled`       | `false` (`true` in the `test` environment)    |

- `Alchemy\CoreBundle\Pusher\PusherManager::trigger()` pushes an event, directly or through
  the bus. An empty `SOCKUDO_HOST` means "no realtime server": direct pushes are skipped.
- `Alchemy\CoreBundle\Pusher\PusherFactory` builds the client with a 1 s connect timeout and a
  2 s request timeout, so that an unreachable host never holds a request or a worker.
- `Alchemy\CoreBundle\Pusher\CircuitBreakerMiddleware`: after a network failure, the pushes
  of the process are skipped for 30 s instead of each waiting for the timeout. Any answer of
  the server closes the circuit again. A transport failure is logged as a warning, an error
  answered by Sockudo as an error, a push skipped by the open circuit as debug.
- Channel names are normalized: every character outside `a-z 0-9 _ - = @ , . ;` becomes a
  `.` (e.g. the thread key `asset:<id>` becomes `asset.<id>`). The client applies the same
  rule.

## Channel authorization

The client (pusher-js) asks the API to sign each subscription:

```http
POST /pusher/auth
{"socket_id": "…", "channel_name": "private-asset-…"}
```

`Alchemy\CoreBundle\Controller\RealtimeAuthController` (route `alchemy_core_pusher_auth`)
accepts a JSON or a form-encoded body. It refuses (403) any channel without the `private-`
prefix and any channel no authorizer supports. Otherwise the first tagged
`Alchemy\CoreBundle\Pusher\ChannelAuthorizerInterface` supporting the channel decides; a
refusal answers 401 to an anonymous visitor (logging in may grant it) and 403 to an
authenticated user. The endpoint is open to anonymous visitors: a public workspace can be
followed without an account.

| Channel                           | Authorizer                                  | Granted to                                                        |
| --------------------------------- | ------------------------------------------- | ----------------------------------------------------------------- |
| `private-user-{userId}`           | `Alchemy\CoreBundle\Pusher\UserChannelAuthorizer` | the user whose id it is, only (never anonymous)            |
| `private-workspace-{id}`          | `App\Realtime\EntityChannelAuthorizer`      | whoever may `READ` the workspace                                  |
| `private-asset-{id}`              | idem                                        | whoever may `READ` the asset                                      |
| `private-file-{id}`               | idem                                        | whoever may `READ` the file                                       |
| `private-basket-{id}`             | idem                                        | whoever may `READ` the basket                                     |
| `private-export-{id}`             | idem                                        | whoever may `READ` the export (`AssetExport`)                     |
| `private-workflow-{id}`           | idem                                        | whoever may read the workflow state, i.e. `EDIT` its asset        |
| `private-thread-{objectKey}.{id}` | idem                                        | whoever may `READ` the object the discussion is about (the thread may not exist yet) |

`EntityChannelAuthorizer` loads the object by its UUID and asks the voters with the current
security token, so the anonymous visitors and the [impersonated users](./impersonation.md)
get exactly their own rights. An unknown or malformed id is refused. The channel names are
built by `App\Realtime\RealtimeChannel`.

## Events

| Channel     | Event                               | Payload                                                     | Sent by                                                                 | Push   |
| ----------- | ----------------------------------- | ----------------------------------------------------------- | ----------------------------------------------------------------------- | ------ |
| `workflow`  | `job_update`                        | `jobId`, `status`                                           | `Alchemy\WorkflowBundle\Listener\PusherListener`, on each job change    | direct |
| `workflow`  | `workflow_update`                   | `status`                                                    | idem, on each workflow change                                           | direct |
| `asset`     | `workflow_update`                   | none                                                        | `App\Listener\AssetWorkflowRealtimeListener`: a workflow run of the asset started or ended (not the Uploader incoming-file workflows) | bus |
| `asset`     | `asset_ingested`                    | none                                                        | `App\Listener\AssetIngestWorkflowListener`: the `asset-ingest:` workflow of the asset succeeded or failed | direct |
| `asset`     | `rendition-update`                  | `assetId`, `definition`, `name`                             | `App\Service\Storage\RenditionManager::pushRenditionUpdate()`, after the flush | bus |
| `workspace` | `rendition-update`                  | `assetId`                                                   | idem                                                                    | bus    |
| `asset`     | `integration:core.face_recognition` | `count`                                                     | `App\Integration\Core\FaceRecognition\FaceRecognitionAnalyzer`          | direct |
| `file`      | `integration:aws.rekognition`       | `cat`                                                       | `App\Integration\Aws\Rekognition\Message\RekognitionAnalyzeHandler`     | direct |
| `file`      | `integration:tui.photo-editor`      | `action` (`save`, `delete`), `id`                           | `App\Integration\ToastUi\TuiPhotoEditorIntegration`                     | direct |
| `file`      | `integration:remove.bg`             | none                                                        | `App\Integration\RemoveBg\Message\RemoveBgCallHandler`                  | direct |
| `basket`    | `integration:phrasea.expose`        | `id`, `action` (`sync-progress` with `total`/`done`, `sync-clean`, `sync-complete` with `skipped`, `sync-failed` with `reason`) | `App\Integration\Phrasea\Expose\ExposeSynchronizer`, `…\Message\SyncBasketHandler` | direct |
| `export`    | `progress`                          | `progress` (0 to 1)                                         | `App\Consumer\Handler\Asset\AssetExportProcessHandler`                  | direct |
| `export`    | `ready`                             | none                                                        | idem                                                                    | direct |
| `export`    | `error`                             | `error`                                                     | idem                                                                    | direct |
| `thread`    | `message` / `message-delete`        | `id`                                                        | `App\Service\Discussion\DiscussionPusher`                               | bus    |
| `user`      | `notification`                      | `id`, `subject`, `content`, `data.uri`, `createdAt`         | `Alchemy\NotifierBundle\Channel\InAppChannel` (in-app notifications)    | bus    |

Some payloads are deliberately empty or minimal because the channel is granted on a lower
right than the data: the `asset` channel only needs `READ` while the workflow runs need
`EDIT`, the `workspace` channel only tells which asset changed (its readers may not read that
asset), a discussion message is read back with the recipient's own permissions.

The workflow channel prefix is set in `databox/api/config/packages/alchemy_workflow.yaml`
(`pusher.channel_prefix: App\Realtime\RealtimeChannel::WORKFLOW`); the workflow pushes are
disabled in the `test` environment. The in-app notification channel and event come from the
`alchemy_notifier` options `in_app_channel_prefix` (`private-user-`) and `in_app_event`
(`notification`): see [Notifications](./notifications.md).

## Client side

`databox/client/src/lib/realtime/`:

- `RealtimeProvider.tsx`: `RealtimeProvider` (mounted in `app/providers.tsx`) loads
  `pusher-js` lazily on the first subscription, connects to `wss://` on the host and port
  of `SOCKUDO_HOST` (443 by default, `ws` transport only, no stats) and signs the private
  channels by posting to `/pusher/auth` through the API client (so with the bearer token
  and the impersonation header). Realtime is off when `SOCKUDO_HOST` or `SOCKUDO_KEY` is
  empty (`config.realtime` undefined). Subscriptions are reference-counted: a channel is
  left when its last listener goes away. On login or logout the connection restarts, so
  that every channel is authorized again for the new visitor.
- `useChannelEvent(channel, event, callback, enabled)`: subscribes for the lifetime of a
  component; `useRealtime()` gives `enabled` and `subscribe()`.
- `channels.ts`: the channel builders (`workspaceChannel`, `assetChannel`, `fileChannel`,
  `basketChannel`, `exportChannel`, `workflowChannel`, `threadChannel`), mirroring
  `App\Realtime\RealtimeChannel`.
- `renditionUpdates.tsx`: `RenditionUpdateFeed`, mounted once in the app shell, subscribes
  to the `workspace` channel of every workspace whose assets are displayed (and those
  declared with `useWatchWorkspaces`, e.g. the quarantine queue) instead of one channel per
  asset; components read the updates with `useRenditionUpdate`.

Main listeners:

| Screen / component                                     | Channel and events                                          |
| ------------------------------------------------------ | ----------------------------------------------------------- |
| Asset view (`AssetViewRoute`), renditions tab          | `asset`: `asset_ingested`, `rendition-update`               |
| Workflow runs and progress of an asset                 | `asset`: `workflow_update`, `asset_ingested`; `workflow`: `job_update`, `workflow_update` |
| Workflow view (`WorkflowViewRoute`)                    | `workflow`: `job_update`, `workflow_update`                 |
| Export watcher (`ExportWatcher`)                       | `export`: `progress`, `ready`, `error`                      |
| Integration panels (Rekognition, photo editor, generic) | `file`: `integration:{name}`                               |
| Face recognition panel                                 | `asset`: `integration:core.face_recognition`                |
| Basket integrations (Expose sync)                      | `basket`: `integration:phrasea.expose`                      |
| Discussions                                            | `thread`: `message`, `message-delete`                       |
| Notifications menu                                     | `private-user-{id}`: `notification` (shown as a toast)      |

## Administration

The admin page **Settings › Realtime** (title **Realtime (Sockudo)**, `ROLE_ADMIN`,
`Alchemy\AdminBundle\Controller\RealtimeStatusController`) shows the server **Status**
(**Disabled**, **Reachable** with its latency, or **Unreachable** with the error), the
**App ID**, the **Occupied channels** and **Subscriptions**, the **Connected users** (deduced
from their `private-user-` channels, with their number of connections) and the **Channels by
type**. See [Administration](./administration.md).

## Configuration

| Variable              | Default (`.env`)                                  | Used by                         |
| --------------------- | ------------------------------------------------- | ------------------------------- |
| `SOCKUDO_IMAGE`       | `ghcr.io/sockudo/sockudo:5.2.0`                   | compose                         |
| `SOCKUDO_APP_ID`      | `phrasea`                                         | Sockudo, APIs                   |
| `SOCKUDO_KEY`         | `__CHANGE_ME_…`                                   | Sockudo, APIs, clients          |
| `SOCKUDO_SECRET`      | `__CHANGE_ME_…`                                   | Sockudo, APIs                   |
| `SOCKUDO_HOST`        | set by compose (see above)                        | APIs, clients                   |
| `SOCKUDO_PORT`        | set by compose for `databox-api-php` (`6001`)     | APIs                            |
| `SOCKUDO_SCHEME`      | set by compose for `databox-api-php` (`http`)     | APIs                            |
| `SOCKUDO_USAGE_URL`   | `https://ws-usage.${PHRASEA_DOMAIN}${HTTPS_PORT_PREFIX}/usage` | dashboard          |

The `sockudo` profile must be listed in `COMPOSE_PROFILES` (it is by default). See
[Configuration](./configuration.md) and [Environment variables](../Configuration/01_env_var.mdx).

## See also

- [Workers](./workers.md): the `p1` transport carrying the bus pushes
- [Workflows](./workflows.md), [Export](./export.md), [Notifications](./notifications.md)
- [Integrations](./integration/01_integrations.md)
- [Impersonation](./impersonation.md)
